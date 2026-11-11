<?php

namespace Kraenkvisuell\SimpleStatamicTranslations\Routing;

use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Data\DataRepository as StatamicDataRepository;
use Statamic\Facades\Site;
use Statamic\Facades\URL;

/**
 * Statamic's lookup of the requested URL, plus the URIs of entries in the other languages
 * (Routing\TranslatedUris): `/en/news/english-slug` finds its entry and renders it in English.
 */
class DataRepository extends StatamicDataRepository
{
    public static function from(StatamicDataRepository $repository): static
    {
        $instance = new static;
        $instance->repositories = $repository->repositories;

        return $instance;
    }

    public function findByRequestUrl($url)
    {
        if ($data = parent::findByRequestUrl($url)) {
            return $data;
        }

        if (! $site = Site::findByUrl($url)) {
            return null;
        }

        $uri = URL::tidy(URL::removeQueryAndFragment($site->relativePath($url)), withTrailingSlash: false);

        if (! $row = TranslatedUris::find($uri, $site->handle())) {
            return null;
        }

        Translations::activate($row->language);

        return TranslatedUris::entry($row);
    }
}
