export default function mediaDownloader() {
    return {
        loading: false,
        error: null,
        formats: null,
        selectedVideo: 'best',
        selectedAudio: 'bestaudio',

        async loadFormats() {
            if (this.formats || this.loading) return;

            this.loading = true;
            this.error = null;

            try {
                const videoId = Alpine.store('app').currentVideo?.id;
                const res = await fetch(`/api/videos/${videoId}/formats`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.error || 'Impossible de recuperer les formats.');
                }

                this.formats = data;
                this.selectedVideo = data.defaults?.video || 'best';
                this.selectedAudio = data.defaults?.audio || 'bestaudio';
            } catch (e) {
                this.error = e.message;
            } finally {
                this.loading = false;
            }
        },

        download(type) {
            const videoId = Alpine.store('app').currentVideo?.id;
            const formatId = type === 'audio' ? this.selectedAudio : this.selectedVideo;

            if (!videoId || !formatId) {
                this.error = 'Selectionnez un format avant de telecharger.';
                return;
            }

            const params = new URLSearchParams({
                type,
                format_id: formatId
            });

            window.location.href = `/api/videos/${videoId}/download?${params.toString()}`;
        },

        sizeLabel(bytes) {
            if (!bytes) return '';

            const units = ['octets', 'Ko', 'Mo', 'Go'];
            let value = bytes;
            let unit = 0;

            while (value >= 1024 && unit < units.length - 1) {
                value /= 1024;
                unit++;
            }

            return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
        }
    };
}
