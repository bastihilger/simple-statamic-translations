<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Spatie\Blink\Blink;
use Statamic\Support\Blink as StatamicBlink;

/**
 * Statamic's per-request cache of entry URIs (`Blink::store('entry-uris')`). Flat-file sites
 * fill it with the stored URI – the default language's – whenever an entry is loaded, and
 * `$entry->uri()` / `url()` return what's in it. In another language it caches nothing, so every
 * URI is built for the current language.
 */
class UriCache extends Blink
{
    /** Puts the cache in place of Statamic's `entry-uris` store (keeping what it holds). */
    public static function install(StatamicBlink $blink): void
    {
        (function () {
            $current = $this->stores['entry-uris'] ?? null;

            if (! $current instanceof UriCache) {
                $this->stores['entry-uris'] = new UriCache($current?->all() ?? []);
            }
        })->call($blink);
    }

    public function put($key, $value = null)
    {
        if (Translations::isDefault()) {
            return parent::put($key, $value);
        }

        return $this;
    }

    public function get(string $key, $default = null)
    {
        return Translations::isDefault() ? parent::get($key, $default) : $default;
    }

    public function has(string $key): bool
    {
        return Translations::isDefault() && parent::has($key);
    }
}
