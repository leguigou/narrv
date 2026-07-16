export default function mediaDownloader() {
    return {
        loading: false,
        error: null,
        formats: null,
        selectedVideo: 'best',
        selectedAudio: 'bestaudio',
        downloadingType: null,
        progress: 0,
        progressMessage: '',
        progressTimer: null,
        success: null,

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

        async download(type) {
            const videoId = Alpine.store('app').currentVideo?.id;
            const formatId = type === 'audio' ? this.selectedAudio : this.selectedVideo;

            if (!videoId || !formatId) {
                this.error = 'Selectionnez un format avant de telecharger.';
                return;
            }

            if (this.downloadingType) return;

            const params = new URLSearchParams({
                type,
                format_id: formatId
            });

            this.error = null;
            this.success = null;
            this.downloadingType = type;
            this.startProgress(type);

            try {
                const res = await fetch(`/api/videos/${videoId}/download?${params.toString()}`, {
                    headers: { 'Accept': 'application/octet-stream, application/json' }
                });

                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.error || 'Le téléchargement a échoué.');
                }

                this.progressMessage = 'Réception du fichier...';
                const blob = await this.readDownload(res);
                const filename = this.filenameFromResponse(res, type);

                this.stopProgress(false);
                this.progress = 100;
                this.progressMessage = 'Fichier prêt.';
                this.saveBlob(blob, filename);
                this.success = type === 'audio'
                    ? 'Audio MP3 téléchargé.'
                    : 'Vidéo téléchargée.';

                await new Promise((resolve) => window.setTimeout(resolve, 700));
            } catch (e) {
                this.error = e.message;
            } finally {
                this.stopProgress();
                this.downloadingType = null;
            }
        },

        startProgress(type) {
            this.stopProgress();
            this.progress = 3;
            this.progressMessage = type === 'audio'
                ? 'Préparation de l’audio MP3...'
                : 'Préparation de la vidéo...';

            this.progressTimer = window.setInterval(() => {
                const increment = this.progress < 60
                    ? 2 + Math.random() * 4
                    : this.progress < 80
                        ? 0.8 + Math.random() * 1.4
                        : 0.12 + Math.random() * 0.45;

                this.progress = Math.min(92, this.progress + increment);
                if (this.progress >= 80) {
                    this.progressMessage = 'Conversion et finalisation du fichier...';
                }
            }, 500);
        },

        stopProgress(reset = true) {
            if (this.progressTimer) {
                window.clearInterval(this.progressTimer);
                this.progressTimer = null;
            }

            if (reset) {
                this.progress = 0;
                this.progressMessage = '';
            }
        },

        async readDownload(response) {
            if (!response.body?.getReader) {
                this.progress = 99;
                return response.blob();
            }

            const reader = response.body.getReader();
            const contentLength = Number(response.headers.get('Content-Length')) || 0;
            const chunks = [];
            let received = 0;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                chunks.push(value);
                received += value.length;

                if (contentLength > 0) {
                    const transferProgress = 92 + (received / contentLength) * 7;
                    this.progress = Math.max(this.progress, Math.min(99, transferProgress));
                } else {
                    this.progress = Math.min(99, this.progress + 0.25);
                }
            }

            return new Blob(chunks, {
                type: response.headers.get('Content-Type') || 'application/octet-stream'
            });
        },

        filenameFromResponse(response, type) {
            const disposition = response.headers.get('Content-Disposition') || '';
            const encoded = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const plain = disposition.match(/filename="?([^";]+)"?/i)?.[1];

            if (encoded) {
                try {
                    return decodeURIComponent(encoded);
                } catch {
                    // Fall back to the regular filename below.
                }
            }

            return plain || (type === 'audio' ? 'audio.mp3' : 'video.mp4');
        },

        saveBlob(blob, filename) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);
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
