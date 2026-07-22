export default function transcriptViewer(transcript) {
    return {
        transcript: transcript,
        activeTab: 'transcript',
        targetLang: 'fr',
        languages: [
            { code: 'en', label: 'Anglais' },
            { code: 'fr', label: 'Francais' },
            { code: 'es', label: 'Espagnol' },
            { code: 'it', label: 'Italien' },
            { code: 'de', label: 'Allemand' }
        ],
        translating: false,
        translatedChunks: 0,
        totalTranslationChunks: 0,
        translation: null,
        translations: [],
        translationsLoaded: false,
        translationsLoading: false,
        error: null,

        init() {
            this.targetLang = this.defaultTargetLanguage();

            // Reafficher la trad stockee quand on change de langue
            this.$watch('targetLang', () => {
                this.showStoredTranslation();
            });
        },

        get sourceLang() {
            return this.normalizeLanguage(this.transcript?.language);
        },

        get availableTargetLanguages() {
            return this.languages.filter((language) => language.code !== this.sourceLang);
        },

        get sourceLanguageLabel() {
            if (!this.sourceLang) {
                return 'Langue source detectee';
            }

            return this.languageLabel(this.sourceLang);
        },

        get targetLanguageLabel() {
            return this.languageLabel(this.targetLang);
        },

        get translationPairLabel() {
            return `${this.sourceLanguageLabel} -> ${this.targetLanguageLabel}`;
        },

        get selectedTranslationRecord() {
            return this.translations.find((translation) => translation.target_language === this.targetLang) || null;
        },

        get hasStoredTargetTranslation() {
            return this.selectedTranslationRecord !== null;
        },

        get isSameLanguage() {
            return Boolean(this.sourceLang && this.targetLang && this.sourceLang === this.targetLang);
        },

        async loadTranslations() {
            if (this.translationsLoaded || this.translationsLoading) return;

            const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            this.translationsLoading = true;
            try {
                const res = await fetch(`/api/videos/${videoId}/translations`);
                if (!res.ok) return;
                this.translations = await res.json();
                this.translationsLoaded = true;
                this.showStoredTranslation();
            } catch (e) {
                // Silently fail, translations are not critical
            } finally {
                this.translationsLoading = false;
            }
        },

        showStoredTranslation() {
            const stored = this.selectedTranslationRecord;
            this.translation = stored?.content || null;
            this.error = null;
        },

        async download(format) {
            const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
            if (!videoId) return;
            window.open(`/api/videos/${videoId}/transcript/download?format=${format}`, '_blank');
        },

        async translate() {
            if (this.isSameLanguage) {
                this.error = 'La langue cible est identique a la langue source.';
                return;
            }

            // Si deja traduite, afficher directement
            const stored = this.translations.find((t) => t.target_language === this.targetLang);
            if (stored) {
                this.translation = stored.content;
                return;
            }

            this.translating = true;
            this.translatedChunks = 0;
            this.totalTranslationChunks = 0;
            this.translation = '';
            this.error = null;
            try {
                const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
                if (!videoId) return;

                const res = await fetch(`/api/videos/${videoId}/translate`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json, application/x-ndjson', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: this.targetLang })
                });
                if (!res.ok) {
                    const payload = await res.json().catch(() => ({}));
                    throw new Error(payload.error || 'Erreur de traduction');
                }
                if (!res.body) {
                    throw new Error('Le navigateur ne permet pas de suivre la traduction.');
                }

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                let completed = false;

                while (true) {
                    const { value, done } = await reader.read();
                    buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

                    const lines = buffer.split('\n');
                    buffer = done ? '' : lines.pop();

                    for (const line of lines) {
                        if (!line.trim()) continue;
                        completed = this.consumeTranslationEvent(JSON.parse(line)) || completed;
                    }

                    if (done) break;
                }

                if (buffer.trim()) {
                    completed = this.consumeTranslationEvent(JSON.parse(buffer)) || completed;
                }

                if (!completed) {
                    throw new Error('La traduction a ete interrompue avant la fin.');
                }
            } catch (e) {
                console.error('Translation error:', e);
                this.error = e.message;
            } finally {
                this.translating = false;
            }
        },

        consumeTranslationEvent(event) {
            if (event.type === 'start') {
                this.translatedChunks = 0;
                this.totalTranslationChunks = event.total;
                return false;
            }

            if (event.type === 'chunk') {
                this.translatedChunks = event.index;
                this.totalTranslationChunks = event.total;
                this.translation += `${this.translation ? '\n\n' : ''}${event.content}`;
                return false;
            }

            if (event.type === 'error') {
                throw new Error(event.error || 'Erreur de traduction');
            }

            if (event.type === 'complete') {
                const data = event.translation;
                this.translation = data.content;
                this.translations = this.translations
                    .filter((translation) => translation.target_language !== data.target_language);
                this.translations.push(data);
                return true;
            }

            return false;
        },

        defaultTargetLanguage() {
            const browserLanguage = this.normalizeLanguage(navigator.language);
            const candidates = [browserLanguage, 'fr', 'en', 'es', 'it', 'de'].filter(Boolean);
            const availableCodes = this.availableTargetLanguages.map((language) => language.code);

            return candidates.find((code) => availableCodes.includes(code)) || availableCodes[0] || 'en';
        },

        languageLabel(code) {
            return this.languages.find((language) => language.code === code)?.label || code || 'Inconnue';
        },

        flagFor(code) {
            const flags = { en: '🇬🇧', fr: '🇫🇷', es: '🇪🇸', it: '🇮🇹', de: '🇩🇪' };
            return flags[code] || '🏳️';
        },

        normalizeLanguage(language) {
            if (!language || typeof language !== 'string') {
                return null;
            }

            return language.trim().toLowerCase().replace('_', '-').split('-')[0] || null;
        }
    };
}
