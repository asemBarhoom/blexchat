<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copies the marketing images shipped in the repository to the public disk,
 * which on Laravel Cloud is an object storage bucket rather than local files.
 */
class PublishMarketingAssets extends Command
{
    protected $signature = 'marketing:publish-assets {--force : Overwrite files already on the disk}';

    protected $description = 'Upload the bundled marketing images to the public disk';

    public function handle(): int
    {
        $source = storage_path('app/public/marketing');
        $disk = Storage::disk('public');
        $uploaded = 0;

        foreach (File::allFiles($source) as $file) {
            $path = 'marketing/'.str_replace('\\', '/', $file->getRelativePathname());

            if (! $this->option('force') && $disk->exists($path)) {
                continue;
            }

            $disk->put($path, $file->getContents());
            $uploaded++;
        }

        $this->info("Uploaded {$uploaded} file(s).");

        return self::SUCCESS;
    }
}
