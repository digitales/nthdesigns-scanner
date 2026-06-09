# Calendar Dropdown Display Names Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show human-readable Fastmail calendar names in the agency booking settings dropdown, with a short ID suffix only when display names collide.

**Architecture:** Parse `displayname` from the existing CalDAV PROPFIND multistatus in `CalDavXmlParser`, then build dropdown labels in `FastmailCalDavClient::listCalendars()` via a private collision-formatting pass. No frontend changes.

**Tech Stack:** PHP 8.3, Laravel, PHPUnit, `CalDavXmlParser`, `Http::fake()` for client tests.

**Spec:** `docs/superpowers/specs/2026-06-09-calendar-dropdown-names-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Services/Calendar/CalDavXmlParser.php` | Add `parsePropfindResponses()` |
| `app/Services/Calendar/FastmailCalDavClient.php` | Use display names; `formatCalendarLabels()` |
| `tests/Unit/CalDavXmlParserTest.php` | Parser unit tests |
| `tests/Unit/FastmailCalDavClientTest.php` | Label formatting via `Http::fake()` |

---

### Task 1: `parsePropfindResponses` parser

**Files:**
- Modify: `app/Services/Calendar/CalDavXmlParser.php`
- Test: `tests/Unit/CalDavXmlParserTest.php`

- [ ] **Step 1: Write failing parser tests**

Add to `tests/Unit/CalDavXmlParserTest.php`:

```php
#[Test]
public function test_parse_propfind_responses_extracts_href_and_displayname(): void
{
    $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/</d:href>
    <d:propstat><d:prop><d:displayname>Home</d:displayname></d:prop></d:propstat>
  </d:response>
  <d:response>
    <d:href>/dav/calendars/user/me/3f2a1b9c-e4d5-6789-abcd-ef0123456789/</d:href>
    <d:propstat><d:prop><d:displayname>Work</d:displayname></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

    $this->assertSame([
        ['href' => '/dav/calendars/user/me/', 'displayname' => 'Home'],
        ['href' => '/dav/calendars/user/me/3f2a1b9c-e4d5-6789-abcd-ef0123456789/', 'displayname' => 'Work'],
    ], CalDavXmlParser::parsePropfindResponses($xml));
}

#[Test]
public function test_parse_propfind_responses_returns_null_when_displayname_missing(): void
{
    $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/uuid-here/</d:href>
    <d:propstat><d:prop></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

    $this->assertSame([
        ['href' => '/dav/calendars/user/me/uuid-here/', 'displayname' => null],
    ], CalDavXmlParser::parsePropfindResponses($xml));
}

#[Test]
public function test_parse_propfind_responses_decodes_xml_entities_in_displayname(): void
{
    $xml = <<<'XML'
<d:multistatus xmlns:d="DAV:">
  <d:response>
    <d:href>/dav/calendars/user/me/abc/</d:href>
    <d:propstat><d:prop><d:displayname>Tom &amp; Jerry</d:displayname></d:prop></d:propstat>
  </d:response>
</d:multistatus>
XML;

    $this->assertSame('Tom & Jerry', CalDavXmlParser::parsePropfindResponses($xml)[0]['displayname']);
}
```

- [ ] **Step 2: Run tests — expect FAIL**

Run: `php artisan test --filter=parse_propfind`

- [ ] **Step 3: Implement `parsePropfindResponses`**

Add to `CalDavXmlParser.php` after `responseHrefs`:

```php
/**
 * @return list<array{href: string, displayname: ?string}>
 */
public static function parsePropfindResponses(string $xml): array
{
    preg_match_all('/<(?:[^:>]+:)?response[^>]*>([\s\S]*?)<\/(?:[^:>]+:)?response>/i', $xml, $blocks);

    $results = [];

    foreach ($blocks[1] ?? [] as $block) {
        if (! preg_match('/<[^>]*href[^>]*>([^<]+)<\/[^>]*href>/i', $block, $hrefMatch)) {
            continue;
        }

        $displayname = null;

        if (preg_match('/<(?:[^:>]+:)?displayname[^>]*>([\s\S]*?)<\/(?:[^:>]+:)?displayname>/i', $block, $nameMatch)) {
            $decoded = html_entity_decode(trim($nameMatch[1]), ENT_XML1);
            $displayname = $decoded !== '' ? $decoded : null;
        }

        $results[] = [
            'href' => $hrefMatch[1],
            'displayname' => $displayname,
        ];
    }

    return $results;
}
```

- [ ] **Step 4: Run tests — expect PASS**

Run: `php artisan test --filter=parse_propfind`

---

### Task 2: Label formatting in `FastmailCalDavClient`

**Files:**
- Modify: `app/Services/Calendar/FastmailCalDavClient.php`
- Create: `tests/Unit/FastmailCalDavClientTest.php`

- [ ] **Step 1: Write failing client tests**

Create `tests/Unit/FastmailCalDavClientTest.php` with `Http::fake()` returning multistatus XML for unique names, duplicate names, and missing displayname cases.

- [ ] **Step 2: Run tests — expect FAIL**

Run: `php artisan test tests/Unit/FastmailCalDavClientTest.php`

- [ ] **Step 3: Update `listCalendars()` and add `formatCalendarLabels()`**

Replace href-only name derivation with parser output + collision pass (see spec).

- [ ] **Step 4: Run tests — expect PASS**

Run: `php artisan test tests/Unit/FastmailCalDavClientTest.php tests/Unit/CalDavXmlParserTest.php`

- [ ] **Step 5: Manual smoke test**

Settings → Agency booking → Test connection with 2+ calendars → dropdown shows `Work`, not UUID.
