<?php

namespace App\Console\Commands;

use App\Models\ScanArtifact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeScanArtifacts extends Command
{
    protected $signature = 'scans:purge-artifacts {--days=30 : Delete artifacts older than N days}';

    protected $description = 'Delete old lhr artifacts from local storage while keeping normalized data in DB.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $artifacts = ScanArtifact::query()
            ->where('created_at', '<', $cutoff)
            ->get();

        $deleted = 0;
        foreach ($artifacts as $artifact) {
            Storage::disk('local')->delete($artifact->path);
            $artifact->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} artifacts older than {$days} days.");

        return self::SUCCESS;
    }
}
