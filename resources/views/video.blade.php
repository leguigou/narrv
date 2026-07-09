@extends('app')

@section('title', 'Détail vidéo — Narrv')

@section('content')
<div x-data="videoDetail()" class="max-w-4xl mx-auto px-4 py-8">
    <a href="/" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 mb-6">
        ← Retour
    </a>

    <div x-show="!video" x-cloak class="text-center py-20 text-gray-400">
        <div x-show="!notFound" class="animate-spin h-8 w-8 border-2 border-gray-300 border-t-narrv-500 rounded-full mx-auto mb-4"></div>
        <div x-show="!notFound">Chargement...</div>
        <div x-show="notFound" class="max-w-md mx-auto">
            <div class="text-6xl mb-4">🔒</div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Video non disponible</h2>
            <p class="text-gray-500 dark:text-gray-400">Cette video a ete masquee par l'administrateur et n'est pas accessible.</p>
            <a href="/" class="mt-6 inline-flex rounded-full bg-narrv-500 px-6 py-3 text-sm font-medium text-white transition hover:bg-narrv-600">Retour a l'accueil</a>
        </div>
    </div>

    <template x-if="video">
        <div>
            <!-- Video info -->
            <div class="mb-8">
                <div class="mb-4 flex aspect-video w-full items-center justify-center overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800">
                    <template x-if="hasThumbnail">
                        <img :src="video.thumbnail_url"
                             :alt="video.title || 'Miniature video'"
                             x-on:error="thumbnailFailed = true"
                             class="h-full w-full object-cover">
                    </template>
                    <div x-show="!hasThumbnail" class="flex h-full w-full flex-col items-center justify-center px-6 text-center">
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-400/10 text-cyan-300">
                            <svg viewBox="0 0 24 24" aria-hidden="true" class="h-7 w-7 fill-none stroke-current stroke-2">
                                <rect x="4" y="5" width="16" height="14" rx="3"></rect>
                                <path d="M10 9.5v5l4.5-2.5-4.5-2.5Z"></path>
                            </svg>
                        </div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200" x-text="thumbnailPlaceholderTitle"></div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">La miniature apparaitra des que les metadonnees seront disponibles.</div>
                    </div>
                </div>
                <h1 class="text-2xl font-bold" x-text="video.title || 'Video en analyse'"></h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1" x-show="video.channel_name" x-text="video.channel_name"></p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-md bg-gray-100 px-2.5 py-1 font-mono text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                          x-text="`ID YouTube: ${video.youtube_id}`"></span>
                    <a :href="video.youtube_url || video.url"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="rounded-md border border-gray-200 px-2.5 py-1 font-medium text-gray-600 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-cyan-600 dark:hover:text-cyan-200">
                        Ouvrir sur YouTube
                    </a>
                </div>

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
                <!-- Tabs navigation - scrollable on mobile -->
                <div class="relative mb-6">
                    <div class="flex overflow-x-auto flex-nowrap gap-1 border-b border-gray-200 dark:border-gray-700 pb-px scrollbar-hide -mx-4 px-4 sm:mx-0 sm:px-0">
                        <button @click="tab = 'transcript'" :class="tab === 'transcript' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Transcript</button>
                        <button @click="tab = 'summary'" :class="tab === 'summary' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Résumé</button>
                        <button @click="tab = 'chat'" :class="tab === 'chat' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Chat IA</button>
                        <button @click="tab = 'translate'" :class="tab === 'translate' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Traduire</button>
                        <button @click="tab = 'download'" :class="tab === 'download' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Télécharger</button>
                    </div>
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
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center mb-6">
                        <select x-model="tone" class="w-full sm:w-auto px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <option value="neutral">Neutre</option>
                            <option value="formal">Formel</option>
                            <option value="casual">Décontracté</option>
                            <option value="bullet_points">Points clés</option>
                        </select>
                        <select x-model="length" class="w-full sm:w-auto px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <option value="short">Court</option>
                            <option value="medium">Moyen</option>
                            <option value="long">Long</option>
                        </select>
                        <select x-model="language" class="w-full sm:w-auto px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <template x-for="option in languages" :key="option.code">
                                <option :value="option.code" x-text="option.label"></option>
                            </template>
                        </select>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="text-gray-500 shrink-0">Temp:</span>
                            <input type="range" x-model="temperature" min="0" max="1.5" step="0.1" class="w-24 sm:w-20">
                            <span x-text="temperature" class="text-narrv-500 font-mono w-6 text-right"></span>
                        </div>
                        <button @click="generate()" :disabled="loading"
                                class="w-full sm:w-auto px-6 py-2 rounded-full bg-narrv-500 text-white text-sm font-medium disabled:opacity-50">
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
                                    <span x-text="summary.tone"></span> · <span x-text="summary.length"></span> · <span x-text="languageLabel(summary.language || 'fr')"></span> · temp <span x-text="summary.temperature"></span>
                                </div>
                                <div class="prose dark:prose-invert max-w-none text-sm" x-html="renderMarkdown(summary.content)"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Chat tab -->
                <div x-show="tab === 'chat'" x-data="chatInterface()" x-init="loadHistory()">
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div class="h-80 sm:h-96 overflow-y-auto p-4 space-y-3" x-ref="chatbox">
                            <template x-for="msg in messages" :key="msg.id">
                                <div :class="msg.role === 'user' ? 'ml-auto bg-narrv-500 text-white' : 'bg-gray-100 dark:bg-gray-800 dark:text-gray-200'" class="max-w-[90%] sm:max-w-[80%] p-3 rounded-2xl text-sm">
                                    <div x-show="msg.role === 'user'" x-text="msg.content"></div>
                                    <div x-show="msg.role === 'assistant'" class="prose prose-sm dark:prose-invert max-w-none" x-html="renderMarkdown(msg.content)"></div>
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
                                   class="flex-1 min-w-0 px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0 focus:ring-2 focus:ring-narrv-500">
                            <button @click="send" :disabled="loading"
                                    class="shrink-0 px-5 py-2 rounded-full bg-narrv-500 text-white text-sm disabled:opacity-50">Envoyer</button>
                        </div>
                    </div>
                </div>

                <!-- Translate tab -->
                <div x-show="tab === 'translate'" x-data="transcriptViewer(video.transcript)">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
                        <div class="rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200 text-center sm:text-left"
                             x-text="translationPairLabel"></div>
                        <select x-model="targetLang" class="w-full sm:w-auto px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm border-0">
                            <template x-for="language in availableTargetLanguages" :key="language.code">
                                <option :value="language.code" x-text="language.label"></option>
                            </template>
                        </select>
                        <button @click="translate()" :disabled="translating || isSameLanguage"
                                class="w-full sm:w-auto px-6 py-2 rounded-full bg-narrv-500 text-white text-sm disabled:opacity-50">
                            <span x-show="!translating">Traduire</span>
                            <span x-show="translating">Traduction...</span>
                        </button>
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>

                    <!-- Liste des traductions stockees -->
                    <div class="space-y-4" x-show="translations.length > 0">
                        <template x-for="t in translations" :key="t.id || t.target_language">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                        <span x-text="flagFor(t.target_language)"></span>
                                        <span x-text="languageLabel(t.target_language)"></span>
                                    </span>
                                    <span class="text-xs text-gray-400" x-text="t.model || ''"></span>
                                </div>
                                <div class="text-sm whitespace-pre-wrap leading-relaxed text-gray-900 dark:text-gray-100" x-text="t.content"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Download tab -->
                <div x-show="tab === 'download'" x-data="mediaDownloader()" x-init="loadFormats()">
                    <div x-show="loading" class="py-8 text-sm text-gray-500 dark:text-gray-400">Chargement des formats...</div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>

                    <div x-show="formats" class="flex flex-col gap-4 sm:grid sm:grid-cols-2">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="text-lg font-semibold">Video complète</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Télécharge la vidéo avec audio.</p>

                            <select x-model="selectedVideo" class="mt-4 w-full rounded-xl border-0 bg-white px-4 py-3 text-sm dark:bg-gray-800">
                                <option value="best">Meilleure qualité disponible</option>
                                <template x-for="format in formats?.video || []" :key="format.format_id">
                                    <option :value="format.format_id" x-text="`${format.label}${format.filesize ? ' - ' + sizeLabel(format.filesize) : ''}`"></option>
                                </template>
                            </select>

                            <button @click="download('video')" class="mt-4 w-full rounded-xl bg-narrv-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-narrv-600">Télécharger la vidéo</button>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                            <h2 class="text-lg font-semibold">Audio seul</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Extrait la piste audio en MP3.</p>

                            <select x-model="selectedAudio" class="mt-4 w-full rounded-xl border-0 bg-white px-4 py-3 text-sm dark:bg-gray-800">
                                <option value="bestaudio">Meilleure qualité audio</option>
                                <template x-for="format in formats?.audio || []" :key="format.format_id">
                                    <option :value="format.format_id" x-text="`${format.label}${format.filesize ? ' - ' + sizeLabel(format.filesize) : ''}`"></option>
                                </template>
                            </select>

                            <button @click="download('audio')" class="mt-4 w-full rounded-xl bg-gray-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Télécharger l'audio MP3</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('videoDetail', () => ({
            video: null,
            thumbnailFailed: false,
            notFound: false,
            adminToken: null,
            tab: 'transcript',
            init() {
                const params = new URLSearchParams(window.location.search);
                this.adminToken = params.get('admin_token') || null;
                const id = window.location.pathname.split('/').pop();
                this.loadVideo(id);
            },
            get hasThumbnail() {
                return Boolean(this.video?.thumbnail_url && !this.thumbnailFailed);
            },
            get thumbnailPlaceholderTitle() {
                if (this.video?.status === 'pending' || this.video?.status === 'processing') {
                    return 'Recuperation de la video en cours';
                }

                return 'Miniature indisponible';
            },
            async loadVideo(id) {
                try {
                    const url = this.adminToken
                        ? `/api/videos/${id}?admin_token=${this.adminToken}`
                        : `/api/videos/${id}`;
                    const res = await fetch(url);
                    if (!res.ok) {
                        if (res.status === 404) {
                            this.notFound = true;
                            return;
                        }
                        throw new Error('Impossible de charger la video');
                    }
                    const video = await res.json();
                    if (this.video?.thumbnail_url !== video.thumbnail_url) {
                        this.thumbnailFailed = false;
                    }
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
