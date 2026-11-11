<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Listeners;

use Illuminate\Support\Str;
use Kraenkvisuell\SimpleStatamicTranslations\Routing\TranslatedFields;
use Kraenkvisuell\SimpleStatamicTranslations\Routing\TranslatedUris;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;

/**
 * Keeps the translated URIs (Routing\TranslatedUris) up to date, and gives a translated title a
 * translated slug where the slug's translation is empty (like Statamic does for the slug).
 */
class UpdateTranslatedUris
{
    public function saving(EntrySaving $event): void
    {
        $entry = $event->entry;
        $fields = TranslatedFields::of($entry);

        if (! isset($fields['slug'], $fields['title'])) {
            return;
        }

        $slugs = Translations::normalize($entry->get($fields['slug']));

        foreach (Translations::others() as $language) {
            if (blank($slugs[$language] ?? null) && $title = TranslatedFields::value($entry, 'title', $language)) {
                $slugs[$language] = Str::slug($title, '-', $language);
            }
        }

        $slugs ? $entry->set($fields['slug'], $slugs) : $entry->remove($fields['slug']);
    }

    public function saved(EntrySaved $event): void
    {
        TranslatedUris::refresh($event->entry);
    }

    public function deleted(EntryDeleted $event): void
    {
        TranslatedUris::forget($event->entry);
    }

    public function treeSaved(CollectionTreeSaved $event): void
    {
        TranslatedUris::refreshCollection($event->tree->collection());
    }
}
