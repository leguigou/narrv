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
            const res = await fetch('/api/admin/stats', {
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.stats = await res.json();
            this.cookiesStatus = this.stats.youtube_cookies || null;
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
        },

        formatCookiesDate(value) {
            if (!value) return 'Jamais';

            return new Intl.DateTimeFormat('fr-FR', {
                dateStyle: 'short',
                timeStyle: 'short'
            }).format(new Date(value));
        },

        async purgeAll() {
            if (!confirm('Supprimer toutes les données ?')) return;
            await fetch('/api/admin/videos', {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${this.token}` }
            });
            this.loadDashboard();
        },

        logout() {
            this.token = null;
            localStorage.removeItem('narrv_admin_token');
            this.stats = null;
        }
    };
}
