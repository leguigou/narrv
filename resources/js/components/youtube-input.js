export default function youtubeInput() {
    return {
        url: '',
        loading: false,
        error: null,
        success: null,
        progress: 0,
        progressMessage: '',
        progressTimer: null,

        examples: [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://m.youtube.com/live/dQw4w9WgXcQ',
        ],

        get detectedId() {
            return this.extractYoutubeId(this.url);
        },

        get isSupportedUrl() {
            return this.url.trim() === '' || this.detectedId !== null;
        },

        useExample(example) {
            this.url = example;
            this.error = null;
            this.success = null;
        },

        extractYoutubeId(value) {
            const raw = value.trim();
            if (!raw) return null;

            let parsed;
            try {
                parsed = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`);
            } catch {
                return null;
            }

            const host = parsed.hostname.toLowerCase();
            const pathSegments = parsed.pathname.split('/').filter(Boolean);
            let candidate = null;

            if (host === 'youtu.be') {
                candidate = pathSegments[0];
            } else if (host === 'youtube.com' || host.endsWith('.youtube.com') || host === 'youtube-nocookie.com' || host.endsWith('.youtube-nocookie.com')) {
                candidate = parsed.searchParams.get('v');

                if (!candidate && ['embed', 'shorts', 'v', 'live'].includes(pathSegments[0])) {
                    candidate = pathSegments[1];
                }
            }

            return /^[a-zA-Z0-9_-]{11}$/.test(candidate || '') ? candidate : null;
        },

        async submit() {
            if (!this.detectedId) {
                this.error = 'URL YouTube invalide ou non supportee';
                this.success = null;
                return;
            }

            this.loading = true;
            this.error = null;
            this.success = null;
            this.startProgress();

            try {
                const res = await fetch('/api/videos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        url: this.url,
                        preferred_language: this.preferredLanguage()
                    })
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Erreur lors de l analyse');

                this.success = data.already_imported
                    ? 'Video deja importee. Ouverture de la fiche existante.'
                    : 'Video ajoutee. Preparation du transcript en cours.';
                this.url = '';
                window.dispatchEvent(new CustomEvent('video-added', { detail: data }));

                if (data.id) {
                    await this.waitForAnalysis(data.id, data.status);
                    await this.completeProgress();
                    window.location.href = `/video/${data.id}`;
                }
            } catch (e) {
                this.error = e.message;
                this.stopProgress();
            } finally {
                this.loading = false;
            }
        },

        startProgress() {
            this.stopProgress(false);
            this.progress = 4;
            this.progressMessage = 'Préparation de la vidéo...';

            this.progressTimer = window.setInterval(() => {
                let increment;

                if (this.progress < 35) {
                    increment = 4 + Math.random() * 4;
                    this.progressMessage = 'Récupération des informations...';
                } else if (this.progress < 80) {
                    increment = 1.5 + Math.random() * 2.5;
                    this.progressMessage = 'Extraction et analyse du transcript...';
                } else {
                    increment = 0.15 + Math.random() * 0.55;
                    this.progressMessage = 'Finalisation de l’analyse...';
                }

                this.progress = Math.min(94, this.progress + increment);
            }, 450);
        },

        stopProgress(reset = true) {
            if (this.progressTimer) {
                window.clearInterval(this.progressTimer);
                this.progressTimer = null;
            }

            if (reset) {
                this.progress = 0;
                this.progressMessage = '';
            }
        },

        async waitForAnalysis(videoId, initialStatus) {
            if (initialStatus === 'ready') return;
            if (initialStatus === 'error') throw new Error('L’analyse de cette vidéo a échoué.');

            while (true) {
                await new Promise((resolve) => window.setTimeout(resolve, 2500));

                const res = await fetch(`/api/videos/${videoId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const video = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(video.error || 'Impossible de suivre l’analyse.');
                }
                if (video.status === 'ready') return;
                if (video.status === 'error') {
                    throw new Error(video.error_message || 'L’analyse de cette vidéo a échoué.');
                }
            }
        },

        async completeProgress() {
            this.stopProgress(false);
            this.progressMessage = 'Analyse terminée, affichage des résultats...';

            while (this.progress < 100) {
                this.progress = Math.min(100, this.progress + 3);
                await new Promise((resolve) => window.setTimeout(resolve, 25));
            }

            await new Promise((resolve) => window.setTimeout(resolve, 250));
        },

        preferredLanguage() {
            const languages = navigator.languages?.length
                ? navigator.languages
                : [navigator.language].filter(Boolean);

            return languages[0] || 'en';
        }
    };
}
