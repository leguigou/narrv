@extends('app')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12" x-data="youtubeInput()">
    <!-- Hero section -->
    <div class="text-center mb-10">
        <h1 class="text-4xl md:text-5xl font-bold tracking-tight mb-4">
            Transformez vos vidéos YouTube<br>
            <span class="text-narrv-500">en podcasts avec IA</span>
        </h1>
        <p class="text-lg text-gray-600 dark:text-gray-400 max-w-xl mx-auto">
            Collez un lien YouTube → obtenez le transcript, un résumé intelligent, ou discutez avec l'IA pour extraire l'essentiel.
        </p>
    </div>

    <!-- Input URL -->
    <div class="relative mb-8">
        <div class="flex gap-2">
            <input type="url"
                   x-model="url"
                   @keydown.enter="submit"
                   placeholder="Collez votre lien YouTube ici..."
                   class="flex-1 px-5 py-4 rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-lg focus:border-narrv-500 dark:focus:border-narrv-400 focus:outline-none transition-colors"
                   :disabled="loading">
            <button @click="submit"
                    :disabled="loading"
                    class="px-8 py-4 bg-narrv-500 hover:bg-narrv-600 text-white font-semibold rounded-2xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
                <span x-show="!loading">Analyser</span>
                <span x-show="loading" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Analyse...
                </span>
            </button>
        </div>
        <!-- Error message -->
        <div x-show="error" x-text="error"
             class="mt-3 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
        </div>
        <!-- Success message -->
        <div x-show="success" x-text="success"
             class="mt-3 px-4 py-2 rounded-xl bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 text-sm">
        </div>
    </div>

    <!-- Feature cards -->
    <div class="grid md:grid-cols-3 gap-4 mb-12">
        <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-3 text-lg">📝</div>
            <h3 class="font-semibold mb-1">Transcript</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Récupérez le texte complet avec timestamps. Téléchargez en .txt, .vtt ou .srt.</p>
        </div>
        <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-3 text-lg">🌍</div>
            <h3 class="font-semibold mb-1">Traduction</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Traduisez dans 5 langues : anglais, français, espagnol, italien, allemand.</p>
        </div>
        <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
            <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mb-3 text-lg">🤖</div>
            <h3 class="font-semibold mb-1">Résumé + Chat</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">Résumé intelligent avec paramètres ajustables. Chattez avec l'IA sur le contenu.</p>
        </div>
    </div>

    <!-- Recent videos -->
    <div x-data="videoList()">
        <h2 class="text-xl font-bold mb-4" x-show="videos.length > 0">
            Dernières vidéos
        </h2>
        <div class="space-y-3">
            <template x-for="video in videos" :key="video.id">
                <a :href="`/video/${video.id}`"
                   class="flex items-center gap-4 p-4 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-narrv-300 dark:hover:border-narrv-700 transition-colors">
                    <img :src="video.thumbnail_url || '/placeholder.jpg'" alt=""
                         class="w-20 h-14 rounded-lg object-cover bg-gray-200 dark:bg-gray-800 shrink-0">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium truncate" x-text="video.title || 'Sans titre'"></div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5" x-text="video.channel_name || ''"></div>
                    </div>
                    <span class="text-lg shrink-0" x-text="statusIcon(video.status)"></span>
                </a>
            </template>
        </div>
        <div x-show="videos.length === 0 && !loading"
             class="text-center py-12 text-gray-400 dark:text-gray-600">
            <div class="text-4xl mb-3">🎧</div>
            <p>Collez votre première vidéo YouTube pour commencer</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:data', () => {
        Alpine.data('youtubeInput', youtubeInput);
        Alpine.data('videoList', () => ({
            videos: [],
            loading: true,
            init() {
                this.loadVideos();
                window.addEventListener('video-added', () => this.loadVideos());
            },
            async loadVideos() {
                try {
                    const res = await fetch('/api/videos');
                    const data = await res.json();
                    this.videos = data.data || [];
                } catch(e) { console.error(e); }
                finally { this.loading = false; }
            },
            statusIcon(status) {
                return { pending: '⏳', processing: '🔄', ready: '✅', error: '❌' }[status] || '⏳';
            }
        }));
    });
</script>
@endsection
