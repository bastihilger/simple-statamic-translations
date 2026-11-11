<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;

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
