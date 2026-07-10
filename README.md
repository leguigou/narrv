<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://placehold.co/120x40/0c8ee9/ffffff?text=Narrv">
    <img src="https://placehold.co/120x40/0c8ee9/ffffff?text=Narrv" alt="Narrv" width="120">
  </picture>
</p>

<h1 align="center">Narrv</h1>
<p align="center">
  <strong>Transformez vos vidéos YouTube en podcasts avec l'IA</strong>
  <br>
  Transcript · Résumé IA · Traduction · Chat interactif
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-red?logo=laravel">
  <img src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php">
  <img src="https://img.shields.io/badge/SQLite-003B57?logo=sqlite">
  <img src="https://img.shields.io/badge/Alpine.js-8BC0D0?logo=alpinedotjs">
  <img src="https://img.shields.io/badge/Tailwind-06B6D4?logo=tailwindcss">
  <img src="https://img.shields.io/badge/Docker-2496ED?logo=docker">
</p>

---

## 🎯 Concept

Collez un lien YouTube → obtenez instantanément :

- 📝 **Transcript complet** avec timestamps, téléchargeable en .txt, .vtt ou .srt
- 🌍 **Traduction** dans 5 langues (EN, FR, ES, IT, DE) via DeepSeek
- 🤖 **Résumé intelligent** avec paramètres ajustables (température, ton, longueur)
- 💬 **Chat interactif** — posez des questions sur le contenu de la vidéo
- 🎙️ *(à venir)* Génération podcast avec voix ElevenLabs

## 🏗️ Stack technique

| Couche | Technologie |
|--------|------------|
| **Backend** | Laravel 11 (API headless) |
| **Frontend** | Alpine.js + Tailwind CSS — mobile-first |
| **Base de données** | SQLite (pas de serveur, zéro config) |
| **File d'attente** | Database queue Laravel |
| **LLM** | DeepSeek API (chat + traduction + résumé) |
| **YouTube** | yt-dlp (transcript + métadonnées) |
| **Conteneurisation** | Docker Compose (app + worker supervisord) |
| **Déploiement** | Dokploy (docker-compose.yml dans le repo) |

## 📦 Installation

### En local (dev)

```bash
git clone git@github.com:leguigou/narrv.git
cd narrv

# PHP
composer install

# Frontend
npm install && npm run build

# BDD
touch database/narrv.sqlite
php artisan migrate --force

# Clé API DeepSeek (obligatoire)
echo 'DEEPSEEK_API_KEY=sk-votre-cle' >> .env
echo 'ADMIN_PASSWORD=mot-de-passe-solide' >> .env

# Lancement
php artisan serve
```

### Avec Docker

```bash
cp .env.example .env
# Editer .env pour APP_KEY, DEEPSEEK_API_KEY et ADMIN_PASSWORD

docker compose up -d
```

### Variables d'environnement

| Variable | Défaut | Description |
|----------|--------|-------------|
| `APP_KEY` | — | Clé Laravel (`php artisan key:generate`) |
| `DEEPSEEK_API_KEY` | — | **Requis** — clé API DeepSeek |
| `DEEPSEEK_BASE_URL` | `https://api.deepseek.com` | URL de l'API DeepSeek, sans `/v1` |
| `DEEPSEEK_MODEL` | `deepseek-chat` | Modele DeepSeek utilise |
| `YT_DLP_SLEEP_REQUESTS` | `1` | Delai entre les requetes yt-dlp pour limiter les erreurs 429 |
| `YT_DLP_SLEEP_SUBTITLES` | `3` | Delai specifique entre les telechargements de sous-titres |
| `YT_DLP_RETRIES` | `5` | Nombre de tentatives yt-dlp sur les erreurs reseau |
| `YT_DLP_RETRY_SLEEP` | `http:exp=1:20` | Backoff yt-dlp entre les tentatives HTTP |
| `YT_DLP_JS_RUNTIMES` | `node` | Runtime JavaScript utilise par yt-dlp pour resoudre les challenges YouTube |
| `YOUTUBE_COOKIES_BASE64` | - | Cookies YouTube exportes puis encodes en base64, utiles si YouTube demande une connexion |
| `YOUTUBE_COOKIES_PATH` | `/var/www/storage/app/youtube-cookies.txt` | Chemin du fichier cookies utilise par `yt-dlp` |
| `ADMIN_PASSWORD` | - | **Requis** - mot de passe zone admin |
| `APP_PORT` | `8080` | Port d'exposition Docker |

## 🚀 Déploiement Dokploy

1. Connecter le repo `leguigou/narrv` à Dokploy
2. Définir les variables d'environnement dans Dokploy
3. Dokploy détecte automatiquement le `docker-compose.yml`
4. Les volumes Docker nommes `narrv_storage` et `narrv_database` persistent les donnees entre les redeploys

### Volumes persistants

| Volume | Montage | Contenu |
|--------|---------|---------|
| `narrv_database` | `/var/www/database` | Base SQLite `narrv.sqlite` |
| `narrv_storage` | `/var/www/storage` | Transcripts, logs, cache/session Laravel |

Ne supprimez pas ces volumes dans Dokploy si vous voulez conserver les videos, transcripts, resumes, traductions et conversations.

### Cookies YouTube pour yt-dlp

Si YouTube retourne `Sign in to confirm you're not a bot`, exportez les cookies YouTube depuis un navigateur connecte au format Netscape `cookies.txt`.

La methode la plus simple est d'aller dans `/admin`, section `Cookies YouTube`, puis d'importer directement le fichier `cookies.txt`.

Vous pouvez aussi passer par une variable Dokploy si vous preferez:

```bash
base64 -w 0 cookies.txt
```

Variable Dokploy:

```env
YOUTUBE_COOKIES_BASE64=contenu_base64_du_fichier
```

Au demarrage, le conteneur ecrit ces cookies dans `/var/www/storage/app/youtube-cookies.txt`, stocke dans le volume persistant `narrv_storage`, puis `yt-dlp` les utilise automatiquement.

## 🗺️ Routes API

| Méthode | URL | Description |
|---------|-----|-------------|
| `GET` | `/api/health` | Health check |
| `POST` | `/api/videos` | Soumettre une URL YouTube |
| `GET` | `/api/videos` | Liste des vidéos (paginated) |
| `GET` | `/api/videos/{id}` | Détail vidéo + statut |
| `GET` | `/api/videos/{id}/transcript` | Transcript complet |
| `GET` | `/api/videos/{id}/transcript/download?format=txt` | Télécharger (.txt/.vtt/.srt) |
| `POST` | `/api/videos/{id}/translate` | Traduire (`language: fr`) |
| `POST` | `/api/videos/{id}/summarize` | Générer résumé (temp/tone/length) |
| `POST` | `/api/videos/{id}/chat` | Chat avec l'IA |
| `GET` | `/api/videos/{id}/chat` | Historique du chat |
| `POST` | `/api/admin/login` | Login admin |
| `GET` | `/api/admin/stats` | Dashboard stats |
| `DELETE` | `/api/admin/videos` | Vider toutes les données |

## 📐 Architecture Docker

```
app (php-fpm + nginx + supervisor)
 ├── nginx → sert le frontend, proxy PHP
 ├── php-fpm → Laravel
 └── supervisor
      ├── php-fpm
      ├── nginx
      └── queue:work (traitement async YouTube)
```

## 🗺️ Roadmap

- [ ] **Phase 2** — Génération podcast voix IA (ElevenLabs)
- [ ] **Phase 2** — Export RSS pour soumettre aux plateformes
- [ ] **Phase 2** — Streaming SSE pour le chat
- [ ] **Phase 2** — Téléchargement audio direct

## 📄 Licence

MIT
