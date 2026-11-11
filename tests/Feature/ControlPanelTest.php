<?php

namespace Tests\Feature;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Tests\TestCase;

class ControlPanelTest extends TestCase
{
    protected function user()
    {
        $user = User::make()->email('editor@example.com')->makeSuper();
        $user->save();

        return $user;
    }

    public function test_tabs_with_translated_fields_get_the_language_switch(): void
    {
        $entry = $this->news();

        $response = $this->actingAs($this->user())->get($entry->editUrl())->assertOk();
        $blueprint = $response->viewData('page')['props']['blueprint'] ?? null;
        $fields = collect($blueprint['tabs'])->mapWithKeys(fn ($tab) => [
            $tab['handle'] => collect($tab['sections'])->flatMap(fn ($section) => collect($section['fields'])->pluck('type', 'handle'))->all(),
        ]);

        // on top of both tabs (main: translated field + title translation, sidebar: slug translation)
        $this->assertSame(['translations_language_main' => 'translations_language_switch'], array_slice($fields['main'], 0, 1));
        $this->assertSame(['translations_language_sidebar' => 'translations_language_switch'], array_slice($fields['sidebar'], 0, 1));

        $titleTranslation = collect($blueprint['tabs'][0]['sections'][0]['fields'])->firstWhere('handle', 'title_translation');
        $this->assertSame('custom simpleTranslationsOtherLanguage', $titleTranslation['if']);
        $this->assertTrue($titleTranslation['always_save']);
    }

    public function test_saving_the_form_stores_every_language(): void
    {
        $entry = $this->news();

        $this->actingAs($this->user())->patchJson($entry->updateUrl(), [
            'title' => 'Nachricht neu',
            'slug' => 'nachricht',
            'title_translation' => ['en' => 'News item new'],
            'slug_translation' => ['en' => 'News Item New'],
            'content' => [
                'de' => ['headline' => 'Neue Überschrift', 'body' => 'Text', 'featured' => false],
                'en' => ['headline' => 'New headline', 'body' => '', 'featured' => true],
            ],
            'translations_language_main' => null,
            'translations_language_sidebar' => null,
            'published' => true,
            '_localized' => [],
        ])->assertOk();

        $entry = Entry::find($entry->id());

        $this->assertSame([
            'de' => ['headline' => 'Neue Überschrift', 'body' => 'Text', 'featured' => false],
            'en' => ['headline' => 'New headline', 'featured' => true],
        ], $entry->get('content'));
        $this->assertSame(['en' => 'News item new'], $entry->get('title_translation'));
        $this->assertSame(['en' => 'news-item-new'], $entry->get('slug_translation'));
        $this->assertFalse($entry->data()->has('translations_language_main'));
        $this->assertFalse($entry->data()->has('translations_language_sidebar'));

        $this->get('/en/news/news-item-new')->assertOk()->assertSee('title=News item new|slug=news-item-new|headline=New headline', false);
    }

    public function test_the_english_headline_is_optional(): void
    {
        $entry = $this->news();

        $this->actingAs($this->user())->patchJson($entry->updateUrl(), [
            'title' => 'Nachricht', 'slug' => 'nachricht', 'published' => true, '_localized' => [],
            'content' => ['de' => ['headline' => ''], 'en' => ['headline' => 'Only English']],
        ])->assertUnprocessable()->assertJsonValidationErrors(['content.de.headline'])->assertJsonMissingValidationErrors(['content.en.headline']);
    }

    public function test_global_variables_have_translated_fields_too(): void
    {
        $set = GlobalSet::make('settings')->title('Settings');
        $set->save();
        $set->makeLocalization('default')->data(['texts' => ['de' => ['footer' => 'Fuß'], 'en' => ['footer' => 'Foot']]])->save();

        $this->actingAs($this->user())->get(cp_route('globals.variables.edit', 'settings'))->assertOk()->assertSee('translations_language_switch');

        $this->assertSame('Fuß', (string) GlobalSet::findByHandle('settings')->inDefaultSite()->augmentedValue('texts')->value()['footer']);
        $this->get('/en/news')->assertNotFound();   // sets the language for the request …
        Translations::setCurrent('en');
        $this->assertSame('Foot', (string) GlobalSet::findByHandle('settings')->inDefaultSite()->augmentedValue('texts')->value()['footer']);
    }
}
