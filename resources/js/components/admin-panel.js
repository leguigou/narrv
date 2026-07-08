export default function adminPanel() {
    return {
        token: localStorage.getItem('narrv_admin_token') || null,
        password: '',
        stats: null,
        cookiesStatus: null,
        cookiesFile: null,
        cookiesMessage: null,
        cookiesError: null,
        cookiesDiagnostic: null,
        testingCookies: false,
        uploadingCookies: false,
        videos: [],
        videosLoading: false,
        videoActionMessage: null,
        videoActionError: null,
        prompts: [],
        promptsLoading: false,
        promptMessage: null,
        promptError: null,
        savingPromptKey: null,
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
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ password: this.password })
                });

                const data = await this.readApiResponse(res, 'Mot de passe incorrect');

                this.token = data.token;
                localStorage.setItem('narrv_admin_token', this.token);
                this.loginError = null;
                this.loadDashboard();
            } catch (e) {
                this.loginError = e.message;
            }
        },

        async loadDashboard() {
            await Promise.allSettled([
                this.loadStats(),
                this.loadVideos(),
                this.loadPrompts()
            ]);
        },

        async loadStats() {
            const res = await fetch('/api/admin/stats', {
                headers: this.authHeaders()
            });
            this.stats = await this.readApiResponse(res, 'Chargement des stats impossible.');
            this.cookiesStatus = this.stats.youtube_cookies || null;
        },

        async loadVideos() {
            this.videosLoading = true;

            try {
                const res = await fetch('/api/admin/videos?per_page=10', {
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Chargement des videos impossible.');
                this.videos = data.data || [];
            } finally {
                this.videosLoading = false;
            }
        },

        async loadPrompts() {
            this.promptsLoading = true;
            this.promptError = null;

            try {
                const res = await fetch('/api/admin/prompts', {
                    headers: this.authHeaders()
                });
                this.prompts = await this.readApiResponse(res, 'Chargement des prompts impossible.');
            } catch (e) {
                this.prompts = [];
                this.promptError = e.message;
            } finally {
                this.promptsLoading = false;
            }
        },

        async savePrompt(prompt) {
            this.promptMessage = null;
            this.promptError = null;
            this.savingPromptKey = prompt.key;

            try {
                const res = await fetch(`/api/admin/prompts/${prompt.key}`, {
                    method: 'PUT',
                    headers: this.authJsonHeaders(),
                    body: JSON.stringify({ content: prompt.content })
                });
                const data = await this.readApiResponse(res, 'Enregistrement du prompt impossible.');

                this.replacePrompt(data.prompt);
                this.promptMessage = data.message || 'Prompt enregistre.';
            } catch (e) {
                this.promptError = e.message;
            } finally {
                this.savingPromptKey = null;
            }
        },

        async resetPrompt(prompt) {
            if (!confirm('Remettre ce prompt par defaut ?')) return;

            this.promptMessage = null;
            this.promptError = null;
            this.savingPromptKey = prompt.key;

            try {
                const res = await fetch(`/api/admin/prompts/${prompt.key}/reset`, {
                    method: 'POST',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Reinitialisation du prompt impossible.');

                this.replacePrompt(data.prompt);
                this.promptMessage = data.message || 'Prompt remis par defaut.';
            } catch (e) {
                this.promptError = e.message;
            } finally {
                this.savingPromptKey = null;
            }
        },

        replacePrompt(updatedPrompt) {
            this.prompts = this.prompts.map((prompt) => (
                prompt.key === updatedPrompt.key ? updatedPrompt : prompt
            ));
        },

        selectCookiesFile(event) {
            this.cookiesFile = event.target.files?.[0] || null;
            this.cookiesMessage = null;
            this.cookiesError = null;
            this.cookiesDiagnostic = null;
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
                    headers: this.authHeaders(),
                    body: formData
                });

                const data = await this.readApiResponse(res, 'Import impossible.');

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
                headers: this.authHeaders()
            });
            const data = await this.readApiResponse(res, 'Suppression des cookies impossible.');
            this.cookiesStatus = data.youtube_cookies;
            this.cookiesMessage = data.message || 'Cookies supprimes.';
            this.cookiesError = null;
            await this.loadStats();
        },

        async testYoutubeCookies() {
            this.testingCookies = true;
            this.cookiesMessage = null;
            this.cookiesError = null;
            this.cookiesDiagnostic = null;

            try {
                const res = await fetch('/api/admin/youtube-cookies/test', {
                    method: 'POST',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Diagnostic impossible.');

                this.cookiesDiagnostic = data;
                this.cookiesMessage = data.diagnostic?.ok
                    ? 'Diagnostic OK : yt-dlp peut lire la video et recuperer un sous-titre.'
                    : 'Diagnostic en erreur : consultez le detail ci-dessous.';
            } catch (e) {
                this.cookiesError = e.message;
            } finally {
                this.testingCookies = false;
            }
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
                headers: this.authHeaders()
            });
            this.loadDashboard();
        },

        async retryVideo(video) {
            this.videoActionMessage = null;
            this.videoActionError = null;

            try {
                const res = await fetch(`/api/admin/videos/${video.id}/retry`, {
                    method: 'POST',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Relance impossible.');

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
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Suppression impossible.');

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

        jsonHeaders() {
            return {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            };
        },

        authHeaders() {
            return {
                'Accept': 'application/json',
                'Authorization': `Bearer ${this.token}`
            };
        },

        authJsonHeaders() {
            return {
                ...this.authHeaders(),
                'Content-Type': 'application/json'
            };
        },

        async readApiResponse(response, fallbackMessage) {
            const contentType = response.headers.get('content-type') || '';
            const body = await response.text();
            let data = {};

            if (contentType.includes('application/json')) {
                try {
                    data = body ? JSON.parse(body) : {};
                } catch {
                    throw new Error(fallbackMessage);
                }
            } else if (body.trim().startsWith('<!DOCTYPE') || body.trim().startsWith('<html')) {
                throw new Error(`${fallbackMessage} Le serveur a retourne une page HTML au lieu de JSON. Consultez les logs Dokploy/Laravel pour le detail.`);
            } else if (body.trim() !== '') {
                throw new Error(body.trim().slice(0, 300));
            }

            if (!response.ok) {
                throw new Error(data.error || data.message || fallbackMessage);
            }

            return data;
        },

        logout() {
            this.token = null;
            localStorage.removeItem('narrv_admin_token');
            this.stats = null;
            this.videos = [];
        }
    };
}
