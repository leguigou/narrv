export default function transcriptViewer(transcript) {
    return {
        transcript: transcript,
        activeTab: 'transcript',
        targetLang: 'fr',
        translating: false,
        translation: null,

        async download(format) {
            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;
            window.open(`/api/videos/${videoId}/transcript/download?format=${format}`, '_blank');
        },

        async translate() {
            this.translating = true;
            try {
                const res = await fetch(`/api/videos/${Alpine.store('app').currentVideo.id}/translate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ language: this.targetLang })
                });
                const data = await res.json();
                this.translation = data.content;
            } catch (e) {
                console.error('Translation error:', e);
            } finally {
                this.translating = false;
            }
        }
    };
}
