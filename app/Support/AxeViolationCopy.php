<?php

namespace App\Support;

class AxeViolationCopy
{
    /** @var array<string, array{user_impact: string, fix_hint: string}> */
    private const MAP = [
        'color-contrast' => [
            'user_impact' => 'Text and buttons may be hard to read for people with low vision or in bright sunlight.',
            'fix_hint' => 'Increase contrast between text and background to meet WCAG AA (4.5:1 for normal text).',
        ],
        'image-alt' => [
            'user_impact' => 'Screen reader users miss information carried by images.',
            'fix_hint' => 'Add descriptive alt text to every meaningful image.',
        ],
        'label' => [
            'user_impact' => 'Form fields are unclear for screen reader and voice-control users.',
            'fix_hint' => 'Associate a visible <label> with each input using for/id.',
        ],
        'link-name' => [
            'user_impact' => 'Links announced as "click here" give no context out of context.',
            'fix_hint' => 'Use link text that describes the destination or action.',
        ],
        'button-name' => [
            'user_impact' => 'Icon-only buttons are unusable for many assistive technology users.',
            'fix_hint' => 'Provide visible text or an accessible name (aria-label) for each button.',
        ],
        'html-has-lang' => [
            'user_impact' => 'Screen readers may use the wrong language and pronunciation.',
            'fix_hint' => 'Set lang on the <html> element (e.g. lang="en-GB").',
        ],
        'document-title' => [
            'user_impact' => 'Users cannot identify the page when switching tabs or using assistive tech.',
            'fix_hint' => 'Add a unique, descriptive <title> on every page.',
        ],
        'heading-order' => [
            'user_impact' => 'Skipping heading levels confuses document structure for screen reader users.',
            'fix_hint' => 'Use headings in order (h1 → h2 → h3) without skipping levels.',
        ],
        'bypass' => [
            'user_impact' => 'Keyboard users must tab through repetitive navigation on every page.',
            'fix_hint' => 'Add a "skip to main content" link at the top of the page.',
        ],
        'meta-viewport' => [
            'user_impact' => 'Users who zoom on mobile may be unable to read content.',
            'fix_hint' => 'Allow zoom in the viewport meta tag (avoid user-scalable=no).',
        ],
    ];

    private const FALLBACK = [
        'user_impact' => 'This issue creates barriers for people using assistive technology or keyboard-only navigation.',
        'fix_hint' => 'Remediate according to the referenced WCAG criterion or ask a specialist for a targeted fix.',
    ];

    /**
     * @return array{user_impact: string, fix_hint: string}
     */
    public static function forRule(string $ruleId): array
    {
        return self::MAP[$ruleId] ?? self::FALLBACK;
    }
}
