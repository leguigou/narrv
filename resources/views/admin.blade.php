@extends('app')

@section('title', 'Admin - Narrv')

@section('content')
<div x-data="adminPanel()" class="mx-auto max-w-5xl px-4 py-12">
    <!-- Login -->
    <template x-if="!token">
        <div class="mx-auto max-w-sm">
            <h1 class="mb-6 text-center text-2xl font-bold">Zone Admin</h1>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-800 dark:bg-gray-900">
                <input type="password"
                       x-model="password"
                       @keydown.enter="login"
                       placeholder="Mot de passe"
                       class="mb-3 w-full rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <button @click="login" class="w-full rounded-lg bg-narrv-500 py-3 font-medium text-white">Connexion</button>
                <div x-show="loginError" x-text="loginError" class="mt-3 text-sm text-red-500"></div>
            </div>
        </div>
    </template>

    <!-- Admin -->
    <template x-if="token">
        <div>
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Administration</h1>
                <button @click="logout" class="rounded-full bg-gray-100 px-4 py-2 text-sm dark:bg-gray-800">Déconnexion</button>
            </div>

            <!-- Sub-navigation -->
            <div class="mb-8 flex flex-wrap gap-2 border-b border-gray-200 pb-4 dark:border-gray-800">
                <template x-for="s in sections" :key="s.id">
                    <button @click="setSection(s.id)"
                            :class="section === s.id
                                ? 'bg-narrv-500 text-white shadow-sm'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                            class="rounded-full px-5 py-2 text-sm font-medium transition-colors">
                        <span x-text="s.icon" class="mr-1.5"></span><span x-text="s.label"></span>
                    </button>
                </template>
            </div>

            <!-- ====== DASHBOARD ====== -->
            <div x-show="section === 'dashboard'">
                <div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4" x-show="stats">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-2xl font-bold text-narrv-500" x-text="stats.videos_count"></div>
                        <div class="text-xs text-gray-500">Vidéos</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-2xl font-bold text-green-500" x-text="stats.ready_videos"></div>
                        <div class="text-xs text-gray-500">Prêtes</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-2xl font-bold text-yellow-500" x-text="stats.pending_videos + stats.processing_videos"></div>
                        <div class="text-xs text-gray-500">En cours</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-2xl font-bold text-red-500" x-text="stats.error_videos"></div>
                        <div class="text-xs text-gray-500">Erreurs</div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="mb-4 text-lg font-semibold text-gray-950 dark:text-white">Accès rapide</h2>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <button @click="setSection('cookies')" class="rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <div class="text-lg">🍪</div>
                            <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">Cookies YouTube</div>
                            <div class="mt-0.5 text-xs text-gray-500" x-text="cookiesStatus?.configured ? 'Configuré' : 'Non configuré'"></div>
                        </button>
                        <button @click="setSection('prompts')" class="rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <div class="text-lg">🤖</div>
                            <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">Prompts IA</div>
                            <div class="mt-0.5 text-xs text-gray-500" x-text="prompts.length + ' prompts'"></div>
                        </button>
                        <button @click="setSection('videos')" class="rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <div class="text-lg">🎬</div>
                            <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">Vidéos</div>
                            <div class="mt-0.5 text-xs text-gray-500" x-text="videos.length + ' récentes'"></div>
                        </button>
                        <button @click="setSection('monitoring')" class="rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <div class="text-lg">🩺</div>
                            <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">Monitoring</div>
                            <div class="mt-0.5 text-xs text-gray-500" x-text="monitoring ? statusLabelFor(monitoring.logs?.status) : 'A verifier'"></div>
                        </button>
                        <button @click="setSection('logs')" class="rounded-xl border border-gray-200 bg-white p-4 text-left transition hover:shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <div class="text-lg">⚠️</div>
                            <div class="mt-1 text-sm font-medium text-gray-900 dark:text-white">Logs</div>
                            <div class="mt-0.5 text-xs text-gray-500" x-text="(logsMeta?.total || 0) + ' erreurs'"></div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ====== COOKIES ====== -->
            <div x-show="section === 'cookies'">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Cookies YouTube</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Importez un fichier cookies.txt pour aider yt-dlp quand YouTube demande une connexion.</p>
                        </div>
                        <div class="rounded-full px-3 py-1 text-xs font-medium"
                             :class="cookiesStatus?.configured ? 'bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800' : 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-200 dark:bg-yellow-950 dark:text-yellow-300 dark:ring-yellow-800'"
                             x-text="cookiesStatus?.configured ? 'Configuré' : 'Non configuré'"></div>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2" x-show="cookiesStatus">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Dernier import</dt>
                            <dd class="font-medium text-gray-900 dark:text-white" x-text="formatCookiesDate(cookiesStatus?.updated_at)"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Taille</dt>
                            <dd class="font-medium text-gray-900 dark:text-white" x-text="`${cookiesStatus?.size || 0} octets`"></dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input x-ref="cookiesInput"
                               type="file"
                               accept=".txt,text/plain"
                               @change="selectCookiesFile"
                               class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:file:bg-gray-700 dark:file:text-gray-100">
                        <button @click="uploadYoutubeCookies"
                                :disabled="uploadingCookies"
                                title="Importer"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-narrv-500 text-lg font-semibold text-white transition hover:bg-narrv-600 disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="uploadingCookies" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                            <x-icon name="upload" class="h-4 w-4" x-show="!uploadingCookies" />
                            <span class="sr-only">Importer</span>
                        </button>
                        <button @click="testYoutubeCookies"
                                :disabled="testingCookies"
                                title="Tester"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            <span x-show="testingCookies" class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-700 dark:border-gray-600 dark:border-t-gray-100" aria-hidden="true"></span>
                            <x-icon name="check" class="h-4 w-4" x-show="!testingCookies" />
                            <span class="sr-only">Tester</span>
                        </button>
                        <button x-show="cookiesStatus?.configured"
                                @click="deleteYoutubeCookies"
                                title="Supprimer"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-red-200 text-lg font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">
                            <x-icon name="trash" class="h-4 w-4" />
                            <span class="sr-only">Supprimer</span>
                        </button>
                    </div>

                    <div x-show="cookiesMessage" x-text="cookiesMessage" class="mt-3 text-sm text-green-600 dark:text-green-400"></div>
                    <div x-show="cookiesError" x-text="cookiesError" class="mt-3 text-sm text-red-500"></div>
                    <div x-show="cookiesDiagnostic"
                         class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Cookies utilisés par yt-dlp</div>
                                <div class="font-medium text-gray-950 dark:text-white" x-text="cookiesDiagnostic?.diagnostic?.cookies?.using_cookies ? 'Oui' : 'Non'"></div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Résultat yt-dlp</div>
                                <div class="font-medium" :class="cookiesDiagnostic?.diagnostic?.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-300'" x-text="cookiesDiagnostic?.diagnostic?.ok ? 'OK' : 'Erreur'"></div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Taille cookies</div>
                                <div class="font-medium text-gray-950 dark:text-white" x-text="`${cookiesDiagnostic?.diagnostic?.cookies?.size || 0} octets`"></div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Code sortie</div>
                                <div class="font-medium text-gray-950 dark:text-white" x-text="cookiesDiagnostic?.diagnostic?.exit_code ?? '-'"></div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Métadonnées</div>
                                <div class="font-medium" :class="cookiesDiagnostic?.diagnostic?.metadata?.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-300'" x-text="cookiesDiagnostic?.diagnostic?.metadata?.ok ? 'OK' : 'Erreur'"></div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Sous-titres</div>
                                <div class="font-medium" :class="cookiesDiagnostic?.diagnostic?.subtitles?.ok ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-300'" x-text="cookiesDiagnostic?.diagnostic?.subtitles?.ok ? `OK (${cookiesDiagnostic?.diagnostic?.subtitles?.language})` : 'Erreur'"></div>
                            </div>
                        </div>
                        <p x-show="cookiesDiagnostic?.diagnostic?.error"
                           class="mt-3 whitespace-pre-wrap text-xs leading-5 text-red-600 dark:text-red-300"
                           x-text="cookiesDiagnostic?.diagnostic?.error"></p>
                        <p x-show="cookiesDiagnostic?.diagnostic?.subtitles?.error"
                           class="mt-3 whitespace-pre-wrap text-xs leading-5 text-red-600 dark:text-red-300"
                           x-text="cookiesDiagnostic?.diagnostic?.subtitles?.error"></p>
                    </div>
                </div>
            </div>

            <!-- ====== PROMPTS ====== -->
            <div x-show="section === 'prompts'">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Prompts IA</h2>
                    <button @click="loadPrompts"
                            title="Rafraîchir"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        <x-icon name="refresh-cw" class="h-4 w-4" />
                        <span class="sr-only">Rafraîchir</span>
                    </button>
                </div>

                <div x-show="promptMessage" x-text="promptMessage" class="mb-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="promptError" x-text="promptError" class="mb-3 text-sm text-red-500"></div>

                <template x-if="promptsLoading">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Chargement...</div>
                </template>

                <template x-if="!promptsLoading && !promptError && prompts.length === 0">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Aucun prompt disponible.</div>
                </template>

                <div class="grid gap-4" x-show="!promptsLoading">
                    <template x-for="prompt in prompts" :key="prompt.key">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-950 dark:text-white" x-text="prompt.label"></h3>
                                    <div class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400" x-text="prompt.key"></div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="savePrompt(prompt)"
                                            :disabled="savingPromptKey === prompt.key"
                                            title="Enregistrer"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-narrv-500 text-lg font-semibold text-white transition hover:bg-narrv-600 disabled:cursor-not-allowed disabled:opacity-60">
                                        <span x-show="savingPromptKey === prompt.key" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                        <x-icon name="check" class="h-4 w-4" x-show="savingPromptKey !== prompt.key" />
                                        <span class="sr-only">Enregistrer</span>
                                    </button>
                                    <button @click="resetPrompt(prompt)"
                                            :disabled="savingPromptKey === prompt.key"
                                            title="Remettre par défaut"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                        <x-icon name="rotate-ccw" class="h-4 w-4" />
                                        <span class="sr-only">Remettre par défaut</span>
                                    </button>
                                </div>
                            </div>
                            <textarea x-model="prompt.content"
                                      rows="8"
                                      spellcheck="false"
                                      class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 font-mono text-xs leading-5 text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ====== VIDEOS ====== -->
            <div x-show="section === 'videos'">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Dernières vidéos</h2>
                    <div class="flex gap-2">
                        <button @click="loadVideos"
                                title="Rafraîchir"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-icon name="refresh-cw" class="h-4 w-4" />
                            <span class="sr-only">Rafraîchir</span>
                        </button>
                        <button @click="purgeAll"
                                title="Purger toutes les vidéos"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-500 text-lg font-semibold text-white transition hover:bg-red-600">
                            <x-icon name="trash" class="h-4 w-4" />
                            <span class="sr-only">Purger toutes les vidéos</span>
                        </button>
                    </div>
                </div>

                <div x-show="videoActionMessage" x-text="videoActionMessage" class="mb-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="videoActionError" x-text="videoActionError" class="mb-3 text-sm text-red-500"></div>

                <div class="space-y-3">
                    <template x-if="videosLoading">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Chargement...</div>
                    </template>

                    <template x-if="!videosLoading && videos.length === 0">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Aucune vidéo pour le moment.</div>
                    </template>

                    <template x-for="video in videos" :key="video.id">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex gap-3 min-w-0 flex-1">
                                    <!-- Thumbnail -->
                                    <div class="shrink-0">
                                        <img :src="video.thumbnail_url || '/images/narrv-hero.png'"
                                             :alt="video.title || ''"
                                             class="h-16 w-28 rounded-lg object-cover bg-gray-100 dark:bg-gray-800">
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(video.status)" x-text="statusLabel(video.status)"></span>
                                            <span class="text-xs text-gray-500" x-text="video.youtube_id"></span>
                                            <!-- Visibilite badge -->
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
                                                  :class="video.is_visible ? 'bg-green-50 text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-300 dark:ring-green-800' : 'bg-gray-100 text-gray-500 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:ring-gray-700'"
                                                  x-text="video.is_visible ? 'Visible' : 'Masquee'"></span>
                                        </div>
                                        <h3 class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="video.title || video.url"></h3>
                                        <p x-show="video.error_message" class="mt-2 line-clamp-2 text-xs leading-5 text-red-600 dark:text-red-300" x-text="video.error_message"></p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <!-- Lien direct -->
                                    <a :href="`/video/${video.id}`"
                                       target="_blank"
                                       title="Ouvrir"
                                       class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <x-icon name="external-link" class="h-4 w-4" />
                                        <span class="sr-only">Ouvrir</span>
                                    </a>
                                    <!-- Voir (modal preview) -->
                                    <button @click="viewVideo(video)"
                                            title="Voir"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <x-icon name="eye" class="h-4 w-4" />
                                        <span class="sr-only">Voir</span>
                                    </button>
                                    <!-- Visibilite toggle -->
                                    <button @click="toggleVisibility(video)"
                                            :title="video.is_visible ? 'Masquer' : 'Afficher'"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-lg font-semibold transition"
                                            :class="video.is_visible
                                                ? 'border border-yellow-200 text-yellow-700 hover:bg-yellow-50 dark:border-yellow-800 dark:text-yellow-300 dark:hover:bg-yellow-950'
                                                : 'border border-green-200 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-300 dark:hover:bg-green-950'"
                                            >
                                        <x-icon name="eye-off" class="h-4 w-4" x-show="video.is_visible" />
                                        <x-icon name="eye" class="h-4 w-4" x-show="!video.is_visible" />
                                        <span class="sr-only" x-text="video.is_visible ? 'Masquer' : 'Afficher'"></span>
                                    </button>
                                    <button @click="askRetryVideo(video)"
                                            :disabled="isRetrying(video.id)"
                                            title="Réanalyser le transcript"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-lg font-semibold text-white transition"
                                            :class="isRetrying(video.id) ? 'bg-narrv-400 cursor-wait' : 'bg-narrv-500 hover:bg-narrv-600'">
                                        <span x-show="isRetrying(video.id)" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                                        <x-icon name="refresh-cw" class="h-4 w-4" x-show="!isRetrying(video.id)" />
                                        <span class="sr-only">Réanalyser le transcript</span>
                                    </button>
                                    <button @click="deleteVideo(video)"
                                            title="Supprimer"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-lg font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">
                                        <x-icon name="trash" class="h-4 w-4" />
                                        <span class="sr-only">Supprimer</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ====== MONITORING ====== -->
            <div x-show="section === 'monitoring'">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Monitoring</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Etat des services critiques, du stockage, des jobs et de la configuration.</p>
                        <p class="mt-1 text-xs text-gray-500" x-show="monitoring">Dernier controle <span x-text="formatCookiesDate(monitoring?.generated_at)"></span></p>
                    </div>
                    <button @click="loadMonitoring"
                            :disabled="monitoringLoading"
                            title="Rafraîchir"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        <span x-show="monitoringLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-700 dark:border-gray-600 dark:border-t-gray-100" aria-hidden="true"></span>
                        <x-icon name="refresh-cw" class="h-4 w-4" x-show="!monitoringLoading" />
                        <span class="sr-only">Rafraîchir</span>
                    </button>
                </div>

                <div x-show="monitoringError" x-text="monitoringError" class="mb-3 text-sm text-red-500"></div>

                <template x-if="monitoringLoading && !monitoring">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Controle des services...</div>
                </template>

                <div x-show="monitoring" class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Application</h3>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.app?.status)" x-text="statusLabelFor(monitoring?.app?.status)"></span>
                            </div>
                            <dl class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <div class="flex justify-between gap-3"><dt>Env</dt><dd class="font-mono" x-text="monitoring?.app?.environment"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Debug</dt><dd x-text="monitoring?.app?.debug ? 'Actif' : 'Inactif'"></dd></div>
                                <div class="flex justify-between gap-3"><dt>PHP</dt><dd class="font-mono" x-text="monitoring?.app?.php_version"></dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Base de donnees</h3>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.database?.status)" x-text="statusLabelFor(monitoring?.database?.status)"></span>
                            </div>
                            <dl class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <div class="flex justify-between gap-3"><dt>Connexion</dt><dd class="font-mono" x-text="monitoring?.database?.connection"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Latence</dt><dd x-text="monitoring?.database?.latency_ms ? monitoring.database.latency_ms + ' ms' : '-'"></dd></div>
                            </dl>
                            <p x-show="monitoring?.database?.message" class="mt-2 line-clamp-3 text-xs text-red-500" x-text="monitoring?.database?.message"></p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Stockage</h3>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.storage?.status)" x-text="statusLabelFor(monitoring?.storage?.status)"></span>
                            </div>
                            <dl class="space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <div class="flex justify-between gap-3"><dt>Utilise</dt><dd x-text="monitoring?.storage?.used_percent !== null ? monitoring.storage.used_percent + '%' : '-'"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Libre</dt><dd x-text="bytesLabel(monitoring?.storage?.free_bytes)"></dd></div>
                                <div class="flex justify-between gap-3"><dt>Ecriture</dt><dd x-text="monitoring?.storage?.writable ? 'OK' : 'Bloquee'"></dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">IA et outils</h3>
                                <span class="max-w-full truncate text-xs text-gray-500">DeepSeek / yt-dlp / ffmpeg</span>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex min-w-0 items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-950 dark:text-white">DeepSeek</div>
                                        <div class="truncate text-xs text-gray-500" x-text="monitoring?.deepseek?.model"></div>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.deepseek?.status)" x-text="monitoring?.deepseek?.configured ? 'Configure' : 'Cle manquante'"></span>
                                </div>
                                <div class="flex min-w-0 items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-950 dark:text-white">yt-dlp</div>
                                        <div class="truncate text-xs text-gray-500" x-text="monitoring?.yt_dlp?.version || monitoring?.yt_dlp?.message"></div>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.yt_dlp?.status)" x-text="statusLabelFor(monitoring?.yt_dlp?.status)"></span>
                                </div>
                                <div class="flex min-w-0 items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-950 dark:text-white">ffmpeg</div>
                                        <div class="truncate text-xs text-gray-500" x-text="monitoring?.ffmpeg?.version || monitoring?.ffmpeg?.message"></div>
                                    </div>
                                    <span class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-xs font-medium" :class="statusPillClass(monitoring?.ffmpeg?.status)" x-text="statusLabelFor(monitoring?.ffmpeg?.status)"></span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Activite</h3>
                                <button @click="setSection('logs')"
                                        title="Voir les logs"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-lg font-semibold text-narrv-500 transition hover:bg-narrv-50 hover:text-narrv-600 dark:hover:bg-narrv-950">
                                    <x-icon name="external-link" class="h-4 w-4" />
                                    <span class="sr-only">Voir les logs</span>
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-white p-3 dark:bg-gray-950">
                                    <div class="text-xl font-bold text-yellow-500" x-text="monitoring?.jobs?.pending_videos + monitoring?.jobs?.processing_videos"></div>
                                    <div class="text-xs text-gray-500">Jobs actifs</div>
                                </div>
                                <div class="rounded-lg bg-white p-3 dark:bg-gray-950">
                                    <div class="text-xl font-bold text-red-500" x-text="monitoring?.jobs?.error_videos"></div>
                                    <div class="text-xs text-gray-500">Videos en erreur</div>
                                </div>
                                <div class="rounded-lg bg-white p-3 dark:bg-gray-950">
                                    <div class="text-xl font-bold" :class="monitoring?.youtube_cookies?.configured ? 'text-green-500' : 'text-yellow-500'" x-text="monitoring?.youtube_cookies?.configured ? 'OK' : 'Non'"></div>
                                    <div class="text-xs text-gray-500">Cookies YouTube</div>
                                </div>
                                <div class="rounded-lg bg-white p-3 dark:bg-gray-950">
                                    <div class="text-xl font-bold" :class="monitoring?.logs?.recent_issues > 0 ? 'text-red-500' : 'text-green-500'" x-text="monitoring?.logs?.recent_issues || 0"></div>
                                    <div class="text-xs text-gray-500">Erreurs recentes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== LOGS ====== -->
            <div x-show="section === 'logs'">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Logs système</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Recherche, filtres et regroupement des erreurs récentes de l'application.</p>
                        <p class="mt-1 text-xs text-gray-500" x-show="logsMeta">
                            <span x-text="bytesLabel(logsMeta?.size)"></span>
                            <span> · Dernière écriture </span>
                            <span x-text="formatCookiesDate(logsMeta?.updated_at)"></span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="loadLogs"
                                :disabled="logsLoading"
                                title="Rafraîchir"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            <span x-show="logsLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-700 dark:border-gray-600 dark:border-t-gray-100" aria-hidden="true"></span>
                            <x-icon name="refresh-cw" class="h-4 w-4" x-show="!logsLoading" />
                            <span class="sr-only">Rafraîchir</span>
                        </button>
                        <button @click="clearLogs"
                                title="Purger"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 text-lg font-semibold text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">
                            <x-icon name="trash" class="h-4 w-4" />
                            <span class="sr-only">Purger</span>
                        </button>
                    </div>
                </div>

                <div x-show="logsMessage" x-text="logsMessage" class="mb-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="logsError" x-text="logsError" class="mb-3 text-sm text-red-500"></div>

                <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid gap-3 md:grid-cols-[160px_180px_1fr_auto]">
                        <select x-model="logLevel" @change="loadLogs" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="all">Tous niveaux</option>
                            <option value="ERROR">Error</option>
                            <option value="CRITICAL">Critical</option>
                            <option value="ALERT">Alert</option>
                            <option value="EMERGENCY">Emergency</option>
                            <option value="WARNING">Warning</option>
                        </select>
                        <select x-model="logSource" @change="loadLogs" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                            <option value="all">Toutes sources</option>
                            <option value="deepseek">IA / DeepSeek</option>
                            <option value="youtube">YouTube / yt-dlp</option>
                            <option value="database">Base de donnees</option>
                            <option value="storage">Stockage</option>
                            <option value="laravel">Laravel</option>
                        </select>
                        <input type="search"
                               x-model="logSearch"
                               @keydown.enter="loadLogs"
                               placeholder="Rechercher dans les logs..."
                               class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950">
                        <button @click="loadLogs"
                                title="Filtrer"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gray-950 text-lg font-semibold text-white dark:bg-white dark:text-gray-950">
                            <x-icon name="search" class="h-4 w-4" />
                            <span class="sr-only">Filtrer</span>
                        </button>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <label class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-gray-600 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-gray-800">
                            <input type="checkbox" x-model="logGrouped" class="rounded border-gray-300 text-narrv-500 focus:ring-narrv-500">
                            Regrouper les erreurs identiques
                        </label>
                        <template x-for="[level, count] in Object.entries(logsMeta?.levels || {})" :key="level">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium" :class="logLevelClass(level)">
                                <span x-text="level"></span>
                                <span x-text="count"></span>
                            </span>
                        </template>
                        <template x-for="[source, count] in Object.entries(logsMeta?.sources || {})" :key="source">
                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-gray-600 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-300 dark:ring-gray-800">
                                <span x-text="sourceLabel(source)"></span>
                                <span x-text="count"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <template x-if="logsLoading">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Chargement des logs...</div>
                </template>

                <template x-if="!logsLoading && !logsError && logsMeta && logsMeta.total === 0">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Aucun log ne correspond aux filtres.</div>
                </template>

                <div class="space-y-3" x-show="!logsLoading && logGrouped && logGroups.length > 0">
                    <template x-for="group in logGroups" :key="group.id">
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                            <button @click="toggleLog(group.sample)" class="w-full p-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="logLevelClass(group.level)" x-text="group.level"></span>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="sourceLabel(group.source)"></span>
                                    <span class="rounded-full bg-narrv-50 px-2 py-1 text-xs font-medium text-narrv-700 dark:bg-narrv-950 dark:text-narrv-300" x-text="group.count + ' occurrence(s)'"></span>
                                    <span class="font-mono text-xs text-gray-500" x-text="group.latest_date"></span>
                                </div>
                                <p class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white" x-text="group.message || 'Erreur sans message'"></p>
                            </button>
                            <div x-show="expandedLogId === group.sample.id" class="border-t border-gray-200 dark:border-gray-800">
                                <div class="flex justify-end bg-gray-50 px-4 py-2 dark:bg-gray-900">
                                    <button @click="copyLog(group.sample)"
                                            title="Copier"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <x-icon name="copy" class="h-4 w-4" />
                                        <span class="sr-only">Copier</span>
                                    </button>
                                </div>
                                <pre x-text="group.sample.raw"
                                 class="max-h-96 overflow-auto border-t border-gray-200 bg-gray-950 p-4 text-xs leading-5 text-gray-100 dark:border-gray-800"></pre>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-3" x-show="!logsLoading && !logGrouped && logs.length > 0">
                    <template x-for="log in logs" :key="log.id">
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                            <button @click="toggleLog(log)" class="w-full p-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="logLevelClass(log.level)" x-text="log.level"></span>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="sourceLabel(log.source)"></span>
                                    <span class="font-mono text-xs text-gray-500" x-text="log.date"></span>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="log.environment"></span>
                                </div>
                                <p class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white" x-text="log.message || 'Erreur sans message'"></p>
                            </button>
                            <div x-show="expandedLogId === log.id" class="border-t border-gray-200 dark:border-gray-800">
                                <div class="flex justify-end bg-gray-50 px-4 py-2 dark:bg-gray-900">
                                    <button @click="copyLog(log)"
                                            title="Copier"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        <x-icon name="copy" class="h-4 w-4" />
                                        <span class="sr-only">Copier</span>
                                    </button>
                                </div>
                                <pre x-text="log.raw"
                                     class="max-h-96 overflow-auto border-t border-gray-200 bg-gray-950 p-4 text-xs leading-5 text-gray-100 dark:border-gray-800"></pre>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Preview modal -->
            <template x-if="previewVideo">
            <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-12" @click.self="closePreview()">
                <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h2 class="text-lg font-bold text-gray-950 dark:text-white" x-text="previewVideo.title || 'Sans titre'"></h2>
                        <button @click="closePreview()"
                                title="Fermer"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                            <x-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Fermer</span>
                        </button>
                    </div>

                    <!-- Thumbnail -->
                    <div class="mb-4 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                        <img :src="previewVideo.thumbnail_url" :alt="previewVideo.title" class="w-full object-cover" x-show="previewVideo.thumbnail_url">
                        <div x-show="!previewVideo.thumbnail_url" class="flex h-40 items-center justify-center text-gray-400 text-sm">Aucune miniature</div>
                    </div>

                    <!-- Meta -->
                    <dl class="mb-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Chaîne</dt>
                            <dd class="font-medium text-gray-900 dark:text-white" x-text="previewVideo.channel_name || '-'"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Durée</dt>
                            <dd class="font-medium text-gray-900 dark:text-white" x-text="previewVideo.duration ? `${Math.floor(previewVideo.duration / 60)}:${(previewVideo.duration % 60).toString().padStart(2, '0')}` : '-'"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">YouTube ID</dt>
                            <dd class="font-mono text-gray-900 dark:text-white" x-text="previewVideo.youtube_id"></dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Statut</dt>
                            <dd class="font-medium" :class="{'text-green-600': previewVideo.status === 'ready', 'text-red-600': previewVideo.status === 'error', 'text-yellow-600': previewVideo.status === 'pending' || previewVideo.status === 'processing'}" x-text="statusLabel(previewVideo.status)"></dd>
                        </div>
                    </dl>

                    <!-- Transcript preview -->
                    <div x-show="previewVideo.transcript?.full_text">
                        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Transcript</h3>
                        <div class="max-h-60 overflow-y-auto whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-4 text-xs leading-5 text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300" x-text="previewVideo.transcript.full_text"></div>
                    </div>

                    <div class="mt-4 flex justify-end gap-2">
                        <button @click="closePreview()"
                                title="Fermer"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-lg font-semibold text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            <x-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Fermer</span>
                        </button>
                    </div>
                </div>
            </div>
            </template>

            <!-- Retry confirmation -->
            <div x-show="retryConfirmVideo"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                 @click.self="cancelRetryVideo()">
                <div x-transition
                     class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Relancer l'analyse ?</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                Le transcript actuel sera supprimé puis régénéré en tâche de fond.
                            </p>
                        </div>
                        <button @click="cancelRetryVideo()"
                                title="Fermer"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                            <x-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Fermer</span>
                        </button>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                        <div class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white" x-text="retryConfirmVideo?.title || retryConfirmVideo?.url || 'Video sans titre'"></div>
                        <div class="mt-1 font-mono text-xs text-gray-500" x-text="retryConfirmVideo?.youtube_id"></div>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button @click="cancelRetryVideo()"
                                class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                            Annuler
                        </button>
                        <button @click="confirmRetryVideo()"
                                class="inline-flex items-center gap-2 rounded-lg bg-narrv-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-narrv-600">
                            <x-icon name="refresh-cw" class="h-4 w-4" />
                            Relancer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Analysis queue toast -->
            <div x-show="analysisJobs.length > 0 && !analysisToastDismissed"
                 x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-6 opacity-0"
                 x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100"
                 x-transition:leave-end="translate-y-6 opacity-0"
                 class="fixed inset-x-3 bottom-3 z-40 sm:left-auto sm:right-5 sm:w-96">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Analyses vidéo</h2>
                            <p class="mt-0.5 text-xs text-gray-500">Récupération YouTube, transcript et mise à jour de la base.</p>
                        </div>
                        <button @click="dismissAnalysisToast()"
                                title="Fermer"
                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">
                            <x-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Fermer</span>
                        </button>
                    </div>

                    <div class="max-h-72 overflow-y-auto p-3">
                        <template x-for="job in analysisJobs" :key="job.id">
                            <div class="rounded-lg px-2 py-2">
                                <div class="flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                                         :class="job.status === 'ready'
                                            ? 'bg-green-50 text-green-600 dark:bg-green-950 dark:text-green-300'
                                            : job.status === 'error'
                                                ? 'bg-red-50 text-red-600 dark:bg-red-950 dark:text-red-300'
                                                : 'bg-narrv-50 text-narrv-600 dark:bg-narrv-950 dark:text-narrv-300'">
                                        <span x-show="job.status === 'pending' || job.status === 'processing'" class="h-4 w-4 animate-spin rounded-full border-2 border-current/30 border-t-current" aria-hidden="true"></span>
                                        <x-icon name="check" class="h-4 w-4" x-show="job.status === 'ready'" />
                                        <x-icon name="x" class="h-4 w-4" x-show="job.status === 'error'" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-gray-950 dark:text-white" x-text="job.title"></div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="analysisJobClass(job.status)" x-text="analysisJobLabel(job.status)"></span>
                                            <span class="font-mono text-xs text-gray-400" x-text="job.youtube_id"></span>
                                        </div>
                                        <p x-show="job.error_message" class="mt-1 line-clamp-2 text-xs text-red-500" x-text="job.error_message"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
