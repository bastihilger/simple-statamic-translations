# Simple Statamic Translations

Translate Statamic content without multi-site. Every entry, term and global set exists once; the
fields that need translating hold all languages:

- a **Translated** field wraps one or more fields like a Group and stores them once per language,
- one **language switch** on top of the form switches all translated fields of the form at once,
- **Translation** fields give `title` and `slug` other languages, used by `{{ title }}` and URLs,
- **`{language}`** in a collection's route gives every entry a URL per language
  (`/de/news/nachricht`, `/en/news/news-item`).

Use it when every page exists in every language. If languages need their own pages, menus or
publish states, use Statamic's multi-site instead.

## Installation

```bash
composer require bastihilger/simple-statamic-translations
php artisan migrate        # the table of translated URIs
php artisan vendor:publish --tag=simple-statamic-translations-config   # optional
```

`config/simple-statamic-translations.php`:

```php
'languages' => ['de' => 'Deutsch', 'en' => 'English'],   // code => name, the first is the default
'default' => null,              // or one of the codes
'route_parameter' => 'language',
'detect_from_url' => true,      // first URL segment (/en/…) selects the language
'app_locale' => true,           // app locale follows the language (or ['de' => 'de_DE', …])
'fallback' => true,             // empty values show the default language's
'redirect_other_languages' => true,  // /en/news/nachricht → 301 /en/news/news-item
```

## Translated fields

Add a field of type **Translated** and put the fields that differ per language into it, like a
Group:

```yaml
-
  handle: content
  field:
    type: translated
    display: Content
    fields:
      - { handle: headline, field: { type: text, required: true } }
      - { handle: body, field: { type: bard } }
```

Stored as one object per language (JSON in the database, a keyed array in YAML):

```yaml
content:
  de: { headline: Überschrift, body: [...] }
  en: { headline: Headline }
```

In templates it's a group of the current language. Empty values fall back to the default
language (`fallback`):

```antlers
{{ content:headline }}
{{ content }}{{ body }}{{ /content }}
```

### A single field

To translate one existing field, wrap it in a Translated field with the same handle. Templates
keep using it as before (`{{ description }}` returns the current language's value), and values
saved before it was wrapped count as the default language's:

```yaml
-
  handle: description
  field:
    type: translated
    hide_display: true
    border: false
    fields:
      - { handle: description, field: { type: bard, display: Description } }
```

Stored as `description: { de: { description: [...] }, en: { description: [...] } }`.

Only the default language is validated as `required`; the others may stay empty. Values saved
before a field was wrapped count as the default language's, so existing fields can be wrapped
without migrating data.

In the CP every tab containing translated fields (the sidebar too) gets the language switch on
top. The selected language applies to the whole CP (per browser tab) until it's switched again.

## Title and slug

Statamic needs `title` and `slug` as plain strings, so they stay in the default language. Their
other languages go into **Translation** fields:

```yaml
- { handle: title, field: { type: text, required: true } }
- { handle: title_translation, field: { type: translation, field: title, display: 'Title (translation)' } }
- { handle: slug, field: { type: slug } }
- { handle: slug_translation, field: { type: translation, field: slug, display: 'Slug (translation)' } }
```

A Translation field shows only while another language is selected. On the frontend `{{ title }}`
and `{{ slug }}` return the current language's translation, falling back to the default. When an
entry is created, an empty slug translation is generated from the title translation (like
Statamic's own slug); existing entries keep their URLs when a title is translated later.

## Routes

Put the parameter into a collection's route:

```yaml
route: '/{language}/news/{slug}'
```

- `{{ url }}`, `{{ permalink }}` and `$entry->url()` use the current language (and the translated
  slug),
- `/en/news/news-item` finds its entry and renders it in English. The default language's URL is
  Statamic's own; the others live in the table `translated_uris`, updated when an entry or a tree
  is saved. After changing a route or importing content, rebuild it:

```bash
php please translations:refresh-uris [collection …]
```

For pages that aren't entries (`Route::statamic`, the site's and other addons' routes in the `web`
group) the language comes from the URL's first segment (`detect_from_url`). Prefix those routes
with the language yourself, e.g. `Route::statamic('de/suche', 'search')` and `'en/search'`.

A URL with another language's slug under a language's prefix (`/en/news/nachricht`) redirects
(301) to the entry's URL in that language (`redirect_other_languages`).

## Templates

```antlers
{{ translations:current }}    {{# de #}}
{{ translations:name }}       {{# Deutsch #}}
{{ translations:default }}    {{# de #}}

{{ translations }}            {{# every language #}}
    <a href="{{ url }}" hreflang="{{ code }}" {{ if current }}aria-current="true"{{ /if }}>{{ name }}</a>
{{ /translations }}
```

`url` is the current entry in that language (or the current URL with its language segment
replaced), for language switches and `<link rel="alternate" hreflang>` tags.

In PHP: `Translations::current()`, `Translations::in('en', fn () => $entry->url())`.

## Limitations

- Structured collections (pages trees): put `{language}` only at the start of the route; child
  URIs are built from their parents' URIs.
- Another addon that rewrites blueprints in its own `EntryBlueprintFound` listener (adding
  translated fields) runs after this addon's listener: call
  `app(\Bastihilger\SimpleStatamicTranslations\Listeners\AdjustBlueprint::class)->handle($event)`
  after the rewrite.
- PHP that reads values raw (`$entry->get('description')`) gets every language
  (`{de: {…}, en: {…}}`); augment them (`$entry->augmentedValue(…)`) to get the current one.
- Asset and entry references inside translated fields aren't updated when the referenced item is
  renamed or moved (Statamic's reference updater doesn't know the language level yet).
- No GraphQL types for translated fields yet.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
