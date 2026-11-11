<?php

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Kraenkvisuell\SimpleStatamicTranslations\Translations;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\TestCase;

class TranslatedFieldTest extends TestCase
{
    protected function field(): Field
    {
        return Blueprint::find('collections.news.news')->field('content');
    }

    public function test_it_stores_every_language_as_an_object(): void
    {
        $processed = $this->field()->setValue([
            'de' => ['headline' => 'Überschrift', 'body' => '', 'featured' => false],
            'en' => ['headline' => '', 'body' => null, 'featured' => null],
        ])->process()->value();

        // empty values are dropped like in a group, an empty language completely
        $this->assertSame(['de' => ['headline' => 'Überschrift', 'featured' => false]], $processed);
    }

    public function test_the_form_gets_every_language(): void
    {
        $preProcessed = $this->field()->setValue(['de' => ['headline' => 'Überschrift']])->preProcess()->value();

        $this->assertSame(['de', 'en'], array_keys($preProcessed));
        $this->assertSame('Überschrift', $preProcessed['de']['headline']);
        $this->assertArrayHasKey('headline', $preProcessed['en']);
    }

    public function test_values_saved_before_the_field_was_translated_count_as_the_default_language(): void
    {
        $this->assertSame(['de' => ['headline' => 'Alt']], $this->field()->setValue(['headline' => 'Alt'])->process()->value());
    }

    public function test_templates_get_the_current_language_and_fall_back_to_the_default_one(): void
    {
        $entry = $this->news();

        $this->assertSame('Überschrift', (string) $entry->augmentedValue('content')->value()['headline']);

        Translations::setCurrent('en');
        $content = Entry::find($entry->id())->augmentedValue('content')->value();

        $this->assertSame('Headline', (string) $content['headline']);
        $this->assertSame('Deutscher Text', (string) $content['body']);   // empty in English
        $this->assertTrue($content->raw('featured'));

        config(['simple-statamic-translations.fallback' => false]);
        $this->assertSame('', (string) Entry::find($entry->id())->augmentedValue('content')->value()['body']);
    }

    public function test_only_the_default_language_is_required(): void
    {
        $fields = Blueprint::find('collections.news.news')->fields();

        $fields->addValues(['title' => 'X', 'slug' => 'x', 'content' => ['de' => ['headline' => 'Da'], 'en' => ['headline' => '']]])->validate();

        $this->expectException(ValidationException::class);
        $fields->addValues(['title' => 'X', 'slug' => 'x', 'content' => ['de' => ['headline' => ''], 'en' => ['headline' => 'There']]])->validate();
    }
}
