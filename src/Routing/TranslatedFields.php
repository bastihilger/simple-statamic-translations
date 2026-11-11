<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes\Translation;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;

/**
 * Finds the Translation fields of an entry or term (`{type: translation, field: slug}`).
 */
class TranslatedFields
{
    /** @return array<string, string> translated field handle => Translation field handle */
    public static function of($content): array
    {
        $blueprint = method_exists($content, 'blueprint') ? $content->blueprint() : null;

        if (! $blueprint) {
            return [];
        }

        return $blueprint->fields()->all()
            ->filter(fn ($field) => $field->type() === Translation::handle())
            ->mapWithKeys(fn ($field) => [$field->get('field', 'title') => $field->handle()])
            ->all();
    }

    /** The current (or given) language's translation of a field, null where there's none. */
    public static function value($content, string $field, ?string $language = null): ?string
    {
        $handle = static::of($content)[$field] ?? null;

        if (! $handle) {
            return null;
        }

        $text = Translations::normalize($content->value($handle))[$language ?? Translations::current()] ?? null;

        return filled($text) ? (string) $text : null;
    }
}
