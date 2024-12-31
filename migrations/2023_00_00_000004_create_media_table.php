<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Leap;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('leap.table_prefix') . 'media', function (Blueprint $table) {
            $table->id();

            $table->string('disk');
            $table->string('file_name');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->uuid('uuid')->unique();
            $table->char('sha256', 64)->index();
            $table->foreignIdFor(Leap::userModel())->nullable()->constrained();
            $table->json('meta')->nullable();
            $table->json('history')->nullable();

            $table->timestamps();

            $table->unique(['disk', 'file_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('leap.table_prefix') . 'media');
    }
};
