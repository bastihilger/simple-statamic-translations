<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Tags;

use Kraenkvisuell\SimpleStatamicTranslations\Translations as Languages;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\URL;
use Statamic\Tags\Tags;

/**
 * {{ translations }}…{{ /translations }}  every language: code, name, current, url (the current
 *                                         page in that language; for alternates and switches)
 * {{ translations:current }}              the current language's code
 * {{ translations:name }}                 the current language's name
 * {{ translations:default }}              the default language's code
 */
class Translations extends Tags
{
    public function index()
    {
        $entry = $this->entry();

        return collect(Languages::languages())->map(fn ($name, $code) => [
            'code' => $code,
            'language' => $code,
            'name' => $name,
            'current' => $code === Languages::current(),
            'default' => $code === Languages::default(),
            'url' => $entry ? $this->entryUrl($entry, $code) : $this->swapUrl($code),
        ])->values()->all();
    }

    public function current(): string
    {
        return Languages::current();
    }

    public function name(): string
    {
        return Languages::name(Languages::current());
    }

    public function default(): string
    {
        return Languages::default();
    }

    protected function entry(): ?Entry
    {
        $id = $this->params->get('id') ?? $this->context->value('id');

        return is_string($id) ? Entries::find($id) : null;
    }

    protected function entryUrl(Entry $entry, string $language): ?string
    {
        if (! Languages::routeHasLanguage($entry->route())) {
            return $this->swapUrl($language);
        }

        return Languages::in($language, fn () => $entry->url());
    }

    /** The current URL with its language segment replaced (or the language's home). */
    protected function swapUrl(string $language): string
    {
        $segments = explode('/', trim(request()->path(), '/'));

        if (Languages::exists($segments[0] ?? null)) {
            $segments[0] = $language;

            return URL::tidy('/'.implode('/', $segments), withTrailingSlash: false);
        }

        return '/'.$language;
    }
}
