<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProspectQualificationService
{
    public function __construct(
        private OpenRouterService $openRouter,
    ) {}

    public function qualify(Prospect $prospect): void
    {
        if (blank($prospect->website_url)) {
            $prospect->update([
                'qualification_status' => 'caution',
                'qualification_summary' => 'No website URL available to assess.',
                'qualification_flags' => ['No website URL recorded'],
                'qualification_ran_at' => now(),
            ]);

            return;
        }

        $html = $this->fetchHtml($prospect->website_url);

        if ($html === null) {
            $prospect->update([
                'qualification_status' => 'caution',
                'qualification_summary' => 'Website could not be fetched — verify manually.',
                'qualification_flags' => ['Could not fetch website — robots.txt or timeout'],
                'qualification_ran_at' => now(),
            ]);

            return;
        }

        $result = $this->assessWithClaude($html, $prospect->website_url, $this->targetNiche($prospect));

        $prospect->update([
            'qualification_status' => $result['status'],
            'qualification_summary' => $result['summary'],
            'qualification_flags' => $result['flags'],
            'qualification_ran_at' => now(),
        ]);
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; nthdesigns-scanner/1.0)'])
                ->get($url);

            if ($response->successful()) {
                return substr($response->body(), 0, 8000);
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning("ProspectQualificationService: failed to fetch {$url}", ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function targetNiche(Prospect $prospect): string
    {
        $prospect->loadMissing('search');
        $niche = trim((string) ($prospect->search?->niche ?? ''));

        return $niche !== '' ? $niche : 'local business';
    }

    private function assessWithClaude(string $html, string $url, string $niche): array
    {
        $systemPrompt = $this->systemPrompt($niche);

        $userMessage = "Website URL: {$url}\n\nHTML content:\n\n{$html}";

        try {
            $response = $this->openRouter->complete($systemPrompt, $userMessage);
            $decoded = $this->parseAssessmentResponse($response['content']);

            if ($decoded === null) {
                Log::warning('ProspectQualificationService: unparseable Claude response', [
                    'content' => substr($response['content'], 0, 500),
                ]);
                throw new \RuntimeException('Invalid JSON response from Claude');
            }

            return [
                'status' => $decoded['status'],
                'summary' => $decoded['summary'] ?? '',
                'flags' => $decoded['flags'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('ProspectQualificationService: Claude assessment failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'caution',
                'summary' => 'Qualification assessment failed — verify manually.',
                'flags' => ['Claude assessment error: '.$e->getMessage()],
            ];
        }
    }

    private function systemPrompt(string $niche): string
    {
        return <<<PROMPT
You are assessing whether a UK {$niche} business website belongs to an independent owner-operated business or a corporate/chain organisation, for the purpose of cold outreach qualification by a web design and accessibility agency.

Analyse the HTML content provided and return a JSON object with exactly these keys:
- "status": one of "qualified", "caution", or "skip"
- "summary": a single sentence plain-English explanation of your decision
- "flags": an array of specific strings describing what you found (positive and negative signals)

Definitions:
- "qualified" = independently owned business with an identifiable decision-maker and a realistic route to contact
- "caution" = signals are mixed or ambiguous — worth manual review before outreach
- "skip" = not suitable for cold outreach — use one of the skip reasons below

Skip reasons (always include the matching flag string in "flags" when status is "skip"):
- "wrong_niche" = the website is not a {$niche} business (wrong industry, unrelated business, or irrelevant listing)
- "corporate_chain" = corporate chain, franchise, or managed group with no single local decision-maker reachable by cold email

Signals that indicate skip with flag "wrong_niche":
- Website content is clearly a different industry or business type than {$niche}
- No evidence the business operates in the {$niche} category

Signals that indicate skip with flag "corporate_chain":
- Parent company or group branding in footer
- National or multi-location coverage inconsistent with a single local outlet
- Copyright or legal entity belonging to a group rather than the trading name
- Booking or contact flows owned by a corporate platform rather than the local business

Signals that indicate QUALIFIED:
- Named owner or principal visible on homepage or about page
- Family-run or "established by" language
- Single location or clearly local operation
- Contact email address present directly on the page

Return ONLY valid JSON. No preamble, no markdown fences.
PROMPT;
    }

    /**
     * @return array{status: string, summary?: string, flags?: array<string>}|null
     */
    private function parseAssessmentResponse(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $decoded = json_decode($content, true);

        if (is_array($decoded) && isset($decoded['status'])) {
            return $this->normaliseAssessment($decoded);
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded) && isset($decoded['status'])) {
                return $this->normaliseAssessment($decoded);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{status: string, summary?: string, flags?: array<string>}|null
     */
    private function normaliseAssessment(array $decoded): ?array
    {
        $status = $decoded['status'] ?? null;

        if (! in_array($status, ['qualified', 'caution', 'skip'], true)) {
            return null;
        }

        return [
            'status' => $status,
            'summary' => is_string($decoded['summary'] ?? null) ? $decoded['summary'] : '',
            'flags' => is_array($decoded['flags'] ?? null)
                ? array_values(array_filter($decoded['flags'], 'is_string'))
                : [],
        ];
    }
}
