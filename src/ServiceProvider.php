<?php

namespace Kraenkvisuell\SimpleStatamicTranslations;

use Illuminate\Support\Facades\Event;
use Kraenkvisuell\SimpleStatamicTranslations\Listeners\AdjustBlueprint;
use Kraenkvisuell\SimpleStatamicTranslations\Listeners\UpdateTranslatedUris;
use Statamic\Contracts\Data\DataRepository as DataRepositoryContract;
use Statamic\Contracts\Routing\UrlBuilder as UrlBuilderContract;
use Statamic\Data\DataRepository as StatamicDataRepository;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;
use Statamic\Support\Blink;

class ServiceProvider extends AddonServiceProvider
{
    protected $fieldtypes = [
        Fieldtypes\Translated::class,
        Fieldtypes\Translation::class,
        Fieldtypes\LanguageSwitch::class,
        Fieldtypes\TranslatedValue::class,
    ];

    protected $tags = [
        Tags\Translations::class,
    ];

    protected $commands = [
        Console\RefreshUris::class,
    ];

    protected $middlewareGroups = [
        'statamic.web' => [
            Http\Middleware\DetectLanguage::class,
        ],
    ];

    // plain JS on top of Statamic's own components, no build step
    protected $scripts = [
        __DIR__.'/../resources/js/cp.js',
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/simple-statamic-translations.php', 'simple-statamic-translations');

        // finds entries by their URI in the other languages; a replaced repository is left alone
        $this->app->extend(DataRepositoryContract::class, fn ($repository) => get_class($repository) === StatamicDataRepository::class
            ? Routing\DataRepository::from($repository)
            : $repository);
    }

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../config/simple-statamic-translations.php' => config_path('simple-statamic-translations.php'),
        ], 'simple-statamic-translations-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        // after Statamic's own binding
        $this->app->bind(UrlBuilderContract::class, Routing\UrlBuilder::class);
        $this->app->resolving(Blink::class, fn ($blink) => Routing\UriCache::install($blink));
        Routing\UriCache::install($this->app->make(Blink::class));

        foreach ([EntryBlueprintFound::class, TermBlueprintFound::class, GlobalVariablesBlueprintFound::class] as $event) {
            Event::listen($event, AdjustBlueprint::class);
        }

        Event::listen(EntrySaving::class, [UpdateTranslatedUris::class, 'saving']);
        Event::listen(EntrySaved::class, [UpdateTranslatedUris::class, 'saved']);
        Event::listen(EntryDeleted::class, [UpdateTranslatedUris::class, 'deleted']);
        Event::listen(CollectionTreeSaved::class, [UpdateTranslatedUris::class, 'treeSaved']);

        Statamic::provideToScript([
            'simpleStatamicTranslations' => [
                'languages' => Translations::languages(),
                'default' => Translations::default(),
            ],
        ]);
    }
}
