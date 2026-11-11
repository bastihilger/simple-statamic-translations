<?php

return [

    /*
    | The languages content can be translated into: code => name. The codes are used in the stored
    | values ({"de": …, "en": …}), in URLs ({language} in a route) and in templates.
    */
    'languages' => [
        'de' => 'Deutsch',
        'en' => 'English',
    ],

    /*
    | The language the site falls back to and that Statamic's own title/slug fields hold. Null:
    | the first of `languages`.
    */
    'default' => null,

    /*
    | The route parameter that stands for the language, e.g. `/{language}/news/{slug}` in a
    | collection's route.
    */
    'route_parameter' => 'language',

    /*
    | Take the language from the first segment of the URL (`/en/…`), also on routes that aren't
    | entries (Route::statamic, listing pages).
    */
    'detect_from_url' => true,

    /*
    | Set the app locale (and with it __() and date formatting) to the current language. A string
    | per language maps it to a locale: ['de' => 'de_DE', 'en' => 'en_US'].
    */
    'app_locale' => true,

    /*
    | An empty value in a language shows the default language's value instead.
    */
    'fallback' => true,

    /*
    | A URL with another language's slug under a language's prefix (`/en/news/nachricht`) redirects
    | (301) to the entry's URL in that language (`/en/news/news-item`).
    */
    'redirect_other_languages' => true,

];
