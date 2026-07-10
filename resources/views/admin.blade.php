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
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                                class="rounded-lg bg-narrv-500 px-5 py-2 text-sm font-medium text-white transition hover:bg-narrv-600 disabled:cursor-not-allowed disabled:opacity-60"
                                x-text="uploadingCookies ? 'Import...' : 'Importer'"></button>
                        <button @click="testYoutubeCookies"
                                :disabled="testingCookies"
                                class="rounded-lg border border-gray-200 px-5 py-2 text-sm font-medium text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                x-text="testingCookies ? 'Test...' : 'Tester'"></button>
                        <button x-show="cookiesStatus?.configured"
                                @click="deleteYoutubeCookies"
                                class="rounded-lg border border-red-200 px-5 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Supprimer</button>
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
                    <button @click="loadPrompts" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Rafraîchir</button>
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
                                            class="rounded-lg bg-narrv-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-narrv-600 disabled:cursor-not-allowed disabled:opacity-60"
                                            x-text="savingPromptKey === prompt.key ? '...' : 'Enregistrer'"></button>
                                    <button @click="resetPrompt(prompt)"
                                            :disabled="savingPromptKey === prompt.key"
                                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Défaut</button>
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
                        <button @click="loadVideos" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Rafraîchir</button>
                        <button @click="purgeAll" class="rounded-full bg-red-500 px-4 py-2 text-sm text-white">Purger tout</button>
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
                                       class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        Ouvrir
                                    </a>
                                    <!-- Voir (modal preview) -->
                                    <button @click="viewVideo(video)"
                                            class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                                        Voir
                                    </button>
                                    <!-- Visibilite toggle -->
                                    <button @click="toggleVisibility(video)"
                                            class="rounded-lg px-3 py-2 text-sm font-medium transition"
                                            :class="video.is_visible
                                                ? 'border border-yellow-200 text-yellow-700 hover:bg-yellow-50 dark:border-yellow-800 dark:text-yellow-300 dark:hover:bg-yellow-950'
                                                : 'border border-green-200 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-300 dark:hover:bg-green-950'"
                                            x-text="video.is_visible ? 'Masquer' : 'Afficher'"></button>
                                    <button x-show="video.status === 'error'"
                                            @click="retryVideo(video)"
                                            :disabled="retryingVideoId === video.id"
                                            class="rounded-lg px-3 py-2 text-sm font-medium text-white transition"
                                            :class="retryingVideoId === video.id ? 'bg-narrv-400 cursor-wait' : 'bg-narrv-500 hover:bg-narrv-600'">
                                        <span x-show="retryingVideoId !== video.id">Relancer</span>
                                        <span x-show="retryingVideoId === video.id">⟳ Relance...</span>
                                    </button>
                                    <button @click="deleteVideo(video)"
                                            class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Supprimer</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ====== LOGS ====== -->
            <div x-show="section === 'logs'">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Logs d'erreur</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Les dernières erreurs Laravel enregistrées dans le fichier de logs de l'application.</p>
                        <p class="mt-1 text-xs text-gray-500" x-show="logsMeta">
                            <span x-text="`${logsMeta?.size || 0} octets`"></span>
                            <span> · Dernière écriture </span>
                            <span x-text="formatCookiesDate(logsMeta?.updated_at)"></span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="loadLogs"
                                :disabled="logsLoading"
                                class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                x-text="logsLoading ? 'Chargement...' : 'Rafraîchir'"></button>
                        <button @click="clearLogs"
                                class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Purger</button>
                    </div>
                </div>

                <div x-show="logsMessage" x-text="logsMessage" class="mb-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="logsError" x-text="logsError" class="mb-3 text-sm text-red-500"></div>

                <template x-if="logsLoading">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Chargement des logs...</div>
                </template>

                <template x-if="!logsLoading && !logsError && logs.length === 0">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Aucune erreur enregistrée.</div>
                </template>

                <div class="space-y-3" x-show="!logsLoading && logs.length > 0">
                    <template x-for="log in logs" :key="log.id">
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                            <button @click="toggleLog(log)" class="w-full p-4 text-left transition hover:bg-gray-50 dark:hover:bg-gray-900">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium" :class="logLevelClass(log.level)" x-text="log.level"></span>
                                    <span class="font-mono text-xs text-gray-500" x-text="log.date"></span>
                                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="log.environment"></span>
                                </div>
                                <p class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white" x-text="log.message || 'Erreur sans message'"></p>
                            </button>
                            <pre x-show="expandedLogId === log.id"
                                 x-text="log.raw"
                                 class="max-h-96 overflow-auto border-t border-gray-200 bg-gray-950 p-4 text-xs leading-5 text-gray-100 dark:border-gray-800"></pre>
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
                        <button @click="closePreview()" class="shrink-0 rounded-full p-1 text-2xl text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800">&times;</button>
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
                        <button @click="closePreview()" class="rounded-full border border-gray-200 px-5 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Fermer</button>
                    </div>
                </div>
            </div>
            </template>
        </div>
    </template>
</div>
@endsection
