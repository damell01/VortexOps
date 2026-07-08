<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProductEmbeddingJob;
use App\Models\Product;
use App\Support\AdminModules;
use Illuminate\Console\Command;

class GenerateProductEmbeddingsCommand extends Command
{
    protected $signature   = 'ai:generate-embeddings {--force : Regenerate embeddings even if they already exist}';
    protected $description = 'Queue embedding generation for all active products missing an embedding vector';

    public function handle(): int
    {
        if (! AdminModules::isEnabled('ai')) {
            $this->warn('AI module is disabled. Enable it in Settings first.');
            return self::FAILURE;
        }

        $query = Product::where('is_active', true);

        if (! $this->option('force')) {
            $query->whereNull('embedding');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info($this->option('force') ? 'No active products found.' : 'All products already have embeddings.');
            return self::SUCCESS;
        }

        $this->info("Queuing embedding jobs for {$count} product(s) on the [ai] queue…");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->each(function (Product $product) use ($bar) {
            if ($this->option('force')) {
                $product->updateQuietly(['embedding' => null]);
            }
            GenerateProductEmbeddingJob::dispatch($product->id)->onQueue('ai');
            $bar->advance();
        });

        $bar->finish();
        $this->newLine();
        $this->info("Done. Run `php artisan queue:work --queue=ai` to process.");

        return self::SUCCESS;
    }
}
