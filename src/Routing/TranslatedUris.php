<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;

/**
 * The URIs of entries in the languages other than the default one (table `translated_uris`), so
 * a request for `/en/news/english-slug` finds its entry. The default language's URI is Statamic's
 * own. Only collections with the language parameter in their route have rows.
 */
class TranslatedUris
{
    protected const TABLE = 'translated_uris';

    /** @return object{site: string, language: string, uri: string, entry_id: string}|null */
    public static function find(string $uri, string $site): ?object
    {
        if (! static::ready()) {
            return null;
        }

        return DB::table(static::TABLE)->where('site', $site)->where('uri', $uri)->first();
    }

    /** The entry's URI in a language other than the default one, from the table. */
    public static function uri(string $entryId, string $language): ?string
    {
        return static::ready()
            ? DB::table(static::TABLE)->where('entry_id', $entryId)->where('language', $language)->value('uri')
            : null;
    }

    /** Rebuilds the entry's rows (structured collections: all entries, children depend on parents). */
    public static function refresh(Entry $entry): void
    {
        $collection = $entry->collection();

        if ($collection?->hasStructure()) {
            static::refreshCollection($collection);

            return;
        }

        static::write($entry);
    }

    public static function refreshCollection($collection): int
    {
        if (! Translations::routeHasLanguage($collection->routes()->all())) {
            static::ready() && DB::table(static::TABLE)->whereIn('entry_id', $collection->queryEntries()->pluck('id')->all())->delete();

            return 0;
        }

        $count = 0;
        $collection->queryEntries()->whereIn('site', Site::all()->keys()->all())->get()->each(function ($entry) use (&$count) {
            $count += static::write($entry);
        });

        return $count;
    }

    public static function forget(Entry $entry): void
    {
        static::ready() && DB::table(static::TABLE)->where('entry_id', $entry->id())->delete();
    }

    protected static function write(Entry $entry): int
    {
        if (! static::ready() || ! $entry->id()) {
            return 0;
        }

        $rows = [];

        if (Translations::routeHasLanguage($entry->route())) {
            $default = Translations::in(Translations::default(), fn () => $entry->uri());

            foreach (Translations::others() as $language) {
                $uri = Translations::in($language, fn () => $entry->uri());

                if ($uri && $uri !== $default) {
                    $rows[] = ['site' => $entry->locale(), 'language' => $language, 'uri' => $uri, 'entry_id' => $entry->id()];
                }
            }
        }

        DB::transaction(function () use ($entry, $rows) {
            DB::table(static::TABLE)->where('entry_id', $entry->id())->delete();
            $rows && DB::table(static::TABLE)->insert($rows);
        });

        return count($rows);
    }

    protected static function ready(): bool
    {
        return Blink::once('simple-statamic-translations-table', fn () => Schema::hasTable(static::TABLE));
    }

    public static function entry(object $row): ?Entry
    {
        return Entries::find($row->entry_id);
    }
}
