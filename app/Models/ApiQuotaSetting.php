<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiQuotaSetting extends Model
{
    protected $fillable = [
        'google_places_text_search_daily',
        'google_places_text_search_monthly',
        'google_places_place_details_daily',
        'google_places_place_details_monthly',
        'brave_web_search_daily',
        'brave_web_search_monthly',
    ];

    protected function casts(): array
    {
        return [
            'google_places_text_search_daily' => 'integer',
            'google_places_text_search_monthly' => 'integer',
            'google_places_place_details_daily' => 'integer',
            'google_places_place_details_monthly' => 'integer',
            'brave_web_search_daily' => 'integer',
            'brave_web_search_monthly' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
