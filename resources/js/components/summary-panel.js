import { renderMarkdown } from '../utils/markdown';

export default function summaryPanel() {
    return {
        summaries: [],
        loading: false,
        temperature: 0.3,
        tone: 'neutral',
        length: 'medium',
        language: 'fr',
        languages: [
            { code: 'fr', label: 'Francais' },
            { code: 'en', label: 'Anglais' },
            { code: 'es', label: 'Espagnol' },
            { code: 'it', label: 'Italien' },
            { code: 'de', label: 'Allemand' }
        ],
        error: null,

        renderMarkdown(text) {
            return renderMarkdown(text);
        },

        async generate() {
            this.loading = true;
            this.error = null;
            try {
                const videoId = Alpine.store('app').currentVideo?.id;
                if (!videoId) return;

                const res = await fetch(`/api/videos/${videoId}/summarize`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        temperature: this.temperature,
                        tone: this.tone,
                        length: this.length,
                        language: this.language
                    })
                });
                const data = await this.readApiResponse(res, 'Erreur de generation du resume');
                this.summaries.unshift(data);
            } catch (e) {
                console.error('Summary error:', e);
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadSummaries() {
            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const res = await fetch(`/api/videos/${videoId}/summaries`);
            const data = await res.json();
            this.summaries = data.data || data;
        },

        languageLabel(code) {
            return this.languages.find((language) => language.code === code)?.label || code || 'Inconnue';
        },

        toneLabel(tone) {
            return {
                neutral: 'Neutre',
                formal: 'Formel',
                casual: 'Décontracté',
                bullet_points: 'Points clés'
            }[tone] || tone || 'Neutre';
        },

        lengthLabel(length) {
            return { short: 'Court', medium: 'Moyen', long: 'Long' }[length] || length || 'Moyen';
        },

        get creativityLabel() {
            const value = Number(this.temperature);
            if (value <= 0.3) return 'Très fidèle';
            if (value <= 0.7) return 'Équilibré';
            if (value <= 1.1) return 'Créatif';
            return 'Très créatif';
        },

        async readApiResponse(response, fallbackMessage) {
            const contentType = response.headers.get('content-type') || '';
            const body = await response.text();
            let data = {};

            if (contentType.includes('application/json')) {
                try {
                    data = body ? JSON.parse(body) : {};
                } catch {
                    throw new Error(fallbackMessage);
                }
            } else if (body.trim().startsWith('<!DOCTYPE') || body.trim().startsWith('<html')) {
                throw new Error(`${fallbackMessage}. Le serveur a retourne une page HTML au lieu de JSON. Consultez les logs admin pour le detail.`);
            } else if (body.trim() !== '') {
                throw new Error(body.trim().slice(0, 300));
            }

            if (!response.ok) {
                if (response.status === 429) {
                    throw new Error('Trop de demandes cote application. Patientez quelques secondes puis relancez le resume.');
                }

                throw new Error(data.error || data.message || fallbackMessage);
            }

            return data;
        }
    };
}
