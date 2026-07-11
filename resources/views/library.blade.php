@extends('app')

@section('title', 'Bibliothèque — Narrv')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-8" x-data="libraryBrowser()">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">Bibliothèque</h1>
        <p class="mt-2 text-gray-500 dark:text-gray-400">Parcourez toutes les vidéos analysées et recherchez dans les transcripts.</p>
    </div>

    <!-- Search bar -->
    <div class="mb-8">
        <div class="flex flex-col gap-3">
            <form @submit.prevent="updateSearch()" class="relative w-full">
                <input type="text"
                       x-model="query"
                       @input.debounce.500ms="updateSearch()"
                       @keydown.enter.prevent="updateSearch()"
                       placeholder="Rechercher dans les titres, transcripts et traductions..."
                       class="h-14 w-full rounded-full border border-gray-200 bg-white px-5 py-3 pr-16 text-sm transition focus:border-narrv-500 focus:outline-none focus:ring-2 focus:ring-narrv-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                <button type="submit"
                        :disabled="loading"
                        title="Rechercher"
                        class="absolute right-2 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-narrv-500 text-white shadow-sm transition hover:bg-narrv-600 focus:outline-none focus:ring-2 focus:ring-narrv-500/30 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-icon name="search" class="h-4 w-4" x-show="!loading" />
                    <span x-show="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                    <span class="sr-only">Rechercher</span>
                </button>
            </form>
            <div class="flex justify-end" x-show="query" x-cloak>
                <button type="button"
                        @click="query = ''; updateSearch()"
                        class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="x" class="h-4 w-4" />
                    Effacer
                </button>
            </div>
        </div>
        <div class="mt-2 text-xs text-gray-400" x-show="total > 0" x-text="`${total} résultat${total > 1 ? 's' : ''}${query ? ' pour « ' + query + ' »' : ''}`"></div>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="py-20 text-center text-gray-400">
        <div class="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-2 border-gray-300 border-t-narrv-500"></div>
        Recherche...
    </div>

    <!-- Results grid -->
    <div x-show="!loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <template x-for="video in videos" :key="video.id">
            <a :href="`/video/${video.id}`"
               class="group rounded-xl border border-gray-200 bg-white p-3 transition hover:border-narrv-300 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-narrv-700">
                <div class="mb-3 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                    <img :src="video.thumbnail_url || '/images/narrv-hero.png'"
                         :alt="video.title || ''"
                         class="aspect-video w-full object-cover transition group-hover:scale-105">
                </div>
                <h3 class="line-clamp-2 text-sm font-semibold text-gray-950 group-hover:text-narrv-600 dark:text-white dark:group-hover:text-narrv-400" x-text="video.title || 'Sans titre'"></h3>
                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400" x-text="video.channel_name || ''"></p>
                <div class="mt-2 flex items-center gap-2 text-xs text-gray-400">
                    <span x-text="video.youtube_id"></span>
                    <span x-show="video.has_transcript" class="rounded-full bg-green-50 px-2 py-0.5 text-green-700 dark:bg-green-950 dark:text-green-300">Transcript</span>
                </div>
            </a>
        </template>
    </div>

    <!-- Empty state -->
    <div x-show="!loading && videos.length === 0"
         class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center dark:border-gray-700 dark:bg-gray-900">
        <div class="text-4xl mb-4">📚</div>
        <p class="text-gray-500 dark:text-gray-400" x-text="query ? 'Aucun résultat pour cette recherche.' : 'Aucune vidéo pour le moment.'"></p>
    </div>

    <!-- Pagination -->
    <div x-show="lastPage > 1" class="mt-8 flex items-center justify-center gap-2">
        <button @click="goToPage(currentPage - 1)" :disabled="currentPage <= 1"
                class="rounded-full border border-gray-200 px-4 py-2 text-sm transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800">
            ← Précédent
        </button>
        <template x-for="page in visiblePages" :key="page">
            <button @click="goToPage(page)"
                    :class="page === currentPage
                        ? 'bg-narrv-500 text-white'
                        : 'border border-gray-200 text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800'"
                    class="rounded-full px-4 py-2 text-sm font-medium transition"
                    x-text="page">
            </button>
        </template>
        <button @click="goToPage(currentPage + 1)" :disabled="currentPage >= lastPage"
                class="rounded-full border border-gray-200 px-4 py-2 text-sm transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:hover:bg-gray-800">
            Suivant →
        </button>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('libraryBrowser', () => ({
            videos: [],
            query: '',
            loading: false,
            currentPage: 1,
            lastPage: 1,
            total: 0,
            perPage: 20,

            init() {
                const params = new URLSearchParams(window.location.search);
                const q = params.get('q');
                if (q) {
                    this.query = q;
                }
                this.search();
            },

            updateSearch() {
                this.currentPage = 1;
                this.search();
            },

            async search() {
                this.loading = true;
                try {
                    const url = `/api/videos/search?per_page=${this.perPage}&page=${this.currentPage}${this.query ? '&q=' + encodeURIComponent(this.query) : ''}`;
                    const res = await fetch(url);
                    const data = await res.json();
                    this.videos = data.data || [];
                    this.currentPage = data.current_page || 1;
                    this.lastPage = data.last_page || 1;
                    this.total = data.total || 0;

                    // Update URL without reload
                    const urlObj = new URL(window.location);
                    if (this.query) {
                        urlObj.searchParams.set('q', this.query);
                    } else {
                        urlObj.searchParams.delete('q');
                    }
                    window.history.replaceState({}, '', urlObj);
                } catch (e) {
                    console.error('Search error:', e);
                } finally {
                    this.loading = false;
                }
            },

            goToPage(page) {
                if (page < 1 || page > this.lastPage) return;
                this.currentPage = page;
                this.search();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            get visiblePages() {
                const pages = [];
                const start = Math.max(1, this.currentPage - 2);
                const end = Math.min(this.lastPage, this.currentPage + 2);
                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }
                return pages;
            }
        }));
    });
</script>
@endsection
