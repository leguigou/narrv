@extends('app')

@section('title', 'Admin - Narrv')

@section('content')
<div x-data="adminPanel()" class="mx-auto max-w-3xl px-4 py-12">
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
                    <button x-show="cookiesStatus?.configured"
                            @click="deleteYoutubeCookies"
                            class="rounded-lg border border-red-200 px-5 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Supprimer</button>
                </div>

                <div x-show="cookiesMessage" x-text="cookiesMessage" class="mt-3 text-sm text-green-600 dark:text-green-400"></div>
                <div x-show="cookiesError" x-text="cookiesError" class="mt-3 text-sm text-red-500"></div>
            </div>

            <div class="mb-8 flex gap-3">
                <button @click="purgeAll" class="rounded-full bg-red-500 px-5 py-2 text-sm text-white">Purger tout</button>
            </div>
        </div>
    </template>
</div>
@endsection
