<?php

namespace Bastihilger\SimpleStatamicTranslations\Fieldtypes;

use Bastihilger\SimpleStatamicTranslations\Translations;
use Illuminate\Support\Str;
use Statamic\Fields\Fieldtype;

/**
 * The other languages of a plain field that has to stay a string, like the title or the slug:
 * `{type: translation, field: title}`. Stored as {"en": "…"}; the form shows it only while
 * another language than the default one is selected. On the frontend the translated field
 * itself (`{{ title }}`, `{{ slug }}`, the URL) returns the current language's value.
 */
class Translation extends Fieldtype
{
    protected static $handle = 'translation';

    protected $icon = 'translate';

    protected $categories = ['text'];

    public static function title()
    {
        return __('Translation');
    }

    protected function configFieldItems(): array
    {
        return [
            'field' => [
                'display' => __('Field'),
                'instructions' => __('The handle of the field this translates, e.g. `title` or `slug`.'),
                'type' => 'text',
                'default' => 'title',
                'width' => 50,
            ],
        ];
    }

    public function translates(): string
    {
        return $this->config('field', 'title');
    }

    public function preProcess($value)
    {
        $value = Translations::normalize($value);

        return collect(Translations::others())->mapWithKeys(fn ($language) => [$language => $value[$language] ?? ''])->all();
    }

    public function process($value)
    {
        $value = collect(is_array($value) ? $value : [])
            ->only(Translations::others())
            ->map(fn ($text, $language) => $this->translates() === 'slug' && filled($text) ? Str::slug($text, '-', $language) : $text)
            ->filter(fn ($text) => filled($text))
            ->all();

        return $value ?: null;
    }

    public function augment($value)
    {
        return $this->in($value, Translations::current());
    }

    public function preProcessIndex($value)
    {
        return collect(Translations::normalize($value))->filter()->map(fn ($text, $language) => "$language: $text")->implode(' · ');
    }

    /** The value in a language, null where there's none. */
    public function in(mixed $value, string $language): ?string
    {
        $text = Translations::normalize($value)[$language] ?? null;

        return filled($text) ? (string) $text : null;
    }
}
