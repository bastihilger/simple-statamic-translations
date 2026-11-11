<?php

namespace Bastihilger\SimpleStatamicTranslations\Http\Middleware;

/**
 * DetectLanguage for the `web` group (the site's and addons' own routes). A class of its own:
 * Laravel runs a middleware only once per route, and on Statamic's routes DetectLanguage must
 * run (again) after Statamic's Localize, which sets the site's locale.
 */
class DetectLanguageOnRoutes extends DetectLanguage {}
