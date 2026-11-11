<?php

namespace Bastihilger\SimpleStatamicTranslations\Fieldtypes;

use Bastihilger\SimpleStatamicTranslations\Translations;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Fieldtypes\Group;

/**
 * Wraps one or more fields like a group, once per language. Stored as {"de": {…}, "en": {…}};
 * in templates it's the group of the current language, where an empty value falls back to the
 * default language's. The form shows one language at a time (the language switch on top of the
 * tab switches all translated fields of the form).
 *
 * A single field with the wrapper's own handle (`description` in `description`) is a translated
 * field: templates get that field's value directly (`{{ description }}`), and a plain value
 * stored before it was wrapped counts as the default language's.
 */
class Translated extends Group
{
    protected static $handle = 'translated';

    protected $icon = 'translate';

    public static function title()
    {
        return __('Translated');
    }

    public function preProcess($data)
    {
        $data = $this->data($data);

        return $this->eachLanguage(fn ($language) => parent::preProcess($data[$language] ?? []));
    }

    public function process($data)
    {
        $data = $this->data($data);

        $values = array_filter(
            $this->eachLanguage(fn ($language) => parent::process($data[$language] ?? [])),
            fn ($values) => $values !== [],
        );

        return $values ?: null;
    }

    public function preload()
    {
        $data = $this->data($this->field->value());

        return $this->eachLanguage(fn ($language) => $this->fields()
            ->addValues($data[$language] ?? $this->defaultGroupData())
            ->meta()
            ->toArray());
    }

    public function augment($value)
    {
        return $this->augmentValues($value, 'augment');
    }

    public function shallowAugment($value)
    {
        return $this->augmentValues($value, 'shallowAugment');
    }

    /** The values of one language (the current one), with the fallback applied. */
    public function valuesIn(mixed $value, ?string $language = null): array
    {
        $value = $this->data($value);
        $language ??= Translations::current();
        $default = Translations::default();

        if ($language === $default || ! Translations::fallback()) {
            return $value[$language] ?? [];
        }

        $values = $value[$default] ?? [];

        foreach ($value[$language] ?? [] as $handle => $translated) {
            if (! blank($translated)) {
                $values[$handle] = $translated;
            }
        }

        return $values;
    }

    protected function augmentValues(mixed $value, string $method): mixed
    {
        $values = $this->fields()->addValues($this->valuesIn($value))->{$method}()->values()->all();

        if ($handle = $this->singleField()) {
            $single = $values[$handle] ?? null;

            return $single instanceof Value ? $single->value() : $single;
        }

        return new Values($values);
    }

    /** The handle of the only field when it has the wrapper's handle (see the class). */
    public function singleField(): ?string
    {
        $fields = (array) $this->config('fields', []);

        if (count($fields) !== 1 || ! $this->field) {
            return null;
        }

        $handle = $fields[array_key_first($fields)]['handle'] ?? null;

        return $handle === $this->field->handle() ? $handle : null;
    }

    /** The stored value as {language: {handle: value}}. */
    protected function data(mixed $value): array
    {
        $single = $this->singleField();
        $isTranslated = is_array($value) && $value !== [] && ! array_is_list($value)
            && ! array_diff_key($value, Translations::languages());

        if ($single && ! $isTranslated && ! blank($value)) {
            return [Translations::default() => [$single => $value]];
        }

        return Translations::normalize($value);
    }

    /** Rules per language; only the default language is required (the others fall back to it). */
    public function extraRules(): array
    {
        $data = $this->data($this->field->value());
        $rules = [];

        foreach (Translations::codes() as $language) {
            $validator = $this->fields()
                ->addValues($data[$language] ?? [])
                ->validator()
                ->withContext(['prefix' => $this->field->validationContext('prefix')]);

            foreach ($this->addEntryValidationReplacements($this->field, $validator)->rules() as $handle => $fieldRules) {
                $rules[$this->field->handle().'.'.$language.'.'.$handle] = Translations::isDefault($language)
                    ? $fieldRules
                    : $this->withoutRequired($fieldRules);
            }
        }

        return $rules;
    }

    public function extraValidationAttributes(): array
    {
        $attributes = [];

        foreach (Translations::codes() as $language) {
            foreach ($this->fields()->validator()->attributes() as $handle => $attribute) {
                $attributes[$this->field->handle().'.'.$language.'.'.$handle] = $attribute.' ('.Translations::name($language).')';
            }
        }

        return $attributes;
    }

    public function preProcessValidatable($value)
    {
        $data = $this->data($value);

        return $this->eachLanguage(fn ($language) => parent::preProcessValidatable($data[$language] ?? []));
    }

    public function preProcessIndex($value)
    {
        $values = $this->valuesIn($value, Translations::default());

        if ($handle = $this->singleField()) {
            return $this->fields()->get($handle)?->setValue($values[$handle] ?? null)->preProcessIndex()->value();
        }

        return collect($values)->filter(fn ($value) => is_string($value) || is_numeric($value))->implode(' · ');
    }

    /** @return array<string, mixed> language => result */
    protected function eachLanguage(callable $callback): array
    {
        $result = [];

        foreach (Translations::codes() as $language) {
            $result[$language] = $callback($language);
        }

        return $result;
    }

    protected function withoutRequired(mixed $rules): array
    {
        $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

        return array_values(array_filter($rules, fn ($rule) => ! (is_string($rule) && str_starts_with($rule, 'required'))));
    }
}
