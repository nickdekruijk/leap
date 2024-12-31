<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Models\Media;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('leap.table_prefix') . 'mediables', function (Blueprint $table) {
            $table->foreignIdFor(Media::class);
            $table->morphs('model');
            $table->string('model_attribute')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('sort')->nullable()->index();
            $table->timestamps();

            $table->index(['media_id', 'model_type', 'model_id', 'model_attribute', 'sort'], 'mediable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('leap.table_prefix') . 'mediables');
    }
};
