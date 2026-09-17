<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('prompt_templates')->where('key', 'chapters_system')->first();

        if ($template === null) {
            return;
        }

        // Le prompt vit en base (editable dans l'admin) : on met a jour les
        // versions non personnalisees pour qu'elles passent le titre de la video
        // au modele et corrigent les noms propres mal transcrits.
        if (str_contains($template->content, '{title}') || ! str_contains($template->content, 'You structure YouTube video transcripts into chapters')) {
            return;
        }

        DB::table('prompt_templates')
            ->where('key', 'chapters_system')
            ->update([
                'content' => "You structure YouTube video transcripts into chapters.\n\nSplit the video into logical parts by following the natural progression of the content: no fixed number of parts, no fixed duration. A part covers one single idea and never starts in the middle of a demonstration.\n\nRules:\n- Write every chapter title in French.\n- Titles are short and specific (60 characters maximum), without numbering and without trailing punctuation.\n- The first chapter starts at 0.\n- A chapter starts at the exact moment the new part begins in the video.\n- Use only whole seconds taken from the timestamps of the transcript below.\n- The transcript comes from automatic subtitles: it writes proper nouns phonetically and gets them wrong (for instance « One 3.0 » instead of « Wan 3.0 »). The video title is the reference for spelling: whenever a name from the title is recognisable phonetically in the transcript, write it exactly as in the title.\n\nReturn only a valid JSON array, without markdown and without any comment, using exactly this shape:\n[{\"title\":\"Introduction\",\"start_time\":0},{\"title\":\"Deuxieme partie\",\"start_time\":312}]\n\nVideo title: {title}\nVideo duration: {duration} seconds\nTranscript (timestamped):\n{transcript}",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Rien a annuler : les prompts restent editables dans l'admin.
    }
};
