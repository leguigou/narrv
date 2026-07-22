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
                </div>

                <!-- Infos detaillees -->
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Duree</div>
                        <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white" x-text="formatDuration(video.duration) || '-'"></div>
                    </div>
                    <a :href="video.channel_url || null"
                       target="_blank"
                       rel="noopener noreferrer"
                       @click="if (!video.channel_url) $event.preventDefault()"
                       :aria-disabled="(!video.channel_url).toString()"
                       class="group rounded-xl border border-gray-200 bg-gray-50 p-3 text-center transition hover:border-cyan-300 hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 aria-disabled:cursor-default aria-disabled:hover:border-gray-200 aria-disabled:hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-cyan-700 dark:hover:bg-cyan-950/30 dark:aria-disabled:hover:border-gray-800 dark:aria-disabled:hover:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Chaine</div>
                        <div class="mt-0.5 flex items-center justify-center gap-1 truncate text-sm font-semibold text-gray-950 group-hover:text-cyan-700 dark:text-white dark:group-hover:text-cyan-300">
                            <span class="truncate" x-text="video.channel_name || '-'"></span>
                            <x-icon x-show="video.channel_url" name="external-link" class="h-3.5 w-3.5 shrink-0" />
                        </div>
                    </a>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Publiée le</div>
                        <div class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white" x-text="formatPublicationDate(video.published_at) || 'Indisponible'"></div>
                    </div>
                    <button type="button"
                            @click="goToChapters()"
                            :disabled="chapterCount === 0"
                            class="group rounded-xl border border-gray-200 bg-gray-50 p-3 text-center transition hover:border-cyan-300 hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-cyan-400/40 disabled:cursor-default disabled:opacity-60 disabled:hover:border-gray-200 disabled:hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-cyan-700 dark:hover:bg-cyan-950/30 dark:disabled:hover:border-gray-800 dark:disabled:hover:bg-gray-900">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Chapitres</div>
                        <div class="mt-0.5 flex items-center justify-center gap-1 text-sm font-semibold text-gray-950 group-hover:text-cyan-700 dark:text-white dark:group-hover:text-cyan-300">
                            <span x-text="chapterCount + ' chapitre' + (chapterCount > 1 ? 's' : '')"></span>
                            <x-icon x-show="chapterCount > 0" name="chevron-down" class="h-3.5 w-3.5 shrink-0" />
                        </div>
                    </button>
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
                    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 font-bold text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900">T</div>
                                <div>
                                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Explorer le transcript</h2>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Recherchez un passage, naviguez par chapitre ou exportez les sous-titres.</p>
                                </div>
                            </div>
                        </div>
                        <div x-show="hasTranscript" class="p-5">
                            <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Exporter le transcript</p>
                            <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
                                <button @click="downloadTranscript('txt')" type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-cyan-700 dark:hover:text-cyan-300">TXT</button>
                                <button @click="downloadTranscript('vtt')" type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-cyan-700 dark:hover:text-cyan-300">VTT</button>
                                <button @click="downloadTranscript('srt')" type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-cyan-700 dark:hover:text-cyan-300">SRT</button>
                            </div>
                        </div>
                    </div>

                    <!-- Chapitres (toujours affichés si présents) -->
                    <div id="chapitrage" x-ref="chaptersSection" x-show="chapterCount > 0" class="mb-6 scroll-mt-4 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
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
                             class="border-t border-gray-200 px-4 py-4 dark:border-gray-800">
                            <div x-show="chapterThumbnailsLoading"
                                 class="mb-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-cyan-200 border-t-cyan-600" aria-hidden="true"></span>
                                <span>Création des miniatures en arrière-plan…</span>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                            <template x-for="(chapter, chapterIndex) in videoChapters" :key="chapter.start_time + '-' + chapter.title">
                                <button @click="playVideo(chapter.start_time)"
                                        class="group flex min-w-0 items-stretch overflow-hidden rounded-lg bg-white text-left ring-1 ring-gray-200 transition hover:ring-cyan-300 hover:shadow-sm dark:bg-gray-950 dark:ring-gray-800 dark:hover:ring-cyan-700">
                                    <span class="relative block aspect-video w-28 shrink-0 overflow-hidden bg-gray-200 sm:w-32 dark:bg-gray-800">
                                        <span class="absolute inset-0 flex items-center justify-center text-gray-400 dark:text-gray-600">
                                            <x-icon name="image" class="h-5 w-5" />
                                        </span>
                                        <img x-show="chapter.thumbnail_url"
                                             :src="chapter.thumbnail_url"
                                             :alt="`Aperçu du chapitre ${chapter.title}`"
                                             loading="lazy"
                                             x-on:error="$el.style.display = 'none'"
                                             class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                        <span class="absolute bottom-2 left-2 rounded bg-black/75 px-2 py-0.5 font-mono text-[11px] font-semibold text-white"
                                              x-text="formatTime(chapter.start_time)"></span>
                                    </span>
                                    <span class="flex min-w-0 flex-1 flex-col justify-center p-3">
                                        <span class="block line-clamp-2 text-sm font-semibold text-gray-950 dark:text-white" x-text="chapter.title"></span>
                                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                                              x-text="`Durée : ${formatChapterDuration(chapter, chapterIndex)}`"></span>
                                    </span>
                                </button>
                            </template>
                            </div>
                        </div>
                    </div>

                    <div x-show="hasTranscript" class="mb-5">
                        <label for="transcript-search" class="mb-2 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Rechercher dans le transcript
                        </label>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div class="relative min-w-0 flex-1">
                                <svg viewBox="0 0 24 24" aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-gray-400 stroke-2">
                                    <circle cx="11" cy="11" r="7"></circle>
                                    <path d="m16 16 4 4"></path>
                                </svg>
                                <input id="transcript-search"
                                       type="search"
                                       x-model="transcriptSearch"
                                       @input="handleTranscriptSearch()"
                                       @keydown.enter.prevent="goToSearchResult($event.shiftKey ? -1 : 1)"
                                       placeholder="Rechercher un mot ou une expression..."
                                       autocomplete="off"
                                       class="w-full rounded-xl border border-gray-200 bg-white py-2.5 pl-10 pr-10 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-200 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-yellow-500 dark:focus:ring-yellow-900/50">
                                <button x-show="transcriptSearch"
                                        @click="clearTranscriptSearch()"
                                        type="button"
                                        aria-label="Effacer la recherche"
                                        class="absolute right-2 top-1/2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <div x-show="hasTranscriptSearch" x-cloak class="flex shrink-0 items-center gap-2">
                                <span class="min-w-20 text-center text-xs font-medium text-gray-500 dark:text-gray-400"
                                      role="status"
                                      aria-live="polite"
                                      x-text="transcriptSearchStatus"></span>
                                <div class="flex overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button @click="goToSearchResult(-1)"
                                            type="button"
                                            :disabled="transcriptSearchResults.length === 0"
                                            aria-label="Résultat précédent"
                                            class="inline-flex h-9 w-9 items-center justify-center bg-white text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <span aria-hidden="true">&uarr;</span>
                                    </button>
                                    <button @click="goToSearchResult(1)"
                                            type="button"
                                            :disabled="transcriptSearchResults.length === 0"
                                            aria-label="Résultat suivant"
                                            class="inline-flex h-9 w-9 items-center justify-center border-l border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <span aria-hidden="true">&darr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p x-show="hasTranscriptSearch" x-cloak class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                            Entrée pour avancer, Maj + Entrée pour revenir au résultat précédent.
                        </p>
                    </div>

                    <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Transcript</h2>
                    <!-- Transcript complet si dispo -->
                    <div x-show="hasTranscript && hasSegmentTranscript" class="space-y-2">
                        <template x-for="block in displayedTranscriptBlocks" :key="block.index">
                            <div @click="playVideo(block.start)"
                                 :data-transcript-block="block.index"
                                 :class="transcriptBlockClasses(block.index)"
                                 class="group cursor-pointer rounded-lg px-3 py-2 -mx-3 transition duration-200 hover:bg-gray-100 dark:hover:bg-gray-800">
                                <p class="text-sm leading-7 text-gray-900 dark:text-gray-100">
                                    <span class="mr-3 inline-flex align-baseline font-mono text-xs text-gray-400 dark:text-gray-500 group-hover:text-narrv-500"
                                          x-text="formatTime(block.start)"></span>
                                    <template x-if="!hasTranscriptSearch">
                                        <span x-text="block.text"></span>
                                    </template>
                                    <template x-if="hasTranscriptSearch">
                                        <span x-html="highlightTranscriptText(block.text)"></span>
                                    </template>
                                </p>
                            </div>
                        </template>
                    </div>
                    <div x-show="hasSegmentTranscript && visibleTranscriptBlocks.length > transcriptRenderLimit"
                         x-cloak
                         class="mt-5 text-center">
                        <button @click="loadMoreTranscriptBlocks()"
                                type="button"
                                class="rounded-full bg-gray-100 px-5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                            <span x-text="`Afficher ${Math.min(transcriptRenderBatch, visibleTranscriptBlocks.length - transcriptRenderLimit)} paragraphes supplémentaires`"></span>
                        </button>
                    </div>
                    <!-- Fallback si transcript mais pas de segments -->
                    <div x-show="hasTranscript && !hasSegmentTranscript && transcriptText && (!hasTranscriptSearch || transcriptSearchResults.length > 0)"
                         data-transcript-block="0"
                         :class="transcriptBlockClasses(0)"
                         class="prose dark:prose-invert max-w-none whitespace-pre-wrap rounded-lg px-3 py-2 -mx-3 text-sm leading-relaxed transition duration-200">
                        <template x-if="!hasTranscriptSearch">
                            <span x-text="transcriptText"></span>
                        </template>
                        <template x-if="hasTranscriptSearch">
                            <span x-html="highlightTranscriptText(transcriptText)"></span>
                        </template>
                    </div>
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
                    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 font-bold text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900">R</div>
                                <div>
                                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Créer un résumé</h2>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Adaptez le format du résumé à votre besoin.</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-300">Ton</span>
                                    <select x-model="tone" :disabled="loading" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-200 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:ring-cyan-900/50">
                                        <option value="neutral">Neutre</option>
                                        <option value="formal">Formel</option>
                                        <option value="casual">Décontracté</option>
                                        <option value="bullet_points">Points clés</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-300">Longueur</span>
                                    <select x-model="length" :disabled="loading" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-200 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:ring-cyan-900/50">
                                        <option value="short">Court</option>
                                        <option value="medium">Moyen</option>
                                        <option value="long">Long</option>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-300">Langue</span>
                                    <select x-model="language" :disabled="loading" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-200 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:focus:ring-cyan-900/50">
                                        <template x-for="option in languages" :key="option.code">
                                            <option :value="option.code" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>

                            <div class="mt-5 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label for="summary-creativity" class="text-xs font-semibold text-gray-600 dark:text-gray-300">Créativité</label>
                                    <span class="rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-950 dark:text-cyan-300" x-text="creativityLabel"></span>
                                </div>
                                <input id="summary-creativity" type="range" x-model="temperature" min="0" max="1.5" step="0.1" :disabled="loading" class="w-full accent-cyan-600 disabled:opacity-60">
                                <div class="mt-1 flex justify-between text-[11px] text-gray-400"><span>Fidèle</span><span>Créatif</span></div>
                            </div>

                            <button @click="generate()" type="button" :disabled="loading" :aria-busy="loading.toString()"
                                    class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                                <span x-show="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                <span x-text="loading ? 'Génération du résumé…' : 'Générer le résumé'"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>
                    <div x-show="summaries.length === 0 && !loading" x-cloak
                         class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Aucun résumé pour le moment</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Choisissez vos options puis lancez la génération.</p>
                    </div>
                    <div class="space-y-4">
                        <template x-for="summary in summaries" :key="summary.id">
                            <div class="p-5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                                <div class="mb-3 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-white px-2.5 py-1 font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-gray-700" x-text="toneLabel(summary.tone)"></span>
                                    <span class="rounded-full bg-white px-2.5 py-1 font-medium text-gray-600 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-gray-700" x-text="lengthLabel(summary.length)"></span>
                                    <span class="rounded-full bg-cyan-50 px-2.5 py-1 font-medium text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900" x-text="languageLabel(summary.language || 'fr')"></span>
                                </div>
                                <div class="prose dark:prose-invert max-w-none text-sm" x-html="renderMarkdown(summary.content)"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Chat tab (masqué si pas de transcript) -->
                <div x-show="hasTranscript && tab === 'chat'" x-data="chatInterface()" x-init="loadHistory()">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-sm font-bold text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900">IA</div>
                                <div class="min-w-0">
                                    <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">Interroger la vidéo</h2>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">Posez vos questions à partir du transcript.</p>
                                </div>
                            </div>
                            <span class="hidden shrink-0 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800 sm:inline-flex">Transcript prêt</span>
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
                    <div class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                        <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 font-bold text-indigo-700 ring-1 ring-indigo-100 dark:bg-indigo-950 dark:text-indigo-300 dark:ring-indigo-900">文</div>
                                <div>
                                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Traduire le transcript</h2>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">La traduction enregistrée sera réutilisée automatiquement.</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-5">
                            <div class="grid items-end gap-3 sm:grid-cols-[1fr_auto_1fr]">
                                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950">
                                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">Langue source</span>
                                    <span class="mt-1 flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                                        <span x-text="flagFor(sourceLang)"></span>
                                        <span x-text="sourceLanguageLabel"></span>
                                    </span>
                                </div>
                                <div class="hidden pb-4 text-gray-400 sm:block" aria-hidden="true">→</div>
                                <label class="block rounded-xl border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-900 dark:bg-cyan-950/30">
                                    <span class="mb-1 block text-xs font-semibold text-cyan-700 dark:text-cyan-300">Traduire vers</span>
                                    <select x-model="targetLang" :disabled="translating" class="w-full rounded-lg border border-cyan-200 bg-white px-3 py-2 text-sm font-semibold text-gray-950 outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-200 disabled:opacity-60 dark:border-cyan-800 dark:bg-gray-950 dark:text-white dark:focus:ring-cyan-900/50">
                                        <template x-for="language in availableTargetLanguages" :key="language.code">
                                            <option :value="language.code" x-text="`${flagFor(language.code)} ${language.label}`"></option>
                                        </template>
                                    </select>
                                </label>
                            </div>

                            <button @click="translate()" type="button" :disabled="translating || isSameLanguage" :aria-busy="translating.toString()"
                                    class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-cyan-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-wait disabled:opacity-60 sm:w-auto">
                                <span x-show="translating" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                <span x-text="translating ? 'Traduction en cours…' : (hasStoredTargetTranslation ? 'Afficher la traduction' : `Traduire en ${targetLanguageLabel}`)"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>

                    <div x-show="translation" x-cloak class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-cyan-50 px-3 py-1 text-xs font-medium text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900">
                                <span x-text="flagFor(targetLang)"></span>
                                <span x-text="targetLanguageLabel"></span>
                            </span>
                            <span class="text-xs text-gray-400" x-text="selectedTranslationRecord?.model || ''"></span>
                        </div>
                        <div class="whitespace-pre-wrap text-sm leading-7 text-gray-900 dark:text-gray-100" x-text="translation"></div>
                    </div>
                    <div x-show="!translation && !translating" x-cloak
                         class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-900">
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="`Aucune traduction en ${targetLanguageLabel}`"></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lancez la traduction pour l’enregistrer et la retrouver ici.</p>
                    </div>
                </div>

                <!-- Download tab -->
                <div x-show="tab === 'download'" x-data="mediaDownloader()" x-init="loadFormats()">
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-50 font-bold text-cyan-700 ring-1 ring-cyan-100 dark:bg-cyan-950 dark:text-cyan-300 dark:ring-cyan-900">↓</div>
                            <div>
                                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Télécharger la vidéo</h2>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Choisissez le format vidéo ou récupérez uniquement la piste audio.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-5">
                    <div x-show="loading" class="flex items-center gap-2 py-8 text-sm text-gray-500 dark:text-gray-400">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-cyan-200 border-t-cyan-600" aria-hidden="true"></span>
                        Chargement des formats…
                    </div>
                    <div x-show="error" x-text="error"
                         class="mb-4 px-4 py-2 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    </div>

                    <div x-show="formats" class="flex flex-col gap-4 sm:grid sm:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-950">
                            <h2 class="text-lg font-semibold">Video complète</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Télécharge la vidéo avec audio.</p>

                            <select x-model="selectedVideo" :disabled="downloadingType" class="mt-4 w-full rounded-xl border-0 bg-white px-4 py-3 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-800">
                                <option value="best">Meilleure qualité disponible</option>
                                <template x-for="format in formats?.video || []" :key="format.format_id">
                                    <option :value="format.format_id" x-text="`${format.label}${format.filesize ? ' - ' + sizeLabel(format.filesize) : ''}`"></option>
                                </template>
                            </select>

                            <button @click="download('video')" :disabled="downloadingType" class="mt-4 w-full rounded-xl bg-narrv-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-narrv-600 disabled:cursor-wait disabled:opacity-60">
                                <span x-text="downloadingType === 'video' ? 'Préparation...' : 'Télécharger la vidéo'"></span>
                            </button>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-950">
                            <h2 class="text-lg font-semibold">Audio seul</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Extrait la piste audio en MP3.</p>

                            <select x-model="selectedAudio" :disabled="downloadingType" class="mt-4 w-full rounded-xl border-0 bg-white px-4 py-3 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-800">
                                <option value="bestaudio">Meilleure qualité audio</option>
                                <template x-for="format in formats?.audio || []" :key="format.format_id">
                                    <option :value="format.format_id" x-text="`${format.label}${format.filesize ? ' - ' + sizeLabel(format.filesize) : ''}`"></option>
                                </template>
                            </select>

                            <button @click="download('audio')" :disabled="downloadingType" class="mt-4 w-full rounded-xl bg-gray-950 px-5 py-3 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-wait disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                                <span x-text="downloadingType === 'audio' ? 'Préparation...' : 'Télécharger l’audio MP3'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="downloadingType" x-cloak class="mt-4 rounded-2xl border border-cyan-200 bg-cyan-50/70 p-4 dark:border-cyan-800 dark:bg-cyan-950/30"
                         role="status" aria-live="polite">
                        <div class="mb-2 flex items-center justify-between gap-4 text-sm">
                            <span class="font-medium text-gray-700 dark:text-gray-200" x-text="progressMessage"></span>
                            <span class="tabular-nums font-semibold text-cyan-700 dark:text-cyan-300" x-text="`${Math.round(progress)}%`"></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-white dark:bg-gray-800"
                             role="progressbar" aria-label="Progression du téléchargement" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(progress)">
                            <div class="h-full rounded-full bg-cyan-600 transition-[width] duration-500 ease-out" :style="`width: ${progress}%`"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Gardez cette page ouverte jusqu’au démarrage du fichier.</p>
                    </div>

                    <div x-show="success" x-text="success" x-cloak
                         class="mt-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300"></div>
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
            transcriptSearch: '',
            transcriptSearchPattern: '',
            transcriptSearchTimer: null,
            hasTranscriptSearch: false,
            activeTranscriptSearchResult: -1,
            transcriptSegments: [],
            transcriptBlocks: [],
            visibleTranscriptBlocks: [],
            transcriptSearchResults: [],
            transcriptRenderBatch: 200,
            transcriptRenderLimit: 200,
            chapterRefreshTimer: null,
            init() {
                this.adminToken = localStorage.getItem('narrv_admin_token') || null;
                const id = window.location.pathname.split('/').pop();
                this.loadVideo(id);
            },
            get hasThumbnail() {
                return Boolean(this.video?.thumbnail_url && !this.thumbnailFailed);
            },
            get videoChapters() {
                return this.asArray(this.video?.chapters_json);
            },
            get chapterCount() {
                return this.videoChapters.length;
            },
            get chapterThumbnailsLoading() {
                return ['pending', 'processing'].includes(this.video?.chapter_thumbnails_status);
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
            get transcriptSearchTerms() {
                return [...new Set(
                    this.transcriptSearch
                        .trim()
                        .split(/\s+/u)
                        .map((term) => term.toLocaleLowerCase())
                        .filter(Boolean)
                )];
            },
            get transcriptSearchStatus() {
                const count = this.transcriptSearchResults.length;
                if (count === 0) return 'Aucun résultat';

                const position = Math.max(0, this.activeTranscriptSearchResult) + 1;
                return `${position} / ${count}`;
            },
            get displayedTranscriptBlocks() {
                return this.visibleTranscriptBlocks.slice(0, this.transcriptRenderLimit);
            },
            buildTranscriptBlocks() {
                const segments = this.transcriptSegments;
                if (!segments || !segments.length) return [];

                const minSentences = 2;
                const maxSentences = 3;
                const targetParagraphLength = 320;
                const maxParagraphLength = 650;
                const sentences = this.transcriptSentences(segments);
                const blocks = [];
                let buffer = [];
                let startTime = null;

                const pushText = () => {
                    const text = buffer.map((sentence) => sentence.text).join(' ').trim();
                    if (!text) return;

                    blocks.push({
                        start: startTime ?? 0,
                        text,
                        sentenceCount: buffer.length,
                        sentences: [...buffer],
                        index: blocks.length
                    });

                    buffer = [];
                    startTime = null;
                };

                for (const sentence of sentences) {
                    const nextLength = buffer
                        .map((item) => item.text)
                        .concat(sentence.text)
                        .join(' ')
                        .length;

                    if (
                        (buffer.length >= minSentences && buffer.length >= maxSentences) ||
                        (buffer.length > 0 && nextLength > maxParagraphLength)
                    ) {
                        pushText();
                    }

                    if (startTime === null) startTime = sentence.start;
                    buffer.push(sentence);

                    const currentLength = buffer.map((item) => item.text).join(' ').length;
                    if (
                        buffer.length >= maxSentences ||
                        (buffer.length >= minSentences && currentLength >= targetParagraphLength)
                    ) {
                        pushText();
                    }
                }

                if (buffer.length === 1 && blocks.length > 0) {
                    const previous = blocks[blocks.length - 1];
                    const combinedText = `${previous.text} ${buffer[0].text}`.trim();

                    if (previous.sentenceCount < maxSentences && combinedText.length <= maxParagraphLength + 80) {
                        previous.text = combinedText;
                        previous.sentenceCount += 1;
                        previous.sentences.push(buffer[0]);
                        buffer = [];
                    } else if (previous.sentenceCount === maxSentences) {
                        const movedSentence = previous.sentences.pop();
                        previous.sentenceCount -= 1;
                        previous.text = previous.sentences.map((sentence) => sentence.text).join(' ');
                        buffer.unshift(movedSentence);
                        startTime = movedSentence.start;
                    }
                }

                pushText();

                return blocks.map(({ sentenceCount, sentences: blockSentences, ...block }, index) => ({
                    ...block,
                    index
                }));
            },
            handleTranscriptSearch() {
                window.clearTimeout(this.transcriptSearchTimer);
                this.transcriptSearchTimer = window.setTimeout(() => {
                    this.refreshTranscriptSearch(true);
                }, 120);
            },
            clearTranscriptSearch() {
                window.clearTimeout(this.transcriptSearchTimer);
                this.transcriptSearch = '';
                this.refreshTranscriptSearch(false);
            },
            refreshTranscriptSearch(shouldScroll = false) {
                const terms = this.transcriptSearchTerms;
                this.hasTranscriptSearch = terms.length > 0;
                this.transcriptSearchPattern = terms
                    .sort((first, second) => second.length - first.length)
                    .map((term) => this.escapeRegExp(term))
                    .join('|');

                if (!this.hasTranscriptSearch) {
                    this.transcriptSearchResults = [];
                    this.visibleTranscriptBlocks = this.transcriptBlocks;
                    this.activeTranscriptSearchResult = -1;
                    this.transcriptRenderLimit = this.transcriptRenderBatch;
                    return;
                }

                if (!this.hasSegmentTranscript) {
                    const text = this.transcriptText.toLocaleLowerCase();
                    this.transcriptSearchResults = terms.every((term) => text.includes(term)) ? [0] : [];
                    this.visibleTranscriptBlocks = [];
                } else {
                    const matchingBlocks = [];

                    for (const block of this.transcriptBlocks) {
                        const text = block.text.toLocaleLowerCase();
                        if (terms.every((term) => text.includes(term))) {
                            matchingBlocks.push(block);
                        }
                    }

                    this.visibleTranscriptBlocks = matchingBlocks;
                    this.transcriptSearchResults = matchingBlocks.map((block) => block.index);
                }

                this.activeTranscriptSearchResult = this.transcriptSearchResults.length ? 0 : -1;
                this.transcriptRenderLimit = this.transcriptRenderBatch;
                if (shouldScroll) this.scrollToActiveTranscriptResult();
            },
            loadMoreTranscriptBlocks() {
                this.transcriptRenderLimit += this.transcriptRenderBatch;
            },
            goToSearchResult(direction = 1) {
                const results = this.transcriptSearchResults;
                if (!results.length) return;

                const current = this.activeTranscriptSearchResult < 0 ? 0 : this.activeTranscriptSearchResult;
                this.activeTranscriptSearchResult = (current + direction + results.length) % results.length;
                this.ensureActiveTranscriptResultIsRendered();
                this.scrollToActiveTranscriptResult();
            },
            ensureActiveTranscriptResultIsRendered() {
                const requiredCount = this.activeTranscriptSearchResult + 1;
                if (requiredCount <= this.transcriptRenderLimit) return;

                this.transcriptRenderLimit = Math.ceil(requiredCount / this.transcriptRenderBatch) * this.transcriptRenderBatch;
            },
            scrollToActiveTranscriptResult() {
                this.$nextTick(() => {
                    const blockIndex = this.transcriptSearchResults[this.activeTranscriptSearchResult];
                    if (blockIndex === undefined) return;

                    const element = this.$root.querySelector(`[data-transcript-block="${blockIndex}"]`);
                    element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            },
            transcriptBlockClasses(blockIndex) {
                if (!this.hasTranscriptSearch) return '';

                if (blockIndex === this.transcriptSearchResults[this.activeTranscriptSearchResult]) {
                    return 'bg-yellow-100 ring-2 ring-yellow-400 shadow-sm dark:bg-yellow-900/35 dark:ring-yellow-500';
                }

                return 'bg-yellow-50 ring-1 ring-yellow-200 dark:bg-yellow-900/15 dark:ring-yellow-800';
            },
            highlightTranscriptText(text) {
                const sourceText = String(text || '');
                if (!this.hasTranscriptSearch) return this.escapeHtml(sourceText);

                if (!this.transcriptSearchPattern) return this.escapeHtml(sourceText);

                const expression = new RegExp(this.transcriptSearchPattern, 'giu');
                let highlightedText = '';
                let lastIndex = 0;
                let match;

                while ((match = expression.exec(sourceText)) !== null) {
                    highlightedText += this.escapeHtml(sourceText.slice(lastIndex, match.index));
                    highlightedText += `<mark class="rounded bg-yellow-300 px-0.5 text-gray-950 dark:bg-yellow-400 dark:text-gray-950">${this.escapeHtml(match[0])}</mark>`;
                    lastIndex = match.index + match[0].length;
                }

                return highlightedText + this.escapeHtml(sourceText.slice(lastIndex));
            },
            escapeHtml(text) {
                return text.replace(/[&<>"']/g, (character) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[character]);
            },
            escapeRegExp(text) {
                return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            },
            transcriptSentences(segments) {
                const sentences = [];
                let pendingText = '';
                let pendingStart = null;

                const pushSentence = () => {
                    const text = pendingText.trim();
                    if (!text) return;

                    for (const part of this.splitLongTranscriptSentence(text)) {
                        sentences.push({
                            text: part,
                            start: pendingStart ?? 0
                        });
                    }

                    pendingText = '';
                    pendingStart = null;
                };

                for (const segment of segments) {
                    const start = Number(segment.start || 0);
                    const text = String(segment.text || '').trim();
                    if (!text) continue;

                    for (const part of this.splitTranscriptSentences(text)) {
                        if (pendingStart === null) pendingStart = start;
                        pendingText += `${pendingText ? ' ' : ''}${part}`;

                        if (/[.!?…。][\s'"»”)]*$/.test(part)) {
                            pushSentence();
                        } else if (pendingText.length >= 650) {
                            pushSentence();
                        }
                    }
                }

                pushSentence();
                return sentences;
            },
            splitTranscriptSentences(text) {
                if (typeof Intl?.Segmenter === 'function') {
                    const language = this.video?.transcript?.language || this.video?.language || 'fr';
                    const segmenter = new Intl.Segmenter(language, { granularity: 'sentence' });
                    return Array.from(segmenter.segment(text), ({ segment }) => segment.trim()).filter(Boolean);
                }

                return text.match(/[^.!?…。]+(?:[.!?…。]+(?=\s|$)|$)/gu)
                    ?.map((part) => part.trim())
                    .filter(Boolean) || [text];
            },
            splitLongTranscriptSentence(text) {
                const maxLength = 520;
                if (text.length <= maxLength) return [text];

                const parts = [];
                const words = text.split(/\s+/);
                let current = '';

                for (const word of words) {
                    const candidate = `${current}${current ? ' ' : ''}${word}`;
                    if (current && candidate.length > maxLength) {
                        parts.push(current);
                        current = word;
                    } else {
                        current = candidate;
                    }
                }

                if (current) parts.push(current);
                return parts;
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
            goToChapters() {
                if (this.chapterCount === 0) return;

                this.tab = 'transcript';
                this.chaptersOpen = true;
                this.$nextTick(() => {
                    this.$refs.chaptersSection?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
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
            formatChapterDuration(chapter, index) {
                let duration = Number(chapter?.duration);

                if (!Number.isFinite(duration) || duration <= 0) {
                    const start = Number(chapter?.start_time) || 0;
                    const nextStart = Number(this.videoChapters[index + 1]?.start_time);
                    const end = Number.isFinite(nextStart) ? nextStart : Number(this.video?.duration);
                    duration = Number.isFinite(end) ? Math.max(0, end - start) : 0;
                }

                return this.formatDuration(duration) || 'indisponible';
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
                    this.transcriptSegments = this.asArray(video?.transcript?.segments_json);
                    this.transcriptBlocks = this.buildTranscriptBlocks();
                    this.refreshTranscriptSearch(false);
                    if (this.video.status === 'pending' || this.video.status === 'processing') {
                        setTimeout(() => this.loadVideo(id), 3000);
                    } else {
                        this.scheduleChapterRefresh(id);
                    }
                } catch(e) { console.error(e); }
            },
            scheduleChapterRefresh(id) {
                if (this.chapterRefreshTimer) {
                    clearTimeout(this.chapterRefreshTimer);
                    this.chapterRefreshTimer = null;
                }

                if (this.chapterThumbnailsLoading) {
                    this.chapterRefreshTimer = setTimeout(() => this.refreshChapterThumbnails(id), 2500);
                }
            },
            async refreshChapterThumbnails(id) {
                try {
                    const headers = { 'Accept': 'application/json' };
                    if (this.adminToken) headers.Authorization = `Bearer ${this.adminToken}`;

                    const res = await fetch(`/api/videos/${id}`, { headers });
                    if (!res.ok) return;

                    const video = await res.json();
                    this.video.chapters_json = video.chapters_json;
                    this.video.chapter_thumbnails_status = video.chapter_thumbnails_status;
                    this.scheduleChapterRefresh(id);
                } catch (e) {
                    console.error(e);
                    this.chapterRefreshTimer = setTimeout(() => this.refreshChapterThumbnails(id), 5000);
                }
            },
            destroy() {
                if (this.chapterRefreshTimer) clearTimeout(this.chapterRefreshTimer);
            }
        }));
    });
</script>
@endsection
