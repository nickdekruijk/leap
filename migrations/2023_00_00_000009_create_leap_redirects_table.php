<?php

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
        Schema::create(config('leap.table_prefix').'redirects', function (Blueprint $table) {
            $table->id();
            // Normalized on the way in: lowercased, without the slashes at either
            // end, so the unique index is what decides a duplicate rather than how
            // someone happened to type it.
            $table->string('path')->unique();
            // Null is a path that was asked for and has nowhere to go yet — what the
            // 404 capture writes down for someone to finish.
            $table->string('destination')->nullable();
            $table->unsignedSmallInteger('status')->default(301);
            $table->boolean('active')->default(true);
            // Derived from the path on save, so the rules ending in /* can be read
            // back without a LIKE over the whole table.
            $table->boolean('wildcard')->default(false)->index();
            $table->boolean('detected')->default(false)->index();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_used_at')->nullable();
            // The pages that carried the dead link, each with how often it was
            // followed from there. A set rather than a column, because one broken
            // link is the easy case: after a migration the same address is usually
            // wrong in several places at once, and the one that arrived first is no
            // more interesting than the rest. Capped, see leap.redirects.capture.
            $table->json('referers')->nullable();
            // The same shape, and the pair that says whether a dead address still has
            // people on it or only a crawler. Both off by default: these describe the
            // visitor, not the site, and this table is open to everyone with panel
            // access. See leap.redirects.capture.
            $table->json('user_agents')->nullable();
            $table->json('ip_addresses')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('leap.table_prefix').'redirects');
    }
};
