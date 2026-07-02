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
        $table = Leap::userModel()->getTable();

        Schema::table($table, function (Blueprint $table) {
            if (! Schema::hasColumn($table->getTable(), 'two_factor_email_confirmed_at')) {
                $table->timestamp('two_factor_email_confirmed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(Leap::userModel()->getTable(), function (Blueprint $table) {
            $table->dropColumn('two_factor_email_confirmed_at');
        });
    }
};
