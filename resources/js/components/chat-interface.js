export default function chatInterface() {
    return {
        messages: [],
        input: '',
        loading: false,
        error: null,

        async send() {
            if (!this.input.trim()) return;

            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const userMsg = {
                id: `local-${Date.now()}`,
                role: 'user',
                content: this.input,
                created_at: new Date().toISOString()
            };
            this.messages.push(userMsg);
            this.input = '';
            this.loading = true;
            this.error = null;

            try {
                const res = await fetch(`/api/videos/${videoId}/chat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: userMsg.content })
                });
                if (!res.ok) {
                    const payload = await res.json().catch(() => ({}));
                    throw new Error(payload.error || 'Erreur du chat');
                }
                const data = await res.json();
                this.messages.push(data.assistant);
            } catch (e) {
                console.error('Chat error:', e);
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        async loadHistory() {
            const videoId = Alpine.store('app').currentVideo?.id;
            if (!videoId) return;

            const res = await fetch(`/api/videos/${videoId}/chat`);
            const data = await res.json();
            this.messages = data.data || data;
        },

        copyToClipboard(text) {
            navigator.clipboard.writeText(text);
        }
    };
}
