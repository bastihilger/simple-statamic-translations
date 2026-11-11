<?php

namespace Bastihilger\SimpleStatamicTranslations\Listeners;

use Bastihilger\SimpleStatamicTranslations\Fieldtypes\LanguageSwitch;
use Bastihilger\SimpleStatamicTranslations\Fieldtypes\Translated;
use Bastihilger\SimpleStatamicTranslations\Fieldtypes\TranslatedValue;
use Bastihilger\SimpleStatamicTranslations\Fieldtypes\Translation;
use Statamic\Fields\Blueprint;
use Statamic\Statamic;

/**
 * Blueprints of entries, terms and global variables:
 *
 * - in the CP, every tab with translated fields (the sidebar too) gets the language switch on
 *   top; Translation
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
        $translatedTabs = $blueprint->tabs()->filter(
            fn ($tab) => $tab->fields()->all()->contains(fn ($field) => $this->isTranslated($field->config()))
        );

        if ($translatedTabs->isEmpty()) {
            return;
        }

        foreach ($translatedTabs as $tab) {
            $tab->fields()->all()->filter(fn ($field) => $field->type() === Translation::handle())->each(function ($field) use ($blueprint) {
                $blueprint->ensureFieldHasConfig($field->handle(), [
                    'if' => 'custom simpleTranslationsOtherLanguage',
                    'always_save' => true,
                ]);
            });
        }

        // on top of every tab with translated fields (the sidebar too: narrow forms show it as a tab)
        foreach ($translatedTabs->keys() as $tab) {
            $blueprint->ensureFieldInTab('translations_language_'.$tab, [
                'type' => LanguageSwitch::handle(),
                'visibility' => 'computed',
                'hide_display' => true,
                'listable' => false,
            ], $tab, true);
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
