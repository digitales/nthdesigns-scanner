<?php

namespace App\Services\Reports;

use App\Models\Prospect;

class CmsLabelResolver
{
    /**
     * CMS/platform summary for operator UI. Null when prospect has no website.
     *
     * @return array<string, mixed>|null
     */
    public function forProspect(Prospect $prospect): ?array
    {
        if (empty($prospect->website_url)) {
            return null;
        }

        $stored = $prospect->cms_detection;

        if (! is_array($stored) || $stored === []) {
            return [
                'platform' => null,
                'version' => null,
                'label' => null,
                'badge' => null,
                'confidence' => null,
                'signals' => [],
                'detected_at' => null,
                'pending' => true,
            ];
        }

        $platform = (string) ($stored['platform'] ?? 'unknown');

        return [
            'platform' => $platform,
            'version' => $stored['version'] ?? null,
            'label' => $this->label($platform, $stored['version'] ?? null),
            'badge' => $this->badge($platform),
            'confidence' => $stored['confidence'] ?? 'low',
            'signals' => $stored['signals'] ?? [],
            'detected_at' => $stored['detected_at'] ?? null,
            'pending' => false,
        ];
    }

    private function label(string $platform, mixed $version): string
    {
        $names = [
            'wordpress' => 'WordPress',
            'shopify' => 'Shopify',
            'wix' => 'Wix',
            'squarespace' => 'Squarespace',
            'webflow' => 'Webflow',
            'joomla' => 'Joomla',
            'drupal' => 'Drupal',
            'craft' => 'Craft CMS',
            'unknown' => 'Unknown',
        ];

        $name = $names[$platform] ?? ucfirst($platform);

        if ($version && $platform !== 'unknown') {
            $majorMinor = preg_match('/^(\d+\.\d+)/', (string) $version, $matches)
                ? $matches[1]
                : (string) $version;

            return $name.' '.$majorMinor;
        }

        return $name;
    }

    private function badge(string $platform): string
    {
        return match ($platform) {
            'wordpress' => 'WP',
            'shopify' => 'Shopify',
            'wix' => 'Wix',
            'squarespace' => 'SqSp',
            'webflow' => 'Webflow',
            'joomla' => 'Joomla',
            'drupal' => 'Drupal',
            'craft' => 'Craft',
            default => '?',
        };
    }
}
