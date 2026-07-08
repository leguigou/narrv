export default function adminPanel() {
    return {
        token: localStorage.getItem('narrv_admin_token') || null,
        password: '',
        stats: null,
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
