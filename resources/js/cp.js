// Simple Statamic Translations – CP components. Plain JS (no build): Statamic exposes Vue, its
// component registry and its UI components (ui.statamic.dev).
//
// One language is selected for the whole CP (kept per browser tab). The language switch on top of
// a form tab changes it; every Translated field shows that language's fields, Translation fields
// (title, slug) show only while another language than the default one is selected.

(() => {
    const h = (...args) => Vue.h(...args);
    const ui = (name, props, slots) => h(Vue.resolveComponent('ui-' + name), props, slots);
    const settings = () => Statamic.$config.get('simpleStatamicTranslations') || { languages: { de: 'Deutsch', en: 'English' }, default: 'de' };
    const storageKey = 'simple-statamic-translations.language';

    const store = Vue.reactive({ language: null });

    const language = () => {
        const { languages, default: fallback } = settings();

        if (!store.language || !(store.language in languages)) {
            let saved = null;
            try { saved = sessionStorage.getItem(storageKey); } catch (e) {}
            store.language = saved && saved in languages ? saved : fallback;
        }

        return store.language;
    };

    const select = (code) => {
        store.language = code;
        try { sessionStorage.setItem(storageKey, code); } catch (e) {}
    };

    // `if: custom simpleTranslationsOtherLanguage` (set on Translation fields by the addon)
    Statamic.booting(() => {
        Statamic.$conditions.add('simpleTranslationsOtherLanguage', () => language() !== settings().default);
        Statamic.$conditions.add('simpleTranslationsDefaultLanguage', () => language() === settings().default);
    });

    Statamic.booted(() => {
        const app = Statamic.$app;

        // the switch on top of a form tab
        app.component('translations_language_switch-fieldtype', {
            props: ['value', 'config', 'handle', 'meta', 'readOnly', 'showFieldPreviews', 'namePrefix', 'fieldPathPrefix', 'metaPathPrefix', 'id'],
            render() {
                const current = language();

                return h('div', { class: 'flex items-center gap-3' }, [
                    h('span', { class: 'text-sm font-medium text-gray-700 dark:text-gray-300' }, __('Language')),
                    ui('button-group', {}, () => Object.entries(settings().languages).map(([code, name]) => ui('button', {
                        size: 'sm',
                        text: name,
                        variant: code === current ? 'primary' : 'default',
                        'aria-pressed': code === current ? 'true' : 'false',
                        onClick: () => select(code),
                    }))),
                ]);
            },
        });

        // Translated: Statamic's group, once per language (path `handle.<language>`), only the
        // selected one visible – the others stay mounted so editors keep their state
        const Group = app.component('group-fieldtype');

        if (Group) {
            app.component('translated-fieldtype', {
                extends: Group,

                computed: {
                    // the selected language's values and previews (used by the collapsed set preview)
                    values() {
                        return this.value?.[language()] || {};
                    },
                    previews() {
                        const path = (this.fieldPathPrefix ? `${this.fieldPathPrefix}.${this.handle}` : this.handle) + '.' + language();
                        return data_get(this.publishContainer.previews, path) || {};
                    },
                },

                render() {
                    const current = language();
                    const prefix = this.fieldPathPrefix ? `${this.fieldPathPrefix}.${this.handle}` : this.handle;
                    const metaPrefix = this.metaPathPrefix ? `${this.metaPathPrefix}.${this.handle}` : this.handle;

                    return h('div', { class: 'simple-translations-translated' }, Object.keys(settings().languages).map((code) => h('div', {
                        key: code,
                        lang: code,
                        style: code === current ? null : 'display:none',
                    }, [
                        h(Group, {
                            handle: code,
                            value: this.value?.[code] || {},
                            meta: this.meta?.[code] || {},
                            config: this.config,
                            readOnly: this.readOnly,
                            showFieldPreviews: this.showFieldPreviews,
                            namePrefix: this.namePrefix,
                            fieldPathPrefix: prefix,
                            metaPathPrefix: metaPrefix,
                            id: `${this.id}-${code}`,
                        }),
                    ])));
                },
            });
        }

        // Translation (title, slug …): the selected language's text
        app.component('translation-fieldtype', {
            props: ['value', 'config', 'handle', 'meta', 'readOnly', 'showFieldPreviews', 'namePrefix', 'fieldPathPrefix', 'metaPathPrefix', 'id'],
            emits: ['update:value', 'update:meta', 'focus', 'blur', 'replicator-preview-updated'],
            render() {
                const current = language();

                if (current === settings().default) {
                    return null;
                }

                return ui('input', {
                    id: this.id,
                    modelValue: this.value?.[current] ?? '',
                    prepend: settings().languages[current],
                    readOnly: this.readOnly,
                    'onUpdate:modelValue': (text) => this.$emit('update:value', { ...(this.value || {}), [current]: text }),
                    onFocus: () => this.$emit('focus'),
                    onBlur: () => this.$emit('blur'),
                });
            },
        });
    });
})();
