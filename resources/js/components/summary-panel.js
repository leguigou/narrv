export default function summaryPanel() {
    return {
        summaries: [],
        loading: false,
        temperature: 0.3,
        tone: 'neutral',
        length: 'medium',

        async generate() {
            this.loading = true;
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
                const data = await res.json();
                this.summaries.unshift(data);
            } catch (e) {
                console.error('Summary error:', e);
            } finally {
                this.loading = false;
            }
        },

        async loadSummaries() {
            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const res = await fetch(`/api/videos/${videoId}/summaries`);
            this.summaries = await res.json();
        }
    };
}
