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
                <div class="mb-4 flex aspect-video w-full items-center justify-center overflow-hidden rounded-2xl bg-gray-100 dark:bg-gray-800 relative">
                    <!-- Player integre -->
                    <template x-if="video.youtube_id && playing">
                        <iframe :src="playerSrc"
                                allow="autoplay; encrypted-media; fullscreen"
                                allowfullscreen
                                class="absolute inset-0 h-full w-full">
                        </iframe>
                    </template>
                    <!-- Miniature cliquable -->
                    <template x-if="!playing">
                        <div class="relative h-full w-full cursor-pointer group" @click="playVideo()">
                            <template x-if="hasThumbnail">
                                <img :src="video.thumbnail_url"
                                     :alt="video.title || 'Miniature video'"
                                     x-on:error="thumbnailFailed = true"
                                     class="h-full w-full object-cover">
                            </template>
                            <div x-show="!hasThumbnail" class="flex h-full w-full flex-col items-center justify-center px-6 text-center bg-gray-100 dark:bg-gray-800">
                                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-400/10 text-cyan-300">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" class="h-7 w-7 fill-none stroke-current stroke-2">
                                        <rect x="4" y="5" width="16" height="14" rx="3"></rect>
                                        <path d="M10 9.5v5l4.5-2.5-4.5-2.5Z"></path>
                                    </svg>
                                </div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Video non disponible</div>
                            </div>
                            <!-- Play button overlay -->
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-black/60 text-white transition group-hover:bg-narrv-500 sm:h-20 sm:w-20">
                                    <svg viewBox="0 0 24 24" class="h-8 w-8 fill-current sm:h-10 sm:w-10">
                                        <path d="M8 5v14l11-7z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <h1 class="text-2xl font-bold" x-text="video.title || 'Video en analyse'"></h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1" x-show="video.channel_name" x-text="video.channel_name"></p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-md bg-gray-100 px-2.5 py-1 font-mono text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                          x-text="`ID YouTube: ${video.youtube_id}`"></span>
                    <span x-show="video.duration"
                          class="rounded-md bg-gray-100 px-2.5 py-1 font-mono text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                          x-text="formatDuration(video.duration)"></span>
                    <a :href="video.youtube_url || video.url"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="rounded-md border border-gray-200 px-2.5 py-1 font-medium text-gray-600 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-cyan-600 dark:hover:text-cyan-200">
                        Ouvrir sur YouTube
                    </a>
                </div>

                <!-- Infos detaillees -->
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Duree</div>
                        <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white" x-text="formatDuration(video.duration) || '-'"></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Chaine</div>
                        <div class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="video.channel_name || '-'"></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Publiée le</div>
                        <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white" x-text="formatPublicationDate(video.published_at) || 'Indisponible'"></div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Chapitres</div>
                        <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white" x-text="chapterCount + ' chapitre' + (chapterCount > 1 ? 's' : '')"></div>
                    </div>
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
                        <button x-show="hasTranscript" @click="tab = 'summary'" :class="tab === 'summary' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Résumé</button>
                        <button x-show="hasTranscript" @click="tab = 'chat'" :class="tab === 'chat' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Chat IA</button>
                        <button x-show="hasTranscript" @click="tab = 'translate'" :class="tab === 'translate' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Traduire</button>
                        <button @click="tab = 'download'" :class="tab === 'download' ? 'border-b-2 border-narrv-500 text-narrv-500' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 border-b-2 border-transparent'" class="shrink-0 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors">Télécharger</button>
                    </div>
                </div>

                <!-- Transcript tab -->
                <div x-show="tab === 'transcript'">
                    <!-- Boutons de téléchargement seulement si transcript dispo -->
                    <div x-show="hasTranscript" class="flex flex-wrap gap-2 mb-4">
                        <button @click="downloadTranscript('txt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .txt</button>
                        <button @click="downloadTranscript('vtt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .vtt</button>
                        <button @click="downloadTranscript('srt')" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700">📥 .srt</button>
                    </div>

                    <!-- Chapitres (toujours affichés si présents) -->
                    <div x-show="chapterCount > 0" class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                        <button @click="chaptersOpen = !chaptersOpen"
                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition hover:bg-gray-100 dark:hover:bg-gray-800"
                                :aria-expanded="chaptersOpen.toString()">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Chapitrage</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400" x-text="chapterCount + ' repère' + (chapterCount > 1 ? 's' : '') + ' de navigation'"></span>
                            </span>
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-200 transition dark:bg-gray-950 dark:ring-gray-800"
                                  :class="chaptersOpen ? 'rotate-180 text-narrv-500' : ''">
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </span>
                        </button>
                        <div x-show="chaptersOpen"
                             x-transition
                             class="flex flex-wrap gap-2 border-t border-gray-200 px-4 py-4 dark:border-gray-800">
                            <template x-for="chapter in videoChapters" :key="chapter.start_time + '-' + chapter.title">
                                <button @click="playVideo(chapter.start_time)"
                                        class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-narrv-700 ring-1 ring-narrv-100 transition hover:bg-narrv-100 dark:bg-gray-950 dark:text-narrv-300 dark:ring-narrv-900 dark:hover:bg-narrv-950">
                                    <span class="font-mono" x-text="formatTime(chapter.start_time)"></span>
                                    <span x-text="chapter.title"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Transcript</h2>
                    <!-- Transcript complet si dispo -->
                    <div x-show="hasTranscript && hasSegmentTranscript" class="space-y-2">
                        <template x-for="block in transcriptBlocks" :key="block.index">
                            <div @click="playVideo(block.start)"
                                 class="group cursor-pointer rounded-lg px-3 py-2 -mx-3 transition hover:bg-gray-100 dark:hover:bg-gray-800">
                                <p class="text-sm leading-7 text-gray-900 dark:text-gray-100">
                                    <span class="mr-3 inline-flex align-baseline font-mono text-xs text-gray-400 dark:text-gray-500 group-hover:text-narrv-500"
                                          x-text="formatTime(block.start)"></span>
                                    <span x-text="block.text"></span>
                                </p>
                            </div>
                        </template>
                    </div>
                    <!-- Fallback si transcript mais pas de segments -->
                    <div x-show="hasTranscript && !hasSegmentTranscript && transcriptText" class="prose dark:prose-invert max-w-none whitespace-pre-wrap text-sm leading-relaxed" x-text="transcriptText"></div>
                    <!-- Message si pas de transcript du tout -->
                    <div x-show="!hasTranscript"
                         class="rounded-xl border border-dashed border-gray-300 bg-amber-50 p-8 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                        <div class="mb-3 text-3xl">📝</div>
                        <p class="font-medium text-gray-900 dark:text-white mb-1">Pas de transcript disponible</p>
                        <p class="text-gray-500 dark:text-gray-400">Cette vidéo ne possède pas de sous-titres sur YouTube. Tu peux quand même la regarder et la télécharger.</p>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Un jour peut-être des sous-titres seront ajoutés, réessaie plus tard.</p>
                    </div>
                </div>

                <!-- Summary tab (masqué si pas de transcript) -->
                <div x-show="hasTranscript && tab === 'summary'" x-data="summaryPanel()" x-init="loadSummaries()">
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

                <!-- Chat tab (masqué si pas de transcript) -->
                <div x-show="hasTranscript && tab === 'chat'" x-data="chatInterface()" x-init="loadHistory()">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-narrv-500 text-sm font-bold text-white">IA</div>
                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-semibold text-gray-950 dark:text-white">Chat IA</h2>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="video.title || 'Video en analyse'"></p>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800">Transcript prêt</span>
                        </div>

                        <div class="h-[28rem] overflow-y-auto bg-white p-4 dark:bg-gray-950 sm:p-5" x-ref="chatbox">
                            <div x-show="messages.length === 0 && !loading" class="flex h-full flex-col items-center justify-center px-4 text-center">
                                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-narrv-50 text-narrv-600 ring-1 ring-narrv-100 dark:bg-narrv-950 dark:text-narrv-300 dark:ring-narrv-900">IA</div>
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Interrogez le transcript</h3>
                                <p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">Posez une question précise ou partez d'une suggestion.</p>
                                <div class="mt-5 grid w-full max-w-2xl gap-2 sm:grid-cols-3">
                                    <template x-for="suggestion in suggestions" :key="suggestion">
                                        <button @click="useSuggestion(suggestion)"
                                                class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-left text-xs font-medium leading-5 text-gray-700 transition hover:border-narrv-200 hover:bg-narrv-50 hover:text-narrv-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-narrv-900 dark:hover:bg-narrv-950 dark:hover:text-narrv-300"
                                                x-text="suggestion"></button>
                                    </template>
                                </div>
                            </div>

                            <div class="space-y-5" x-show="messages.length > 0 || loading">
                            <template x-for="msg in messages" :key="msg.id">
                                <div class="flex gap-3" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                    <div x-show="msg.role === 'assistant'" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">IA</div>

                                    <div class="max-w-[88%] sm:max-w-[76%]">
                                        <div class="mb-1 flex items-center gap-2 text-xs text-gray-400" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                            <span x-text="msg.role === 'user' ? 'Vous' : 'Narrv IA'"></span>
                                            <span x-show="messageTime(msg)" x-text="messageTime(msg)"></span>
                                        </div>
                                        <div :class="msg.role === 'user'
                                                ? 'rounded-2xl rounded-tr-md bg-narrv-500 px-4 py-3 text-white shadow-sm'
                                                : 'rounded-2xl rounded-tl-md border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100'"
                                             class="text-sm leading-6">
                                            <div x-show="msg.role === 'user'" class="whitespace-pre-wrap" x-text="msg.content"></div>
                                            <div x-show="msg.role === 'assistant'" class="prose prose-sm max-w-none dark:prose-invert prose-p:my-2 prose-ul:my-2 prose-ol:my-2" x-html="renderMarkdown(msg.content)"></div>
                                        </div>
                                        <div x-show="msg.role === 'assistant'" class="mt-2 flex justify-end">
                                            <button @click="copyToClipboard(msg)"
                                                    class="rounded-full px-3 py-1 text-xs font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                                    x-text="copiedMessageId === msg.id ? 'Copié' : 'Copier'"></button>
                                        </div>
                                    </div>

                                    <div x-show="msg.role === 'user'" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-narrv-100 text-xs font-bold text-narrv-700 dark:bg-narrv-950 dark:text-narrv-300">VO</div>
                                </div>
                            </template>
                            <div x-show="loading" class="flex items-start gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">IA</div>
                                <div class="rounded-2xl rounded-tl-md border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-gray-400"></span>
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-gray-400 [animation-delay:120ms]"></span>
                                        <span class="h-2 w-2 animate-pulse rounded-full bg-gray-400 [animation-delay:240ms]"></span>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>

                        <div x-show="error" x-text="error"
                             class="border-t border-red-100 bg-red-50 px-4 py-2 text-sm text-red-600 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-400">
                        </div>

                        <div class="border-t border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="flex items-end gap-2 rounded-2xl border border-gray-200 bg-white p-2 shadow-sm focus-within:ring-2 focus-within:ring-narrv-500/30 dark:border-gray-800 dark:bg-gray-950">
                                <textarea x-model="input"
                                          @keydown.enter.exact.prevent="send"
                                          :disabled="loading"
                                          rows="1"
                                          placeholder="Posez une question sur la vidéo..."
                                          class="max-h-28 min-h-10 flex-1 resize-none border-0 bg-transparent px-3 py-2 text-sm leading-6 text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-gray-100"></textarea>
                            <button @click="send" :disabled="loading"
                                        class="flex h-10 shrink-0 items-center justify-center rounded-xl bg-narrv-500 px-4 text-sm font-medium text-white transition hover:bg-narrv-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-show="!loading">Envoyer</span>
                                    <span x-show="loading">...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Translate tab (masqué si pas de transcript) -->
                <div x-show="hasTranscript && tab === 'translate'" x-data="transcriptViewer(video.transcript)">
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
            playing: false,
            seekTo: null,
            adminToken: null,
            tab: 'transcript',
            chaptersOpen: false,
            init() {
                this.adminToken = localStorage.getItem('narrv_admin_token') || null;
                const id = window.location.pathname.split('/').pop();
                this.loadVideo(id);
            },
            get hasThumbnail() {
                return Boolean(this.video?.thumbnail_url && !this.thumbnailFailed);
            },
            get transcriptSegments() {
                return this.asArray(this.video?.transcript?.segments_json);
            },
            get videoChapters() {
                return this.asArray(this.video?.chapters_json);
            },
            get chapterCount() {
                return this.videoChapters.length;
            },
            get hasTranscript() {
                return Boolean(this.video?.transcript?.id);
            },
            get hasSegmentTranscript() {
                return this.transcriptSegments.length > 0;
            },
            get transcriptText() {
                return (this.video?.transcript?.full_text || '').trim();
            },
            get transcriptBlocks() {
                const segments = this.transcriptSegments;
                if (!segments || !segments.length) return [];

                const maxParagraphLength = 900;
                const blocks = [];
                let buffer = '';
                let startTime = null;

                const pushText = () => {
                    const text = buffer.trim();
                    if (!text) return;

                    blocks.push({
                        start: startTime ?? 0,
                        text,
                        index: blocks.length
                    });

                    buffer = '';
                    startTime = null;
                };

                for (const seg of segments) {
                    const segStart = Number(seg.start || 0);
                    const segText = String(seg.text || '').trim();
                    if (!segText) continue;

                    if (startTime === null) startTime = segStart;

                    buffer += (buffer ? ' ' : '') + segText;

                    if (/[.!?…。]$/.test(segText)) {
                        pushText();
                    } else if (buffer.length >= maxParagraphLength && /[,;:]$/.test(segText)) {
                        pushText();
                    }
                }

                pushText();

                return blocks;
            },
            asArray(value) {
                if (Array.isArray(value)) return value;

                if (typeof value === 'string' && value.trim() !== '') {
                    try {
                        const parsed = JSON.parse(value);
                        return Array.isArray(parsed) ? parsed : [];
                    } catch {
                        return [];
                    }
                }

                return [];
            },
            get playerSrc() {
                if (!this.video?.youtube_id) return '';
                let src = `https://www.youtube.com/embed/${this.video.youtube_id}?autoplay=1`;
                if (this.seekTo != null) {
                    src += `&start=${Math.floor(this.seekTo)}`;
                }
                return src;
            },
            get thumbnailPlaceholderTitle() {
                if (this.video?.status === 'pending' || this.video?.status === 'processing') {
                    return 'Recuperation de la video en cours';
                }

                return 'Miniature indisponible';
            },
            playVideo(seconds) {
                if (this.video?.youtube_id) {
                    this.seekTo = seconds != null ? seconds : null;
                    if (this.playing && seconds != null) {
                        this.playing = false;
                        this.$nextTick(() => {
                            this.playing = true;
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        });
                    } else {
                        this.playing = true;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            },
            downloadTranscript(format) {
                if (!this.video?.id) return;
                window.open(`/api/videos/${this.video.id}/transcript/download?format=${format}`, '_blank');
            },
            formatTime(seconds) {
                if (!seconds && seconds !== 0) return '';
                const m = Math.floor(seconds / 60);
                const s = Math.floor(seconds % 60);
                return m + ':' + s.toString().padStart(2, '0');
            },
            formatDuration(seconds) {
                if (!seconds && seconds !== 0) return '';
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = Math.floor(seconds % 60);
                if (h > 0) return h + 'h ' + m + 'm ' + s + 's';
                if (m > 0) return m + 'min ' + s + 's';
                return s + 's';
            },
            formatDate(dateStr) {
                if (!dateStr) return '';
                try {
                    return new Date(dateStr).toLocaleDateString('fr-FR', {
                        day: 'numeric', month: 'short', year: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });
                } catch { return dateStr; }
            },
            formatPublicationDate(dateStr) {
                if (!dateStr) return '';
                try {
                    return new Date(dateStr).toLocaleDateString('fr-FR', {
                        day: 'numeric', month: 'long', year: 'numeric'
                    });
                } catch { return dateStr; }
            },
            async loadVideo(id) {
                try {
                    const headers = { 'Accept': 'application/json' };
                    if (this.adminToken) {
                        headers.Authorization = `Bearer ${this.adminToken}`;
                    }

                    const res = await fetch(`/api/videos/${id}`, { headers });
                    if (!res.ok) {
                        if (res.status === 404) {
                            this.notFound = true;
                            return;
                        }
                        throw new Error('Impossible de charger la video');
                    }
                    const video = await res.json();
                    this.notFound = false;
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
