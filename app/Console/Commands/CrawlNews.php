<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\NewsCrawlService;
use Illuminate\Console\Command;
use Throwable;

class CrawlNews extends Command
{
    protected $signature = 'news:crawl {--source= : Only crawl this source id}';

    protected $description = 'Fetch the latest Maragheh news from all active sources';

    public function handle(NewsCrawlService $service): int
    {
        $query = NewsSource::query()->where('is_active', true);
        if ($id = $this->option('source')) {
            $query = NewsSource::query()->whereKey($id);
        }
        $failed = false;
        foreach ($query->get() as $source) {
            try {
                $count = $service->crawl($source);
                $this->info("{$source->name}: {$count} new");
            } catch (Throwable $e) {
                $failed = true;
                $this->error("{$source->name}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
