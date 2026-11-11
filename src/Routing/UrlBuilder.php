<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Routing\UrlBuilder as StatamicUrlBuilder;

/**
 * Fills the language parameter of a route (`/{language}/news/{slug}`) with the current language
 * and, in another language than the default one, uses the slug's Translation field.
 */
class UrlBuilder extends StatamicUrlBuilder
{
    public function build($route)
    {
        if (Translations::routeHasLanguage($route)) {
            $parameter = Translations::routeParameter();
            $extra = array_key_exists($parameter, $this->merged) ? [] : [$parameter => Translations::current()];

            // a structure merges the entry's own slug; the translated one replaces it
            $ownSlug = ! isset($this->merged['slug']) || $this->merged['slug'] === $this->content->slug();

            if (! Translations::isDefault() && $ownSlug && $slug = TranslatedFields::value($this->content, 'slug')) {
                $extra['slug'] = $slug;
            }

            $this->merged = array_merge($this->merged, $extra);
        }

        return parent::build($route);
    }
}
