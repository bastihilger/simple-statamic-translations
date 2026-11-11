<?php

namespace Kraenkvisuell\SimpleStatamicTranslations;

use Statamic\Facades\Blink;

/**
 * The configured languages and the one the current request renders in.
 */
class Translations
{
    protected static ?string $current = null;

    /** @return array<string, string> code => name */
    public static function languages(): array
    {
        return config('simple-statamic-translations.languages') ?: ['de' => 'Deutsch', 'en' => 'English'];
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(static::languages());
    }

    public static function default(): string
    {
        $default = config('simple-statamic-translations.default');

        return $default && static::exists($default) ? $default : array_key_first(static::languages());
    }

    /** @return list<string> every language except the default one */
    public static function others(): array
    {
        return array_values(array_diff(static::codes(), [static::default()]));
    }

    public static function exists(?string $language): bool
    {
        return $language !== null && array_key_exists($language, static::languages());
    }

    public static function name(string $language): string
    {
        return static::languages()[$language] ?? $language;
    }

    public static function current(): string
    {
        return static::$current ?? static::default();
    }

    public static function isDefault(?string $language = null): bool
    {
        return ($language ?? static::current()) === static::default();
    }

    public static function routeParameter(): string
    {
        return config('simple-statamic-translations.route_parameter', 'language');
    }

    public static function fallback(): bool
    {
        return (bool) config('simple-statamic-translations.fallback', true);
    }

    /** The language of the current request (sets the app locale too, see the config). */
    public static function activate(?string $language): void
    {
        static::setCurrent($language);

        $locale = config('simple-statamic-translations.app_locale', true);

        if ($locale !== false) {
            app()->setLocale(is_array($locale) ? ($locale[static::current()] ?? static::current()) : static::current());
        }
    }

    public static function setCurrent(?string $language): void
    {
        $language = static::exists($language) ? $language : null;

        if ($language === static::$current) {
            return;
        }

        static::$current = $language;

        // entry URIs are cached per request (Routing\UriCache caches only the default language's)
        Blink::store('entry-uris')->flush();
    }

    /**
     * Runs the callback with another current language (e.g. to build an entry's URL in it).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function in(string $language, callable $callback): mixed
    {
        $previous = static::$current;
        static::setCurrent($language);

        try {
            return $callback();
        } finally {
            static::setCurrent($previous);
        }
    }

    /** Does a route (string or per site) contain the language parameter? */
    public static function routeHasLanguage(mixed $route): bool
    {
        $parameter = preg_quote(static::routeParameter(), '/');

        foreach ((array) $route as $string) {
            if (is_string($string) && preg_match('/\{\{?\s*'.$parameter.'\s*\}?\}/', $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A stored value of a translated field ({"de": …, "en": …}). Values saved before the field
     * was translated (no language keys) count as the default language's.
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        if (array_intersect_key($value, static::languages())) {
            return array_intersect_key($value, static::languages());
        }

        return [static::default() => $value];
    }
}
