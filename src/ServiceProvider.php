<?php

namespace Bastihilger\SimpleStatamicTranslations;

use Bastihilger\SimpleStatamicTranslations\Listeners\AdjustBlueprint;
use Bastihilger\SimpleStatamicTranslations\Listeners\UpdateTranslatedUris;
use Illuminate\Support\Facades\Event;
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
            Http\Middleware\RedirectOtherLanguageUrls::class,
        ],
        // the site's and other addons' own frontend routes (`/{language}/search`)
        'web' => [
            Http\Middleware\DetectLanguageOnRoutes::class,
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
        // the language is static state: a new app (test, worker) starts in the default language
        Translations::setCurrent(null);

        $this->publishes([
            __DIR__.'/../config/simple-statamic-translations.php' => config_path('simple-statamic-translations.php'),
        ], 'simple-statamic-translations-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        // after Statamic's own binding
        $this->app->bind(UrlBuilderContract::class, Routing\UrlBuilder::class);

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
