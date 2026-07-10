import { renderMarkdown } from '../utils/markdown';

export default function chatInterface() {
    return {
        messages: [],
        input: '',
        loading: false,
        error: null,
        copiedMessageId: null,
        suggestions: [
            'Résume les points clés de cette vidéo.',
            'Donne-moi les passages importants.',
            'Liste les actions concrètes à retenir.',
        ],

        renderMarkdown(text) {
            return renderMarkdown(text);
        },

        useSuggestion(text) {
            this.input = text;
            this.send();
        },

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
            this.scrollToBottom();

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
                this.scrollToBottom();
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
            this.scrollToBottom();
        },

        async copyToClipboard(message) {
            await navigator.clipboard.writeText(message.content);
            this.copiedMessageId = message.id;
            setTimeout(() => {
                if (this.copiedMessageId === message.id) {
                    this.copiedMessageId = null;
                }
            }, 1600);
        },

        messageTime(message) {
            if (!message.created_at) return '';

            return new Intl.DateTimeFormat('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date(message.created_at));
        },

        scrollToBottom() {
            this.$nextTick(() => {
                if (this.$refs.chatbox) {
                    this.$refs.chatbox.scrollTop = this.$refs.chatbox.scrollHeight;
                }
            });
        }
    };
}
