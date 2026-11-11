<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Spatie\Blink\Blink;
use Statamic\Support\Blink as StatamicBlink;

/**
 * Statamic's per-request caches of URIs (`Blink::store('entry-uris')`, `'structure-uris'` for
 * tree pages). Flat-file sites fill the entry cache with the stored URI – the default language's –
 * whenever an entry is loaded, and `$entry->uri()` / `url()` return what's in them. In another
 * language they cache nothing, so every URI is built for the current language.
 */
class UriCache extends Blink
{
    public const STORES = ['entry-uris', 'structure-uris'];

    /** Puts the cache in place of Statamic's URI stores (keeping what they hold). */
    public static function install(StatamicBlink $blink): void
    {
        (function () {
            foreach (UriCache::STORES as $name) {
                $current = $this->stores[$name] ?? null;

                if (! $current instanceof UriCache) {
                    $this->stores[$name] = new UriCache($current?->all() ?? []);
                }
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
