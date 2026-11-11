<?php

namespace Bastihilger\SimpleStatamicTranslations\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * The language switch on top of every form tab with translated fields (added by
 * Listeners\AdjustBlueprint, not selectable in blueprints). It only switches the form; it has
 * no value and is never saved (`visibility: computed`).
 */
class LanguageSwitch extends Fieldtype
{
    protected static $handle = 'translations_language_switch';

    protected $selectable = false;

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    public function process($value)
    {
        return null;
    }
}
