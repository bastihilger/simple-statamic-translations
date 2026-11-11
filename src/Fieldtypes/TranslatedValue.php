<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Fields\Fieldtype;
use Statamic\Fields\FieldtypeRepository;

/**
 * Stands in for a field that has a Translation field (`title`, `slug`) outside the CP
 * (Listeners\AdjustBlueprint): in another language than the default one it returns the
 * translation, otherwise (or where there's none) the field's own value, augmented by the field's
 * own fieldtype.
 */
class TranslatedValue extends Fieldtype
{
    protected static $handle = 'translations_value';

    protected $selectable = false;

    public function augment($value)
    {
        $translation = $this->field->parent()?->value($this->config('translation'));
        $translated = Translations::normalize($translation)[Translations::current()] ?? null;

        return $this->original()->augment(filled($translated) ? $translated : $value);
    }

    public function shallowAugment($value)
    {
        return $this->augment($value);
    }

    protected function original(): Fieldtype
    {
        return app(FieldtypeRepository::class)->find($this->config('original_type', 'text'))->setField($this->field);
    }
}
