export default function youtubeInput() {
    return {
        url: '',
        loading: false,
        error: null,
        success: null,

        youtubeRegex: /^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)\/.+$/,

        async submit() {
            if (!this.youtubeRegex.test(this.url)) {
                this.error = 'URL YouTube invalide';
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

                if (!res.ok) throw new Error('Erreur lors de l\'analyse');

                const data = await res.json();
                this.success = 'Vidéo en cours d\'analyse...';
                this.url = '';
                // Déclenche le rafraîchissement de la liste
                window.dispatchEvent(new CustomEvent('video-added', { detail: data }));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        }
    };
}
