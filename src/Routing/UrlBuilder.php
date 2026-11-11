<?php

namespace Bastihilger\SimpleStatamicTranslations\Routing;

use Bastihilger\SimpleStatamicTranslations\Translations;
use Statamic\Routing\UrlBuilder as StatamicUrlBuilder;
use Statamic\Structures\Page;

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

            // structured collections build the URI of the tree page, which has the entry
            $entry = $this->content instanceof Page ? $this->content->entry() : $this->content;

            if (! Translations::isDefault() && $ownSlug && $entry && $slug = TranslatedFields::value($entry, 'slug')) {
                $extra['slug'] = $slug;
            }

            $this->merged = array_merge($this->merged, $extra);
        }

        return parent::build($route);
    }
}
