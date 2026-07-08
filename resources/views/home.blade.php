@extends('app')

@section('content')
<div class="bg-white dark:bg-gray-950">
    <section class="relative min-h-[calc(100vh-4rem)] overflow-hidden">
        <img src="/images/narrv-hero.png"
             alt=""
             class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-white/88 dark:bg-gray-950/82"></div>
        <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(255,255,255,0.98)_0%,rgba(255,255,255,0.92)_44%,rgba(255,255,255,0.36)_100%)] dark:bg-[linear-gradient(90deg,rgba(3,7,18,0.98)_0%,rgba(3,7,18,0.9)_44%,rgba(3,7,18,0.36)_100%)]"></div>

        <div class="relative mx-auto flex min-h-[calc(100vh-4rem)] max-w-6xl flex-col justify-center px-4 py-14 sm:px-6 lg:px-8">
            <div class="max-w-3xl" x-data="youtubeInput()">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-sm font-medium text-cyan-800 dark:border-cyan-800 dark:bg-cyan-950 dark:text-cyan-200">
                    YouTube vers transcript, resume et chat IA
                </div>

                <h1 class="max-w-2xl text-4xl font-bold leading-tight tracking-tight text-gray-950 dark:text-white sm:text-5xl lg:text-6xl">
                    Comprenez une video YouTube sans la regarder en entier.
                </h1>

                <p class="mt-5 max-w-2xl text-lg leading-8 text-gray-700 dark:text-gray-300">
                    Collez un lien YouTube. Narrv recupere la video, extrait le transcript, genere des resumes, traduit le contenu et vous laisse poser des questions a l'IA.
                </p>

                <div class="mt-8 max-w-2xl">
                    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-2 shadow-2xl shadow-cyan-900/10 dark:border-gray-700 dark:bg-gray-900 sm:flex-row">
                        <label for="youtube-url" class="sr-only">Lien YouTube</label>
                        <input id="youtube-url"
                               type="url"
                               x-model="url"
                               @keydown.enter="submit"
                               placeholder="https://www.youtube.com/watch?v=..."
                               class="min-h-14 flex-1 rounded-md border-0 bg-gray-50 px-4 text-base text-gray-950 outline-none ring-1 ring-transparent transition focus:bg-white focus:ring-cyan-500 dark:bg-gray-800 dark:text-white dark:focus:bg-gray-950"
                               :class="!isSupportedUrl ? 'ring-red-400' : ''"
                               :disabled="loading">
                        <button @click="submit"
                                :disabled="loading || !detectedId"
                                class="min-h-14 rounded-md bg-cyan-600 px-6 font-semibold text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <span x-show="!loading">Analyser</span>
                            <span x-show="loading">Analyse...</span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        <span x-show="detectedId" class="rounded-md border border-green-200 bg-green-50 px-3 py-1 font-medium text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                            ID detecte : <span x-text="detectedId"></span>
                        </span>
                        <span x-show="url && !detectedId" class="rounded-md border border-red-200 bg-red-50 px-3 py-1 font-medium text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                            Format non reconnu
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">
                            watch, youtu.be, shorts, embed, live et youtube-nocookie
                        </span>
                    </div>

                    <div x-show="error" x-text="error"
                         class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-800">
                    </div>
                    <div x-show="success" x-text="success"
                         class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200 dark:bg-green-950 dark:text-green-200 dark:ring-green-800">
                    </div>

                </div>
            </div>

            <div class="mt-12 grid max-w-5xl gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white/86 p-5 shadow-lg shadow-cyan-900/5 backdrop-blur dark:border-gray-800 dark:bg-gray-900/86">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-2xl font-bold text-cyan-600">01</div>
                        <div class="rounded-md bg-cyan-50 px-2 py-1 text-xs font-semibold text-cyan-700 dark:bg-cyan-950 dark:text-cyan-200">TXT · VTT · SRT</div>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Transcript horodate</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Narrv extrait les sous-titres disponibles et reconstruit un texte lisible avec les timestamps utiles.
                    </p>
                    <p class="mt-4 border-l-2 border-cyan-300 pl-3 text-xs leading-5 text-gray-500 dark:border-cyan-700 dark:text-gray-400">
                        Ideal pour retrouver une citation, preparer des notes ou exporter des sous-titres.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white/86 p-5 shadow-lg shadow-emerald-900/5 backdrop-blur dark:border-gray-800 dark:bg-gray-900/86">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-2xl font-bold text-emerald-600">02</div>
                        <div class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">Court · Moyen · Long</div>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Resume configurable</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Choisissez la longueur, le ton et la temperature pour obtenir une synthese adaptee a votre usage.
                    </p>
                    <p class="mt-4 border-l-2 border-emerald-300 pl-3 text-xs leading-5 text-gray-500 dark:border-emerald-700 dark:text-gray-400">
                        Utile pour comprendre l'essentiel avant de decider si la video vaut le temps.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white/86 p-5 shadow-lg shadow-rose-900/5 backdrop-blur dark:border-gray-800 dark:bg-gray-900/86">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-2xl font-bold text-rose-600">03</div>
                        <div class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-200">FR · EN · ES · IT · DE</div>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Traduction directe</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Transformez un transcript dans une autre langue sans copier-coller le texte dans un outil externe.
                    </p>
                    <p class="mt-4 border-l-2 border-rose-300 pl-3 text-xs leading-5 text-gray-500 dark:border-rose-700 dark:text-gray-400">
                        Pratique pour exploiter des contenus internationaux ou partager une version localisee.
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white/86 p-5 shadow-lg shadow-gray-900/5 backdrop-blur dark:border-gray-800 dark:bg-gray-900/86">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">04</div>
                        <div class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">Questions ciblées</div>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Chat avec la video</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                        Posez des questions sur le contenu et obtenez des reponses basees sur le transcript de la video.
                    </p>
                    <p class="mt-4 border-l-2 border-gray-300 pl-3 text-xs leading-5 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Demandez un plan, une decision, une definition ou les points d'action mentionnes.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-3 lg:px-8">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Pourquoi Narrv</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">Une video devient une base de connaissance.</h2>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-950">
                <h3 class="font-semibold text-gray-950 dark:text-white">Texte exploitable</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">Recuperez un transcript complet avec timestamps et exportez-le en TXT, VTT ou SRT.</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-950">
                <h3 class="font-semibold text-gray-950 dark:text-white">Lecture acceleree</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">Generez un resume court, moyen ou detaille avec le ton adapte a votre usage.</p>
            </div>
            <div class="lg:col-start-2 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-950">
                <h3 class="font-semibold text-gray-950 dark:text-white">Questions directes</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">Interrogez l'IA sur le contenu exact de la video au lieu de chercher manuellement le bon passage.</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-950">
                <h3 class="font-semibold text-gray-950 dark:text-white">Traduction rapide</h3>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">Passez un transcript en francais, anglais, espagnol, italien ou allemand.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8" x-data="{
        videos: [],
        loading: true,
        async init() {
            await this.loadVideos();
            window.addEventListener('video-added', () => this.loadVideos());
        },
        async loadVideos() {
            try {
                const res = await fetch('/api/videos');
                const data = await res.json();
                this.videos = data.data || [];
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        statusLabel(status) {
            return { pending: 'En attente', processing: 'Analyse', ready: 'Pret', error: 'Erreur' }[status] || 'En attente';
        }
    }">
        <div class="flex items-end justify-between gap-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-300">Bibliotheque</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">Dernieres videos analysees</h2>
            </div>
            <a href="#youtube-url" class="hidden rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-cyan-600 dark:hover:text-cyan-200 sm:inline-flex">
                Ajouter une video
            </a>
        </div>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <template x-for="video in videos" :key="video.id">
                <a :href="`/video/${video.id}`"
                   class="group grid grid-cols-[112px_1fr] gap-4 rounded-lg border border-gray-200 bg-white p-3 transition hover:border-cyan-300 hover:shadow-lg hover:shadow-cyan-900/5 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-cyan-700">
                    <img :src="video.thumbnail_url || '/images/narrv-hero.png'"
                         :alt="video.title || ''"
                         class="h-20 w-28 rounded-md object-cover bg-gray-100 dark:bg-gray-800">
                    <div class="min-w-0">
                        <div class="truncate font-semibold text-gray-950 group-hover:text-cyan-700 dark:text-white dark:group-hover:text-cyan-200" x-text="video.title || 'Video en preparation'"></div>
                        <div class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400" x-text="video.channel_name || 'YouTube'"></div>
                        <div class="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400" x-text="video.youtube_id"></div>
                        <div class="mt-3 inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-text="statusLabel(video.status)"></div>
                    </div>
                </a>
            </template>
        </div>

        <div x-show="videos.length === 0 && !loading"
             class="mt-8 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            Aucune video pour le moment.
        </div>
    </section>
</div>
@endsection
