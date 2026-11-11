<?php

namespace Bastihilger\SimpleStatamicTranslations\Listeners;

use Bastihilger\SimpleStatamicTranslations\Routing\TranslatedFields;
use Bastihilger\SimpleStatamicTranslations\Routing\TranslatedUris;
use Bastihilger\SimpleStatamicTranslations\Translations;
use Illuminate\Support\Str;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;

/**
 * Keeps the translated URIs (Routing\TranslatedUris) up to date, and gives a new entry's
 * translated title a translated slug where the slug's translation is empty (like Statamic does
 * for the slug while an entry is created; existing URLs don't change when a title is translated).
 */
class UpdateTranslatedUris
{
    public function saving(EntrySaving $event): void
    {
        $entry = $event->entry;

        if ($entry->id() && Entry::find($entry->id())) {
            return;
        }

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
