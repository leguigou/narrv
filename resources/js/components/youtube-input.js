export default function youtubeInput() {
    return {
        url: '',
        loading: false,
        error: null,
        success: null,

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

            try {
                const res = await fetch('/api/videos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: this.url })
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.error || 'Erreur lors de l’analyse');

                this.success = 'Video ajoutee. Preparation du transcript en cours.';
                this.url = '';
                window.dispatchEvent(new CustomEvent('video-added', { detail: data }));

                if (data.id) {
                    window.location.href = `/video/${data.id}`;
                }
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        }
    };
}
