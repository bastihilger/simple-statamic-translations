<?php

namespace Bastihilger\SimpleStatamicTranslations\Console;

use Bastihilger\SimpleStatamicTranslations\Routing\TranslatedUris;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Collection;

class RefreshUris extends Command
{
    use RunsInPlease;

    protected $signature = 'translations:refresh-uris {collection?* : Only these collections}';

    protected $description = 'Rebuild the URIs of entries in the other languages (after changing a route or importing) and remove those of deleted entries';

    public function handle(): int
    {
        $collections = $this->argument('collection')
            ? collect($this->argument('collection'))->map(fn ($handle) => Collection::findByHandle($handle))->filter()
            : Collection::all();

        foreach ($collections as $collection) {
            $count = TranslatedUris::refreshCollection($collection);
            $this->line(sprintf('%-30s %d URIs', $collection->handle(), $count));
        }

        if ($pruned = TranslatedUris::prune()) {
            $this->line("Removed $pruned URIs of deleted entries");
        }

        return self::SUCCESS;
    }
}
