<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Раздел «Статьи» (/stati). Бывшие «гиды» (страницы gid-*) переносятся сюда черновиками:
 * в них по 250 знаков — поисковики сочтут такие страницы малоценными, пока их не дописать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('h1')->nullable();
            $table->string('category')->nullable()->index();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('cover')->nullable();
            $table->string('cover_alt')->nullable();
            $table->json('faq')->nullable();
            $table->string('cars_query')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_role')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        $now = now();
        foreach (DB::table('pages')->where('slug', 'like', 'gid-%')->get() as $page) {
            DB::table('articles')->insertOrIgnore([
                'slug' => substr($page->slug, 4),
                'title' => $page->title,
                'h1' => $page->h1,
                'category' => 'Советы',
                'content' => $page->content,
                'seo_description' => $page->seo_description,
                'is_published' => false,
                'created_at' => $page->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('pages')->where('slug', 'like', 'gid-%')->update(['is_published' => false]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', 'like', 'gid-%')->update(['is_published' => true]);
        Schema::dropIfExists('articles');
    }
};
