<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Page::class, 'parent')->nullable();

            $table->boolean('active')->default(1);
            $table->datetime('published_at')->nullable();

            $table->boolean('menuitem')->default(1);
            $table->integer('homepage_priority')->nullable();

            $table->string('view', 100)->nullable();

            $table->json('title');
            $table->json('head')->nullable();
            $table->json('html_title')->nullable();
            $table->json('slug')->nullable();
            $table->json('description')->nullable();
            $table->json('video_id')->nullable();
            $table->json('sections')->nullable();
            $table->json('meta')->nullable();

            $table->unsignedInteger('sort')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['active', 'published_at', 'parent', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
