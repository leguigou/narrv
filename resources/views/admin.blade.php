@extends('app')

@section('title', 'Admin — Narrv')

@section('content')
<div x-data="adminPanel()" class="max-w-3xl mx-auto px-4 py-12">
    <template x-if="!token">
        <div class="max-w-sm mx-auto">
            <h1 class="text-2xl font-bold mb-6 text-center">🔒 Zone Admin</h1>
            <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
                <input type="password" x-model="password" @keydown.enter="login"
                       placeholder="Mot de passe"
                       class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 mb-3">
                <button @click="login" class="w-full py-3 rounded-xl bg-narrv-500 text-white font-medium">Connexion</button>
                <div x-show="loginError" x-text="loginError" class="mt-3 text-sm text-red-500"></div>
            </div>
        </div>
    </template>

    <template x-if="token">
        <div>
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-2xl font-bold">🔒 Admin Dashboard</h1>
                <button @click="logout" class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-800 text-sm">Déconnexion</button>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-8" x-show="stats">
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-center">
                    <div class="text-2xl font-bold text-narrv-500" x-text="stats.videos_count"></div>
                    <div class="text-xs text-gray-500">Vidéos</div>
                </div>
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-center">
                    <div class="text-2xl font-bold text-green-500" x-text="stats.ready_videos"></div>
                    <div class="text-xs text-gray-500">Prêtes</div>
                </div>
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-center">
                    <div class="text-2xl font-bold text-yellow-500" x-text="stats.pending_videos + stats.processing_videos"></div>
                    <div class="text-xs text-gray-500">En cours</div>
                </div>
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-center">
                    <div class="text-2xl font-bold text-red-500" x-text="stats.error_videos"></div>
                    <div class="text-xs text-gray-500">Erreurs</div>
                </div>
            </div>

            <div class="flex gap-3 mb-8">
                <button @click="purgeAll" class="px-5 py-2 rounded-full bg-red-500 text-white text-sm">🗑️ Purger tout</button>
            </div>
        </div>
    </template>
</div>
@endsection
