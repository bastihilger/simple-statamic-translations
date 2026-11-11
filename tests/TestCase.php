<?php

namespace Tests;

use Bastihilger\SimpleStatamicTranslations\ServiceProvider;
use Bastihilger\SimpleStatamicTranslations\Translations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk, RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function setUp(): void
    {
        parent::setUp();

        Translations::setCurrent(null);
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('statamic.system.blueprints_path', __DIR__.'/__fixtures__/blueprints');
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
        $app['config']->set('statamic.editions.pro', false);
        $app['config']->set('app.locale', 'de');
        $app->useLangPath(__DIR__.'/__fixtures__/lang');
    }

    protected function news(): \Statamic\Contracts\Entries\Entry
    {
        Collection::make('news')->routes('/{language}/news/{slug}')->template('news')->layout('layout')->save();

        $entry = Entry::make()->collection('news')->slug('nachricht')->data([
            'title' => 'Nachricht',
            'title_translation' => ['en' => 'News item'],
            'slug_translation' => ['en' => 'news-item'],
            'content' => [
                'de' => ['headline' => 'Überschrift', 'body' => 'Deutscher Text', 'featured' => true],
                'en' => ['headline' => 'Headline'],
            ],
        ]);
        $entry->save();

        return $entry;
    }
}
