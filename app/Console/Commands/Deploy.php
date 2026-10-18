<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Everything a fresh or updated deployment needs, in one idempotent command — so it can be run
 * from a temporary cPanel cron job on hosts without SSH/Terminal:
 *
 *   cd /home/USER/maragheh-api && /usr/local/bin/php artisan app:deploy >> storage/logs/deploy.log 2>&1
 */
class Deploy extends Command
{
    protected $signature = 'app:deploy {--admin-email=} {--admin-password=}';

    protected $description = 'Prepare the app after uploading: key, migrations, seed data, upload folder, caches';

    public function handle(): int
    {
        $this->line('== deploy '.now()->toDateTimeString());

        try {
            if (empty(config('app.key'))) {
                $this->call('key:generate', ['--force' => true]);
            }

            $this->ensureUploadFolder();

            $this->call('migrate', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);

            if ($email = $this->option('admin-email')) {
                $this->call('admin:create', [
                    'email' => $email,
                    '--password' => $this->option('admin-password'),
                ]);
            }

            $this->call('optimize:clear');
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('view:cache');
        } catch (Throwable $e) {
            $this->error('Deploy failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Deploy finished. Remove the app:deploy cron job now.');

        return self::SUCCESS;
    }

    /** public/storage holds user uploads; make sure it exists and can never run scripts. */
    private function ensureUploadFolder(): void
    {
        $dir = public_path('storage');
        if (is_link($dir)) {
            return; // local dev: symlink from an older `storage:link`
        }
        File::ensureDirectoryExists($dir);
        File::put($dir.'/.htaccess', implode("\n", [
            'Options -Indexes',
            '<FilesMatch "\.(php\d?|phtml|phar|pl|py|cgi|sh)$">',
            '    Require all denied',
            '</FilesMatch>',
            '',
        ]));
        $this->line('Upload folder ready: '.$dir);
    }
}
