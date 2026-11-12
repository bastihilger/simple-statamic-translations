<?php

namespace Tests\Feature;

use Bastihilger\SimpleStatamicTranslations\Routing\TranslatedUris;
use Bastihilger\SimpleStatamicTranslations\Translations;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Statamic\Console\Commands\StaticWarm;
use Statamic\Facades\Collection;
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
            ->assertSee('title=Nachricht|slug=nachricht|subtitle=|headline=Überschrift|body=Deutscher Text|current=de', false)
            ->assertSee('links=de:/de/news/nachricht*;en:/en/news/news-item;', false);

        $this->get('/en/news/news-item')->assertOk()
            ->assertSee('title=News item|slug=news-item|subtitle=|headline=Headline|body=Deutscher Text|current=en', false)
            ->assertSee('links=de:/de/news/nachricht;en:/en/news/news-item*;', false);

        // the app locale follows the language (__(), dates), also after Statamic's Localize middleware
        $this->get('/de/news/nachricht')->assertSee('greeting=Hallo');
        $this->get('/en/news/news-item')->assertSee('greeting=Hello');

        $this->get('/fr/news/nachricht')->assertNotFound();
        $this->get('/en/news/gibt-es-nicht')->assertNotFound();
    }

    public function test_a_slug_of_another_language_redirects_to_the_requested_language(): void
    {
        $this->news();

        $this->get('/en/news/nachricht?x=1')->assertStatus(301)->assertRedirect('/en/news/news-item?x=1');
        $this->get('/de/news/news-item')->assertStatus(301)->assertRedirect('/de/news/nachricht');

        config(['simple-statamic-translations.redirect_other_languages' => false]);
        $this->get('/en/news/nachricht')->assertNotFound();
    }

    public function test_a_new_entrys_translated_title_gets_a_translated_slug(): void
    {
        $entry = $this->news();
        $new = Entry::make()->collection('news')->slug('grosse-neuigkeit')->data([
            'title' => 'Große Neuigkeit',
            'title_translation' => ['en' => 'Big News Today'],
            'content' => ['de' => ['headline' => 'X']],
        ]);
        $new->save();

        $this->assertSame(['en' => 'big-news-today'], Entry::find($new->id())->get('slug_translation'));
        $this->get('/en/news/big-news-today')->assertOk()->assertSee('title=Big News Today', false);

        // an existing entry keeps its URL when its title is translated later
        $entry->set('slug_translation', null)->set('title_translation', ['en' => 'Renamed'])->save();
        $this->assertNull(Entry::find($entry->id())->get('slug_translation'));
        $this->get('/en/news/nachricht')->assertOk()->assertSee('title=Renamed', false);
    }

    public function test_structured_collections_use_the_translated_slug_too(): void
    {
        $collection = Collection::make('docs')->routes('/{language}/docs/{slug}')->template('news')->layout('layout')
            ->structureContents(['max_depth' => 1]);
        $collection->save();

        $entry = Entry::make()->collection('docs')->slug('anleitung')->data([
            'title' => 'Anleitung',
            'title_translation' => ['en' => 'Guide'],
            'content' => ['de' => ['headline' => 'Los'], 'en' => ['headline' => 'Go']],
        ]);
        $entry->save();
        $collection->structure()->in('default')->tree([['entry' => $entry->id()]])->save();

        $this->assertSame('/de/docs/anleitung', Entry::find($entry->id())->url());
        $this->assertSame('/en/docs/guide', Translations::in('en', fn () => Entry::find($entry->id())->url()));
        $this->get('/en/docs/guide')->assertOk()->assertSee('title=Guide|slug=guide|subtitle=|headline=Go', false);
    }

    public function test_static_warm_gets_the_other_languages_urls(): void
    {
        $entry = $this->news();
        $draft = Entry::make()->collection('news')->slug('entwurf')->published(false)->data(['title' => 'Entwurf', 'slug_translation' => ['en' => 'draft']]);
        $draft->save();

        $this->assertSame(['http://localhost/en/news/news-item'], TranslatedUris::urls());

        $hooks = app('statamic.hooks')[StaticWarm::class]['additional'] ?? collect();
        $this->assertCount(1, $hooks);
        $uris = (new Pipeline)->send(collect(['http://localhost/de/news/nachricht']))->through($hooks->all())->thenReturn();
        $this->assertSame(['http://localhost/de/news/nachricht', 'http://localhost/en/news/news-item'], $uris->all());
    }
}
