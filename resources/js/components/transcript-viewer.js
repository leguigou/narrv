export default function transcriptViewer(transcript) {
    return {
        transcript: transcript,
        activeTab: 'transcript',
        targetLang: 'fr',
        translating: false,
        translation: null,
        error: null,

        async download(format) {
            const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
            if (!videoId) return;
            window.open(`/api/videos/${videoId}/transcript/download?format=${format}`, '_blank');
        },

        async translate() {
            this.translating = true;
            this.error = null;
            try {
                const videoId = this.transcript?.video_id || Alpine.store('app').currentVideo?.id;
                if (!videoId) return;

                const res = await fetch(`/api/videos/${videoId}/translate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
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
        }
    };
}
