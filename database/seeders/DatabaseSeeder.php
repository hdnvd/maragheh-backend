<?php

namespace Database\Seeders;

use App\Models\NewsSource;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        NewsSource::firstOrCreate(
            ['url' => 'https://www.mehrnews.com/tag/%D9%85%D8%B1%D8%A7%D8%BA%D9%87'],
            ['name' => 'خبرگزاری مهر', 'type' => NewsSource::TYPE_MEHR_TAG, 'max_pages' => 2, 'is_active' => true],
        );
    }
}
