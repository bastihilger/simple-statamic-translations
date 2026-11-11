<?php

namespace Bastihilger\SimpleStatamicTranslations\Http\Middleware;

use Bastihilger\SimpleStatamicTranslations\Translations;
use Closure;
use Illuminate\Http\Request;

/**
 * Frontend requests: the language is the URL's first segment where it's a configured language
 * (`/en/…`), otherwise the default one.
 */
class DetectLanguage
{
    public function handle(Request $request, Closure $next)
    {
        $segment = config('simple-statamic-translations.detect_from_url', true) ? $request->segment(1) : null;

        Translations::activate(Translations::exists($segment) ? $segment : Translations::default());

        return $next($request);
    }
}
