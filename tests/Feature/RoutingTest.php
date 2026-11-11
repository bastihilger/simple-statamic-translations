<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Facades\Entry;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    public function test_the_language_parameter_and_the_translated_slug_build_the_url(): void
    {
        $entry = $this->news();

        $this->assertSame('/de/news/nachricht', $entry->url());
        $this->assertSame('/en/news/news-item', Translations::in('en', fn () => Entry::find($entry->id())->url()));
        $this->assertSame('/de/news/nachricht', $entry->url());   // back in the default language
    }

    public function test_the_other_languages_uris_are_stored_on_save(): void
    {
        $entry = $this->news();

        $this->assertSame(
            [['language' => 'en', 'uri' => '/en/news/news-item']],
            DB::table('translated_uris')->where('entry_id', $entry->id())->get(['language', 'uri'])->map(fn ($row) => (array) $row)->all(),
        );

        $entry->delete();
        $this->assertSame(0, DB::table('translated_uris')->count());
    }

    public function test_each_language_renders_at_its_url(): void
    {
        $this->news();

        $this->get('/de/news/nachricht')->assertOk()
            ->assertSee('title=Nachricht|slug=nachricht|headline=Überschrift|body=Deutscher Text|current=de', false)
            ->assertSee('links=de:/de/news/nachricht*;en:/en/news/news-item;', false);

        $this->get('/en/news/news-item')->assertOk()
            ->assertSee('title=News item|slug=news-item|headline=Headline|body=Deutscher Text|current=en', false)
            ->assertSee('links=de:/de/news/nachricht;en:/en/news/news-item*;', false);

        // the app locale follows the language (__(), dates)
        $this->get('/de/news/nachricht')->assertSee('greeting=Hallo');
        $this->get('/en/news/news-item')->assertSee('greeting=Hello');

        $this->get('/en/news/nachricht')->assertNotFound();
        $this->get('/fr/news/nachricht')->assertNotFound();
    }

    public function test_a_translated_title_gets_a_translated_slug(): void
    {
        $entry = $this->news();
        $entry->set('slug_translation', null)->set('title_translation', ['en' => 'Big News Today'])->save();

        $this->assertSame(['en' => 'big-news-today'], Entry::find($entry->id())->get('slug_translation'));
        $this->get('/en/news/big-news-today')->assertOk()->assertSee('title=Big News Today', false);
    }
}
