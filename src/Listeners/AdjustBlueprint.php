<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Listeners;

use Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes\LanguageSwitch;
use Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes\Translated;
use Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes\TranslatedValue;
use Kraenkvisuell\SimpleStatamicTranslations\Fieldtypes\Translation;
use Statamic\Fields\Blueprint;
use Statamic\Statamic;

/**
 * Blueprints of entries, terms and global variables:
 *
 * - in the CP, every tab with translated fields gets the language switch on top, and Translation
 *   fields show only while another language than the default one is selected (they're always
 *   saved, also while hidden),
 * - outside the CP, fields with a Translation field (`title`, `slug`) return the current
 *   language's translation (Fieldtypes\TranslatedValue; it decides when the value is augmented,
 *   blueprints are cached per request).
 */
class AdjustBlueprint
{
    public function handle($event): void
    {
        $blueprint = $event->blueprint ?? null;

        if (! $blueprint instanceof Blueprint) {
            return;
        }

        Statamic::isCpRoute() ? $this->forForm($blueprint) : $this->forFrontend($blueprint);
    }

    protected function forForm(Blueprint $blueprint): void
    {
        foreach ($blueprint->tabs() as $tab) {
            $fields = $tab->fields()->all();

            if (! $fields->contains(fn ($field) => $this->isTranslated($field->config()))) {
                continue;
            }

            $blueprint->ensureFieldInTab('translations_language_'.$tab->handle(), [
                'type' => LanguageSwitch::handle(),
                'visibility' => 'computed',
                'hide_display' => true,
                'listable' => false,
            ], $tab->handle(), true);

            $fields->filter(fn ($field) => $field->type() === Translation::handle())->each(function ($field) use ($blueprint) {
                $blueprint->ensureFieldHasConfig($field->handle(), [
                    'if' => 'custom simpleTranslationsOtherLanguage',
                    'always_save' => true,
                ]);
            });
        }
    }

    protected function forFrontend(Blueprint $blueprint): void
    {
        foreach ($blueprint->fields()->all() as $field) {
            if ($field->type() !== Translation::handle()) {
                continue;
            }

            $translated = $field->get('field', 'title');

            if ($blueprint->hasField($translated) && $blueprint->field($translated)->type() !== TranslatedValue::handle()) {
                $blueprint->ensureFieldHasConfig($translated, [
                    'type' => TranslatedValue::handle(),
                    'original_type' => $blueprint->field($translated)->type(),
                    'translation' => $field->handle(),
                ]);
            }
        }
    }

    /** A translated field or Translation field, also nested (replicator sets, grids, groups). */
    protected function isTranslated(array $config): bool
    {
        if (in_array($config['type'] ?? null, [Translated::handle(), Translation::handle()], true)) {
            return true;
        }

        foreach ($config as $value) {
            if (is_array($value) && $this->isTranslated($value)) {
                return true;
            }
        }

        return false;
    }
}
