export default function videoCard(video) {
    return {
        video: video,
        statusIcon() {
            const icons = {
                pending: '⏳',
                processing: '🔄',
                ready: '✅',
                error: '❌'
            };
            return icons[this.video.status] || '⏳';
        },
        formatDuration(seconds) {
            if (!seconds) return '';
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${m}:${s.toString().padStart(2, '0')}`;
        }
    };
}
