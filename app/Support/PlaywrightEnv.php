<?php

namespace App\Support;

class PlaywrightEnv
{
    /**
     * Environment variables for Node audit/screenshot subprocesses.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function forProcess(array $extra = []): array
    {
        $env = $extra;

        $browsersPath = PlaywrightBrowsers::resolve();

        if ($browsersPath !== null && $browsersPath !== '') {
            $env['PLAYWRIGHT_BROWSERS_PATH'] = $browsersPath;
        }

        return $env;
    }
}
