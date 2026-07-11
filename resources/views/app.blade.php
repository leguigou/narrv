<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Narrv - Comprendre les vidéos YouTube avec IA')</title>
    <script>
        // Dark mode init avant rendu
        if (localStorage.getItem('darkMode') === 'true' ||
            (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 min-h-screen">
    <div id="app"
         x-data="{
             darkMode: document.documentElement.classList.contains('dark'),
             toggleDark() {
                 this.darkMode = !this.darkMode;
                 document.documentElement.classList.toggle('dark', this.darkMode);
                 localStorage.setItem('darkMode', this.darkMode);
             }
         }"
         class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="sticky top-0 z-50 bg-white/80 dark:bg-gray-950/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2 font-bold text-xl tracking-tight">
                    <span class="w-9 h-9 rounded-lg bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200 flex items-center justify-center dark:bg-cyan-950 dark:text-cyan-200 dark:ring-cyan-800">
                        <svg class="h-7 w-7" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="8" y="10" width="48" height="44" rx="13" />
                            <path d="M25 24.5v15l12-7.5-12-7.5Z" />
                            <path d="M18 45h28" />
                            <path d="M18 19h9" />
                            <path d="M42 20v5" />
                            <path d="M48 18v9" />
                            <path d="M54 21v3" />
                        </svg>
                    </span>
                    Narrv
                </a>
                <nav class="flex items-center gap-4 text-sm">
                    <a href="/bibliotheque" class="px-3 py-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Bibliothèque</a>
                    <button @click="toggleDark"
                            :aria-label="darkMode ? 'Activer le mode clair' : 'Activer le mode sombre'"
                            :title="darkMode ? 'Mode clair' : 'Mode sombre'"
                            class="px-3 py-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors focus:outline-none focus:ring-2 focus:ring-cyan-500/30">
                        <span x-text="darkMode ? '☀️' : '🌙'"></span>
                    </button>
                    <a href="/admin" class="px-3 py-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Admin</a>
                </nav>
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 dark:border-gray-800 py-6 text-center text-sm text-gray-500">
            Narrv &mdash; Comprendre les vidéos YouTube avec IA
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
