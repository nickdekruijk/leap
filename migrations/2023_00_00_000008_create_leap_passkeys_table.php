<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Leap;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The table name 'passkeys' is not leap-prefixed because it is owned and
     * queried directly by the laravel/passkeys package's own Passkey model.
     */
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Leap::userModel(), 'user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
