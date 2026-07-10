export default function adminPanel() {
    return {
        token: localStorage.getItem('narrv_admin_token') || null,
        password: '',
        section: 'dashboard',
        sections: [
            { id: 'dashboard', label: 'Dashboard', icon: '📊' },
            { id: 'cookies', label: 'Cookies YouTube', icon: '🍪' },
            { id: 'prompts', label: 'Prompts IA', icon: '🤖' },
            { id: 'videos', label: 'Vidéos', icon: '🎬' },
            { id: 'monitoring', label: 'Monitoring', icon: '🩺' },
            { id: 'logs', label: 'Logs', icon: '⚠️' },
        ],

        // Dashboard stats
        stats: null,

        // Cookies
        cookiesStatus: null,
        cookiesFile: null,
        cookiesMessage: null,
        cookiesError: null,
        cookiesDiagnostic: null,
        testingCookies: false,
        uploadingCookies: false,

        // Videos
        videos: [],
        videosLoading: false,
        retryingVideoId: null,
        videoActionMessage: null,
        videoActionError: null,

        // Prompts
        prompts: [],
        promptsLoading: false,
        promptMessage: null,
        promptError: null,
        savingPromptKey: null,

        // Logs
        logs: [],
        logGroups: [],
        logsMeta: null,
        logsLoading: false,
        logsMessage: null,
        logsError: null,
        expandedLogId: null,
        logLevel: 'all',
        logSource: 'all',
        logSearch: '',
        logGrouped: true,

        // Monitoring
        monitoring: null,
        monitoringLoading: false,
        monitoringError: null,

        loginError: null,

        init() {
            if (this.token) {
                this.loadDashboard();
            }
        },

        setSection(id) {
            this.section = id;
            this.clearMessages();

            if (id === 'videos' && this.videos.length === 0) {
                this.loadVideos();
            }
            if (id === 'prompts' && this.prompts.length === 0) {
                this.loadPrompts();
            }
            if (id === 'cookies' && !this.cookiesStatus) {
                this.loadStats();
            }
            if (id === 'logs' && this.logs.length === 0) {
                this.loadLogs();
            }
            if (id === 'monitoring' && !this.monitoring) {
                this.loadMonitoring();
            }
        },

        clearMessages() {
            this.videoActionMessage = null;
            this.videoActionError = null;
            this.promptMessage = null;
            this.promptError = null;
            this.cookiesMessage = null;
            this.cookiesError = null;
            this.logsMessage = null;
            this.logsError = null;
            this.monitoringError = null;
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
                this.loadPrompts(),
                this.loadLogs(),
                this.loadMonitoring()
            ]);
        },

        async loadStats() {
            try {
                const res = await fetch('/api/admin/stats', {
                    headers: this.authHeaders()
                });
                this.stats = await this.readApiResponse(res, 'Chargement des stats impossible.');
                this.cookiesStatus = this.stats.youtube_cookies || null;
            } catch (e) {
                this.handleAuthError(e);
            }
        },

        async loadVideos() {
            this.videosLoading = true;

            try {
                const res = await fetch('/api/admin/videos?per_page=100', {
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Chargement des videos impossible.');
                this.videos = data.data || [];
            } catch (e) {
                this.handleAuthError(e);
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
                this.promptError = e.message;
                this.handleAuthError(e);
            } finally {
                this.promptsLoading = false;
            }
        },

        handleAuthError(error) {
            if (error.message && error.message.includes('Session invalide')) {
                this.logout();
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

        async loadMonitoring() {
            this.monitoringLoading = true;
            this.monitoringError = null;

            try {
                const res = await fetch('/api/admin/monitoring', {
                    headers: this.authHeaders()
                });
                this.monitoring = await this.readApiResponse(res, 'Chargement du monitoring impossible.');
            } catch (e) {
                this.monitoringError = e.message;
                this.handleAuthError(e);
            } finally {
                this.monitoringLoading = false;
            }
        },

        async loadLogs() {
            this.logsLoading = true;
            this.logsError = null;

            try {
                const params = new URLSearchParams({
                    limit: '120',
                    level: this.logLevel,
                    source: this.logSource,
                    search: this.logSearch.trim()
                });

                const res = await fetch(`/api/admin/logs?${params.toString()}`, {
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Chargement des logs impossible.');
                this.logs = data.entries || [];
                this.logGroups = data.groups || [];
                this.logsMeta = {
                    total: data.total || 0,
                    size: data.size || 0,
                    updated_at: data.updated_at || null,
                    levels: data.levels || {},
                    sources: data.sources || {}
                };
            } catch (e) {
                this.logs = [];
                this.logGroups = [];
                this.logsError = e.message;
                this.handleAuthError(e);
            } finally {
                this.logsLoading = false;
            }
        },

        async clearLogs() {
            if (!confirm('Vider les logs d\'erreur ?')) return;

            this.logsMessage = null;
            this.logsError = null;

            try {
                const res = await fetch('/api/admin/logs', {
                    method: 'DELETE',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Purge des logs impossible.');
                this.logs = [];
                this.logGroups = [];
                this.logsMeta = { total: 0, size: 0, updated_at: null, levels: {}, sources: {} };
                this.expandedLogId = null;
                this.logsMessage = data.message || 'Logs purges.';
            } catch (e) {
                this.logsError = e.message;
            }
        },

        async copyLog(log) {
            await navigator.clipboard.writeText(log.raw || log.message || '');
            this.logsMessage = 'Log copie dans le presse-papiers.';
        },

        toggleLog(log) {
            this.expandedLogId = this.expandedLogId === log.id ? null : log.id;
        },

        logLevelClass(level) {
            return {
                ERROR: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800',
                CRITICAL: 'bg-red-100 text-red-800 ring-1 ring-red-300 dark:bg-red-950 dark:text-red-200 dark:ring-red-700',
                ALERT: 'bg-purple-50 text-purple-700 ring-1 ring-purple-200 dark:bg-purple-950 dark:text-purple-300 dark:ring-purple-800',
                EMERGENCY: 'bg-purple-100 text-purple-800 ring-1 ring-purple-300 dark:bg-purple-950 dark:text-purple-200 dark:ring-purple-700'
            }[level] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700';
        },

        sourceLabel(source) {
            return {
                deepseek: 'DeepSeek',
                youtube: 'YouTube / yt-dlp',
                database: 'Base de donnees',
                storage: 'Stockage',
                laravel: 'Laravel'
            }[source] || source || 'Inconnue';
        },

        statusLabelFor(value) {
            return {
                ok: 'OK',
                warning: 'Attention',
                error: 'Erreur'
            }[value] || value || 'Inconnu';
        },

        statusPillClass(value) {
            return {
                ok: 'bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800',
                warning: 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-300 dark:ring-yellow-800',
                error: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800'
            }[value] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700';
        },

        bytesLabel(value) {
            if (!value && value !== 0) return '-';

            const units = ['o', 'Ko', 'Mo', 'Go', 'To'];
            let size = Number(value);
            let unit = 0;

            while (size >= 1024 && unit < units.length - 1) {
                size /= 1024;
                unit++;
            }

            return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
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
            this.retryingVideoId = video.id;

            try {
                const res = await fetch(`/api/admin/videos/${video.id}/retry`, {
                    method: 'POST',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Relance impossible.');

                video.status = 'pending';
                this.videoActionMessage = data.message || 'Video relancee.';
                await this.loadDashboard();
            } catch (e) {
                this.videoActionError = e.message;
            } finally {
                this.retryingVideoId = null;
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

        previewVideo: null,

        async viewVideo(video) {
            this.videoActionMessage = null;
            this.videoActionError = null;

            try {
                const res = await fetch(`/api/videos/${video.id}`, {
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Chargement de la video impossible.');
                this.previewVideo = data;
            } catch (e) {
                this.videoActionError = e.message;
            }
        },

        closePreview() {
            this.previewVideo = null;
        },

        async toggleVisibility(video) {
            this.videoActionMessage = null;
            this.videoActionError = null;

            try {
                const res = await fetch(`/api/admin/videos/${video.id}/visibility`, {
                    method: 'PUT',
                    headers: this.authHeaders()
                });
                const data = await this.readApiResponse(res, 'Changement de visibilite impossible.');

                video.is_visible = data.is_visible;
                this.videoActionMessage = data.message;
            } catch (e) {
                this.videoActionError = e.message;
            }
        },

        videoUrl(video) {
            return `/video/${video.id}`;
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
                pending: 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-300 dark:ring-yellow-800',
                processing: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950 dark:text-blue-300 dark:ring-blue-800',
                ready: 'bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800',
                error: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-300 dark:ring-red-800'
            }[status] || 'bg-gray-50 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700';
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
            this.logs = [];
            this.logGroups = [];
            this.logsMeta = null;
            this.monitoring = null;
            this.section = 'dashboard';
        }
    };
}
