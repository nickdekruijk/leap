<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('leap.table_prefix') . 'logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignIdFor(Leap::userModel())->nullable()->constrained();
            $table->string('module')->nullable();
            $table->string('action')->nullable();
            $table->json('context')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('leap.table_prefix') . 'logs');
    }
};
