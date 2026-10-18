<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->unique();
            $table->string('name', 60)->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('news_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 30); // mehr_tag | rss
            $table->string('url', 500);
            $table->string('keyword', 100)->nullable(); // only keep items containing this word (optional)
            $table->unsignedTinyInteger('max_pages')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_crawled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 150); // (150×4 bytes + bigint) fits a 767-byte index
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->string('url', 1000);
            $table->timestamp('published_at')->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();
            $table->unique(['news_source_id', 'external_id']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index(); // attraction | celebrity
            $table->string('title', 200);
            $table->string('subtitle', 200)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->string('reject_reason', 300)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
        Schema::dropIfExists('post_images');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('news');
        Schema::dropIfExists('news_sources');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('app_users');
    }
};
