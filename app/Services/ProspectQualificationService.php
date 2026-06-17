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

        $result = $this->assessWithClaude($html, $prospect->website_url);

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

    private function assessWithClaude(string $html, string $url): array
    {
        $systemPrompt = <<<'PROMPT'
You are assessing whether a UK dental practice website belongs to an independent owner-operated business or a corporate/chain organisation, for the purpose of cold outreach qualification by a web design and accessibility agency.

Analyse the HTML content provided and return a JSON object with exactly these keys:
- "status": one of "qualified", "caution", or "skip"
- "summary": a single sentence plain-English explanation of your decision
- "flags": an array of specific strings describing what you found (positive and negative signals)

Definitions:
- "qualified" = independently owned practice with an identifiable decision-maker and a realistic route to contact
- "caution" = signals are mixed or ambiguous — worth manual review before outreach
- "skip" = corporate chain, franchise, or managed group (e.g. Portman Healthcare, mydentist, Bupa Dental, Dental Care Alliance, Portman PDC, or similar) — no single decision-maker reachable by cold email

Signals that indicate SKIP:
- Parent company branding in footer (e.g. "trading name of Portman Healthcare Limited", "part of mydentist", "Bupa Dental Care")
- Registered company number in footer with a Cheltenham, Manchester, or other non-local registered office address inconsistent with a single practice
- Booking URLs hosted on corporate dental SaaS platforms (hsone.app, dentalnode.com) — these are strong chain indicators
- More than 5 locations listed with national coverage
- Copyright notice belonging to a group entity rather than the practice name

Signals that indicate QUALIFIED:
- Named dentist/owner visible on homepage or about page (e.g. "Dr Smith established this practice in...")
- Family-run or "established by" language
- Single location
- Contact email address present directly on the page
- Independent booking system or no booking system

Return ONLY valid JSON. No preamble, no markdown fences.
PROMPT;

        $userMessage = "Website URL: {$url}\n\nHTML content:\n\n{$html}";

        try {
            $response = $this->openRouter->complete($systemPrompt, $userMessage);
            $decoded = json_decode($response['content'], true);

            if (json_last_error() !== JSON_ERROR_NONE || ! isset($decoded['status'])) {
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
}
