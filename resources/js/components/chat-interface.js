export default function chatInterface() {
    return {
        messages: [],
        input: '',
        loading: false,

        async send() {
            if (!this.input.trim()) return;

            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const userMsg = { role: 'user', content: this.input, created_at: new Date().toISOString() };
            this.messages.push(userMsg);
            this.input = '';
            this.loading = true;

            try {
                const res = await fetch(`/api/videos/${videoId}/chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: userMsg.content })
                });
                const data = await res.json();
                this.messages.push(data.assistant);
            } catch (e) {
                console.error('Chat error:', e);
            } finally {
                this.loading = false;
            }
        },

        async loadHistory() {
            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const res = await fetch(`/api/videos/${videoId}/chat`);
            this.messages = await res.json();
        },

        copyToClipboard(text) {
            navigator.clipboard.writeText(text);
        }
    };
}
