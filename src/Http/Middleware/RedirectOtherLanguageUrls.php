<?php

namespace Bastihilger\SimpleStatamicTranslations\Http\Middleware;

use Bastihilger\SimpleStatamicTranslations\Routing\TranslatedUris;
use Bastihilger\SimpleStatamicTranslations\Translations;
use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Symfony\Component\HttpFoundation\Response;

/**
 * A 404 whose URL is an entry's URL in another language with the requested language's prefix
 * (`/en/news/nachricht`, the German slug under /en/) redirects to the entry's URL in the requested
 * language (`/en/news/news-item`). Old links often mix them up.
 */
class RedirectOtherLanguageUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! $request->isMethod('GET') || ! config('simple-statamic-translations.redirect_other_languages', true)) {
            return $response;
        }

        $site = Site::findByUrl($request->url());
        $segments = explode('/', trim($request->path(), '/'));
        $language = $segments[0] ?? null;

        if (! $site || ! Translations::exists($language)) {
            return $response;
        }

        foreach (Translations::codes() as $other) {
            if ($other === $language) {
                continue;
            }

            $uri = '/'.implode('/', [$other, ...array_slice($segments, 1)]);
            // stored URIs are the default language's
            $entry = Translations::isDefault($other)
                ? Translations::in($other, fn () => Entry::findByUri($uri, $site->handle()))
                : (($row = TranslatedUris::find($uri, $site->handle())) && $row->language === $other ? TranslatedUris::entry($row) : null);

            if (! $entry || ! $entry->published()) {
                continue;
            }

            $url = Translations::in($language, fn () => $entry->url());

            if ($url && '/'.trim($request->path(), '/') !== $url) {
                return redirect($url.($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
            }
        }

        return $response;
    }
}
