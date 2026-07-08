export default function summaryPanel() {
    return {
        summaries: [],
        loading: false,
        temperature: 0.3,
        tone: 'neutral',
        length: 'medium',
        error: null,

        async generate() {
            this.loading = true;
            this.error = null;
            try {
                const videoId = Alpine.store('app').currentVideo?.id;
                if (!videoId) return;

                const res = await fetch(`/api/videos/${videoId}/summarize`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        temperature: this.temperature,
                        tone: this.tone,
                        length: this.length
                    })
                });
                if (!res.ok) {
                    const payload = await res.json().catch(() => ({}));
                    throw new Error(payload.error || 'Erreur de generation');
                }
                const data = await res.json();
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
        }
    };
}
