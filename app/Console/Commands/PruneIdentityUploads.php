<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneIdentityUploads extends Command
{
    protected $signature = 'identities:prune-temporary';

    protected $description = 'Hapus unggahan identitas sementara yang berusia lebih dari 48 jam';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $count = 0;
        foreach ($disk->allFiles('identities_temp') as $path) {
            if ($disk->lastModified($path) < now()->subHours(48)->timestamp) {
                if (! $disk->delete($path)) {
                    $this->error('Gagal membersihkan unggahan sementara.');

                    return self::FAILURE;
                }
                $count++;
            }
        }
        $this->info("{$count} unggahan sementara dibersihkan.");

        return self::SUCCESS;
    }
}
