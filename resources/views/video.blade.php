@extends('app')

@section('title', 'Détail vidéo — Narrv')

@section('content')
<div x-data="videoDetail()" class="max-w-4xl mx-auto px-4 py-8">
    <a href="/" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        ← Retour
    </a>

    <div x-show="!video" class="text-center py-20 text-gray-400">
        <div class="animate-spin h-8 w-8 border-2 border-gray-300 border-t-narrv-500 rounded-full mx-auto mb-4"></div>
        Chargement...
    </div>

    <template x-if="video">
        <div>
            <!-- Video info -->
            <div class="mb-8">
                <img :src="video.thumbnail_url" :alt="video.title"
                     class="w-full aspect-video rounded-2xl object-cover bg-gray-200 dark:bg-gray-800 mb-4">
                <h1 class="text-2xl font-bold" x-text="video.title"></h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1" x-text="video.channel_name"></p>

                <!-- Status badge -->
                <div x-show="video.status === 'processing' || video.status === 'pending'"
                     class="mt-4 px-4 py-3 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 text-sm flex items-center gap-2">
                    <span class="animate-spin">⟳</span>
                    Transcript en cours de récupération...
                </div>
                <div x-show="video.status === 'error'"
                     class="mt-4 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    ⚠️ <span x-text="video.error_message || 'Erreur inconnue'"></span>
                </div>
            </div>

            <!-- Tabs -->
            <div x-show="video.status === 'ready'">
                <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
                    <button @click="tab = 'transcript'" :class="tab === 'transcript' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-3 text-sm font-medium">Transcript</button>
                    <button @click="tab = 'summary'" :class="tab === 'summary' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-3 text-sm font-medium">Résumé</button>
                    <button @click="tab = 'chat'" :class="tab === 'chat' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-3 text-sm font-medium">Chat IA</button>
                    <button @click="tab = 'translate'" :class="tab === 'translate' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="px-4 py-3 text-sm font-medium">Traduire</button>
                </div>

                <!-- Transcript tab -->
                <div x-show="tab === 'transcript'" x-data="transcriptViewer(video.transcript)">
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button @click="download('txt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .txt</button>
                        <button @click="download('vtt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .vtt</button>
                        <button @click="download('srt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .srt</button>
                    </div>
                    <div class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm leading-relaxed" x-text="video.transcript?.full_text || 'Transcript non disponible'"></div>
                </div>

                <!-- Summary tab -->
                <div x-show="tab === 'summary'" x-data="summaryPanel()" x-init="loadSummaries()">
                    <div class="flex flex-wrap gap-3 mb-6">
                        <select x-model="tone" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <option value="neutral">Neutre</option>
                            <option value="formal">Formel</option>
                            <option value="casual">Décontracté</option>
                            <option value="bullet_points">Points clés</option>
                        </select>
                        <select x-model="length" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <option value="short">Court</option>
                            <option value="medium">Moyen</option>
                            <option value="long">Long</option>
                        </select>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500">Temp:</span>
                            <input type="range" x-model="temperature" min="0" max="1.5" step="0.1" class="w-24">
                            <span x-text="temperature" class="text-narrv-500 font-mono"></span>
                        </div>
                        <button @click="generate()" :disabled="loading"
                                class="px-6 py-2 rounded-full bg-narrv-500 text-white text-sm font-medium disabled:opacity-50">
                            <span x-show="!loading">Générer</span>
                            <span x-show="loading">Génération...</span>
                        </button>
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>
                    <div class="space-y-4">
                        <template x-for="summary in summaries" :key="summary.id">
                            <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                <div class="text-xs text-gray-400 mb-2">
                                    <span x-text="summary.tone"></span> · <span x-text="summary.length"></span> · temp <span x-text="summary.temperature"></span>
                                </div>
                                <div class="prose dark:prose-invert max-w-none text-sm whitespace-pre-wrap" x-text="summary.content"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Chat tab -->
                <div x-show="tab === 'chat'" x-data="chatInterface()" x-init="loadHistory()">
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div class="h-96 overflow-y-auto p-4 space-y-3" x-ref="chatbox">
                            <template x-for="msg in messages" :key="msg.id">
                                <div :class="msg.role === 'user' ? 'ml-auto bg-narrv-500 text-white' : 'bg-gray-100 dark:bg-gray-800 dark:text-gray-200'" class="max-w-[80%] p-3 rounded-2xl text-sm whitespace-pre-wrap">
                                    <div x-text="msg.content"></div>
                                    <div x-show="msg.role === 'assistant'" @click="copyToClipboard(msg.content)" class="text-xs text-gray-400 mt-1 cursor-pointer hover:text-gray-600">📋 Copier</div>
                                </div>
                            </template>
                            <div x-show="loading" class="text-center text-gray-400 text-sm py-4">Réflexion...</div>
                        </div>
                        <div x-show="error" x-text="error"
                             class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-800 p-3 flex gap-2">
                            <input type="text" x-model="input" @keydown.enter="send" :disabled="loading"
                                   placeholder="Posez une question sur la vidéo..."
                                   class="flex-1 px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0 focus:ring-2 focus:ring-narrv-500">
                            <button @click="send" :disabled="loading"
                                    class="px-5 py-2 rounded-full bg-narrv-500 text-white text-sm disabled:opacity-50">Envoyer</button>
                        </div>
                    </div>
                </div>

                <!-- Translate tab -->
                <div x-show="tab === 'translate'" x-data="transcriptViewer(video.transcript)">
                    <div class="flex gap-2 mb-4">
                        <select x-model="targetLang" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <option value="en">Anglais</option>
                            <option value="fr">Français</option>
                            <option value="es">Espagnol</option>
                            <option value="it">Italien</option>
                            <option value="de">Allemand</option>
                        </select>
                        <button @click="translate()" :disabled="translating"
                                class="px-6 py-2 rounded-full bg-narrv-500 text-white text-sm disabled:opacity-50">
                            <span x-show="!translating">Traduire</span>
                            <span x-show="translating">Traduction...</span>
                        </button>
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>
                    <div x-show="translation" class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-sm whitespace-pre-wrap" x-text="translation"></div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('videoDetail', () => ({
            video: null,
            tab: 'transcript',
            init() {
                const id = window.location.pathname.split('/').pop();
                this.loadVideo(id);
            },
            async loadVideo(id) {
                try {
                    const res = await fetch(`/api/videos/${id}`);
                    if (!res.ok) throw new Error('Impossible de charger la video');
                    const video = await res.json();
                    Alpine.store('app').currentVideo = video;
                    this.video = video;
                    if (this.video.status === 'pending' || this.video.status === 'processing') {
                        setTimeout(() => this.loadVideo(id), 3000);
                    }
                } catch(e) { console.error(e); }
            }
        }));
    });
</script>
@endsection
