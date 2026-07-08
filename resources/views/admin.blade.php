@extends('app')

@section('title', 'Admin - Narrv')

@section('content')
<div x-data="adminPanel()" class="mx-auto max-w-5xl px-4 py-12">
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

    <template x-if="token">
        <div>
            <div class="mb-8 flex items-center justify-between">
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                <button @click="logout" class="rounded-full bg-gray-100 px-4 py-2 text-sm dark:bg-gray-800">Deconnexion</button>
            </div>

            <div class="mb-8 grid grid-cols-2 gap-3 md:grid-cols-4" x-show="stats">
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-narrv-500" x-text="stats.videos_count"></div>
                    <div class="text-xs text-gray-500">Videos</div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-center dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-green-500" x-text="stats.ready_videos"></div>
                    <div class="text-xs text-gray-500">Pretes</div>
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

            <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Cookies YouTube</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Importez un fichier cookies.txt pour aider yt-dlp quand YouTube demande une connexion.</p>
                    </div>
                    <div class="rounded-full px-3 py-1 text-xs font-medium"
                         :class="cookiesStatus?.configured ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300'"
                         x-text="cookiesStatus?.configured ? 'Configure' : 'Non configure'"></div>
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
                            <div class="text-gray-500 dark:text-gray-400">Cookies utilises par yt-dlp</div>
                            <div class="font-medium text-gray-950 dark:text-white" x-text="cookiesDiagnostic?.diagnostic?.cookies?.using_cookies ? 'Oui' : 'Non'"></div>
                        </div>
                        <div>
                            <div class="text-gray-500 dark:text-gray-400">Resultat yt-dlp</div>
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
                            <div class="text-gray-500 dark:text-gray-400">Metadonnees</div>
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

            <div class="mb-8">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Prompts IA</h2>
                    <button @click="loadPrompts" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Rafraichir</button>
                </div>

                <div x-show="promptMessage" x-text="promptMessage" class="mb-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="promptError" x-text="promptError" class="mb-3 text-sm text-red-500"></div>

                <template x-if="promptsLoading">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">Chargement...</div>
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
                                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Defaut</button>
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

            <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Dernieres videos</h2>
                    <button @click="loadDashboard" class="rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-white dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Rafraichir</button>
                </div>

                <div x-show="videoActionMessage" x-text="videoActionMessage" class="mt-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="videoActionError" x-text="videoActionError" class="mt-3 text-sm text-red-500"></div>

                <div class="mt-4 space-y-3">
                    <template x-if="videosLoading">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Chargement...</div>
                    </template>

                    <template x-if="!videosLoading && videos.length === 0">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Aucune video pour le moment.</div>
                    </template>

                    <template x-for="video in videos" :key="video.id">
                        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(video.status)" x-text="statusLabel(video.status)"></span>
                                        <span class="text-xs text-gray-500" x-text="video.youtube_id"></span>
                                    </div>
                                    <h3 class="mt-2 truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="video.title || video.url"></h3>
                                    <p x-show="video.error_message" class="mt-2 line-clamp-3 text-xs leading-5 text-red-600 dark:text-red-300" x-text="video.error_message"></p>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <button x-show="video.status === 'error'"
                                            @click="retryVideo(video)"
                                            class="rounded-lg bg-narrv-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-narrv-600">Relancer</button>
                                    <button @click="deleteVideo(video)"
                                            class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Supprimer</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="mb-8 flex gap-3">
                <button @click="purgeAll" class="rounded-full bg-red-500 px-5 py-2 text-sm text-white">Purger tout</button>
            </div>
        </div>
    </template>
</div>
@endsection
