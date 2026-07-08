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
        translation: null,
        error: null,

        init() {
            this.targetLang = this.defaultTargetLanguage();
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

        get isSameLanguage() {
            return Boolean(this.sourceLang && this.targetLang && this.sourceLang === this.targetLang);
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

            this.translating = true;
            this.error = null;
            try {
                const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
                if (!videoId) return;

                const res = await fetch(`/api/videos/${videoId}/translate`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: this.targetLang })
                });
                if (!res.ok) {
                    const payload = await res.json().catch(() => ({}));
                    throw new Error(payload.error || 'Erreur de traduction');
                }
                const data = await res.json();
                this.translation = data.content;
            } catch (e) {
                console.error('Translation error:', e);
                this.error = e.message;
            } finally {
                this.translating = false;
            }
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

        normalizeLanguage(language) {
            if (!language || typeof language !== 'string') {
                return null;
            }

            return language.trim().toLowerCase().replace('_', '-').split('-')[0] || null;
        }
    };
}
