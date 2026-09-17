<?php

namespace App\Console\Commands;

use App\Jobs\GenerateChapters;
use App\Models\Video;
use Illuminate\Console\Command;

class GenerateChaptersCommand extends Command
{
    protected $signature = 'chapters:generate {video : Identifiant de la vidéo} {--force : Régénère les chapitres même s\'il en existe déjà}';

    protected $description = 'Génère les chapitres d\'une vidéo à partir de son transcript (agent IA)';

    public function handle(): int
    {
        $video = Video::find($this->argument('video'));

        if ($video === null) {
            $this->error('Vidéo introuvable.');

            return self::FAILURE;
        }

        if ($video->transcript === null) {
            $this->error('Cette vidéo n\'a pas de transcript : génération impossible.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            $video->update([
                'chapters_json' => [],
                'chapters_source' => null,
                'chapters_status' => null,
                'chapter_thumbnails_status' => null,
            ]);
        }

        if (empty($video->fresh()->chapters_json)) {
            Video::whereKey($video->id)->update([
                'chapters_status' => 'pending',
                'updated_at' => now(),
            ]);
        }

        dispatch_sync(new GenerateChapters($video->fresh()));

        $video->refresh();

        if ($video->chapters_status !== 'ready') {
            $this->error("Échec de la génération (statut : {$video->chapters_status}).");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d chapitres générés pour « %s ».',
            count(is_array($video->chapters_json) ? $video->chapters_json : []),
            (string) $video->title
        ));

        return self::SUCCESS;
    }
}
