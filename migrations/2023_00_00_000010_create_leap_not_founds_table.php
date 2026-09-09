<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('leap.table_prefix').'not_founds', function (Blueprint $table) {
            $table->id();
            // Normalized the same way a redirect's path is, so the two can be compared
            // without either side thinking about it.
            $table->string('path')->unique();
            // Filling this in is how a missing address becomes a redirect: on save the
            // rule is created and the row here is done with.
            $table->string('destination')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_used_at')->nullable();
            // Who asked, and from where. The last two are off unless a project asks
            // for them; they describe the visitor rather than the site.
            $table->json('referers')->nullable();
            $table->json('user_agents')->nullable();
            $table->json('ip_addresses')->nullable();
            $table->timestamps();

            $table->index(['hits', 'last_used_at']);
        });

        $redirects = config('leap.table_prefix').'redirects';

        // 1.15 wrote captured addresses into the redirects table as rules with no
        // destination. They are a worklist rather than rules, so they move here.
        if (Schema::hasColumn($redirects, 'detected')) {
            // Every rule with nowhere to go, not only the captured ones: the column is
            // about to say a destination is required, and an unfinished rule someone
            // typed by hand is the same unanswered question as a captured address.
            $captured = DB::table($redirects)->whereNull('destination')->get();

            foreach ($captured as $row) {
                DB::table(config('leap.table_prefix').'not_founds')->insert([
                    'path' => $row->path,
                    'destination' => null,
                    'hits' => $row->hits,
                    'last_used_at' => $row->last_used_at,
                    'referers' => $row->referers,
                    'user_agents' => $row->user_agents,
                    'ip_addresses' => $row->ip_addresses,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            DB::table($redirects)->whereNull('destination')->delete();
        }

        if (Schema::hasColumn($redirects, 'detected')) {
            // The index goes first: SQLite refuses to drop a column one still points at.
            Schema::table($redirects, function (Blueprint $table) {
                $table->dropIndex(['detected']);
            });
        }

        Schema::table($redirects, function (Blueprint $table) use ($redirects) {
            foreach (['detected', 'referers', 'user_agents', 'ip_addresses'] as $column) {
                if (Schema::hasColumn($redirects, $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // A rule with nowhere to go was the captured address, and that lives in its own
        // table now. Every row left has a destination, so the column can say so.
        Schema::table($redirects, function (Blueprint $table) {
            $table->string('destination')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $redirects = config('leap.table_prefix').'redirects';

        Schema::table($redirects, function (Blueprint $table) {
            $table->string('destination')->nullable()->change();
        });

        Schema::table($redirects, function (Blueprint $table) use ($redirects) {
            if (! Schema::hasColumn($redirects, 'detected')) {
                $table->boolean('detected')->default(false)->index();
                $table->json('referers')->nullable();
                $table->json('user_agents')->nullable();
                $table->json('ip_addresses')->nullable();
            }
        });

        Schema::dropIfExists(config('leap.table_prefix').'not_founds');
    }
};
