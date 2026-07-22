export default function youtubeInput() {
    return {
        url: '',
        loading: false,
        error: null,
        success: null,
        progress: 0,
        progressMessage: '',
        progressTimer: null,
        elapsedTimer: null,
        elapsedSeconds: 0,
        progressStep: 1,
        progressCompleting: false,
        progressWaiting: false,

        get progressLabel() {
            if (this.progressWaiting && !this.progressCompleting) {
                return `En cours · ${this.elapsedLabel}`;
            }

            return `${Math.round(this.progress)}%`;
        },

        get elapsedLabel() {
            const minutes = Math.floor(this.elapsedSeconds / 60);
            const seconds = this.elapsedSeconds % 60;
            return `${minutes}:${seconds.toString().padStart(2, '0')}`;
        },

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

            this.$nextTick(() => {
                this.$refs.analysisProgress?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });

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
                    : 'Video ajoutee. Recuperation des informations essentielles.';
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
            this.progressStep = 1;
            this.progressCompleting = false;
            this.progressWaiting = false;
            this.elapsedSeconds = 0;
            this.progressMessage = 'Préparation de la vidéo...';

            this.elapsedTimer = window.setInterval(() => {
                this.elapsedSeconds++;
                if (this.progressWaiting) {
                    this.progressMessage = this.elapsedSeconds < 30
                        ? 'YouTube prépare les informations de la vidéo...'
                        : 'Toujours en cours chez YouTube, la page s’ouvrira automatiquement...';
                }
            }, 1000);

            this.progressTimer = window.setInterval(() => {
                let increment;

                if (this.progress < 30) {
                    increment = 3 + Math.random() * 3;
                    this.progressStep = 1;
                    this.progressMessage = 'Préparation de la vidéo...';
                } else if (this.progress < 70) {
                    increment = 1.5 + Math.random() * 2;
                    this.progressStep = 2;
                    this.progressMessage = 'Récupération des informations YouTube...';
                } else if (this.progress < 88) {
                    increment = 0.35 + Math.random() * 0.45;
                    this.progressStep = 2;
                    this.progressMessage = 'Préparation de la fiche vidéo...';
                } else {
                    this.progress = 88;
                    this.progressStep = 2;
                    this.progressWaiting = true;
                    this.progressMessage = 'YouTube prépare les informations de la vidéo...';
                    window.clearInterval(this.progressTimer);
                    this.progressTimer = null;
                    return;
                }

                this.progress = Math.min(88, this.progress + increment);
            }, 450);
        },

        stopProgress(reset = true) {
            if (this.progressTimer) {
                window.clearInterval(this.progressTimer);
                this.progressTimer = null;
            }
            if (this.elapsedTimer) {
                window.clearInterval(this.elapsedTimer);
                this.elapsedTimer = null;
            }

            if (reset) {
                this.progress = 0;
                this.progressMessage = '';
                this.progressStep = 1;
                this.progressCompleting = false;
                this.progressWaiting = false;
                this.elapsedSeconds = 0;
            }
        },

        async waitForAnalysis(videoId, initialStatus) {
            if (initialStatus === 'ready') return;
            if (initialStatus === 'error') throw new Error('L’analyse de cette vidéo a échoué.');

            while (true) {
                await new Promise((resolve) => window.setTimeout(resolve, 1200));

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
            this.progressStep = 2;
            this.progressCompleting = true;
            this.progressWaiting = false;
            this.progressMessage = 'Vidéo prête, ouverture de la fiche...';
            this.progress = 100;

            // La navigation ne dépend pas de l'animation : ce court délai laisse
            // seulement le navigateur peindre la fin de la barre.
            await new Promise((resolve) => window.setTimeout(resolve, 160));
        },

        preferredLanguage() {
            const languages = navigator.languages?.length
                ? navigator.languages
                : [navigator.language].filter(Boolean);

            return languages[0] || 'en';
        }
    };
}
