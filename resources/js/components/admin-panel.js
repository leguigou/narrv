export default function adminPanel() {
    return {
        token: localStorage.getItem('narrv_admin_token') || null,
        password: '',
        stats: null,
        cookiesStatus: null,
        cookiesFile: null,
        cookiesMessage: null,
        cookiesError: null,
        uploadingCookies: false,
        videos: [],
        videosLoading: false,
        videoActionMessage: null,
        videoActionError: null,
        loginError: null,

        init() {
            if (this.token) {
                this.loadDashboard();
            }
        },

        async login() {
            try {
                const res = await fetch('/api/admin/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: this.password })
                });

                if (!res.ok) throw new Error('Mot de passe incorrect');

                const data = await res.json();
                this.token = data.token;
                localStorage.setItem('narrv_admin_token', this.token);
                this.loginError = null;
                this.loadDashboard();
            } catch (e) {
                this.loginError = e.message;
            }
        },

        async loadDashboard() {
            await Promise.all([
                this.loadStats(),
                this.loadVideos()
            ]);
        },

        async loadStats() {
            const res = await fetch('/api/admin/stats', {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.stats = await res.json();
            this.cookiesStatus = this.stats.youtube_cookies || null;
        },

        async loadVideos() {
            this.videosLoading = true;

            try {
                const res = await fetch('/api/admin/videos?per_page=10', {
                    headers: { 'Authorization': `Bearer ${this.token}` }
                });
                const data = await res.json();
                this.videos = data.data || [];
            } finally {
                this.videosLoading = false;
            }
        },

        selectCookiesFile(event) {
            this.cookiesFile = event.target.files?.[0] || null;
            this.cookiesMessage = null;
            this.cookiesError = null;
        },

        async uploadYoutubeCookies() {
            if (!this.cookiesFile) {
                this.cookiesError = 'Selectionnez un fichier cookies.txt.';
                return;
            }

            this.uploadingCookies = true;
            this.cookiesMessage = null;
            this.cookiesError = null;

            try {
                const formData = new FormData();
                formData.append('cookies', this.cookiesFile);

                const res = await fetch('/api/admin/youtube-cookies', {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${this.token}` },
                    body: formData
                });

                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.error || data.message || 'Import impossible.');
                }

                this.cookiesStatus = data.youtube_cookies;
                this.cookiesMessage = data.message || 'Cookies importes.';
                this.cookiesFile = null;
                await this.loadStats();

                if (this.$refs.cookiesInput) {
                    this.$refs.cookiesInput.value = '';
                }
            } catch (e) {
                this.cookiesError = e.message;
            } finally {
                this.uploadingCookies = false;
            }
        },

        async deleteYoutubeCookies() {
            if (!confirm('Supprimer le fichier cookies YouTube ?')) return;

            const res = await fetch('/api/admin/youtube-cookies', {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            const data = await res.json();
            this.cookiesStatus = data.youtube_cookies;
            this.cookiesMessage = data.message || 'Cookies supprimes.';
            this.cookiesError = null;
            await this.loadStats();
        },

        formatCookiesDate(value) {
            if (!value) return 'Jamais';

            return new Intl.DateTimeFormat('fr-FR', {
                dateStyle: 'short',
                timeStyle: 'short'
            }).format(new Date(value));
        },

        async purgeAll() {
            if (!confirm('Supprimer toutes les donnees ?')) return;

            await fetch('/api/admin/videos', {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.loadDashboard();
        },

        async retryVideo(video) {
            this.videoActionMessage = null;
            this.videoActionError = null;

            try {
                const res = await fetch(`/api/admin/videos/${video.id}/retry`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${this.token}` }
                });
                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.error || data.message || 'Relance impossible.');
                }

                this.videoActionMessage = data.message || 'Video relancee.';
                await this.loadDashboard();
            } catch (e) {
                this.videoActionError = e.message;
            }
        },

        async deleteVideo(video) {
            if (!confirm('Supprimer cette video ?')) return;

            this.videoActionMessage = null;
            this.videoActionError = null;

            try {
                const res = await fetch(`/api/admin/videos/${video.id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': `Bearer ${this.token}` }
                });
                const data = await res.json();

                if (!res.ok) {
                    throw new Error(data.error || data.message || 'Suppression impossible.');
                }

                this.videoActionMessage = data.message || 'Video supprimee.';
                await this.loadDashboard();
            } catch (e) {
                this.videoActionError = e.message;
            }
        },

        statusLabel(status) {
            return {
                pending: 'En attente',
                processing: 'Traitement',
                ready: 'Prete',
                error: 'Erreur'
            }[status] || status;
        },

        statusClass(status) {
            return {
                pending: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300',
                processing: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
                ready: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
                error: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'
            }[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
        },

        logout() {
            this.token = null;
            localStorage.removeItem('narrv_admin_token');
            this.stats = null;
            this.videos = [];
        }
    };
}
