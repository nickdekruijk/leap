<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NickDeKruijk\Leap\Helpers;
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
        Schema::create(config('leap.table_prefix') . 'role_user', function (Blueprint $table) {
            $table->foreignIdFor(Role::class, 'role_id')->constrained(config('leap.table_prefix') . 'roles')->cascadeOnDelete();
            $table->foreignIdFor(Helpers::userModel()::class, 'user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
        });

        // Create the first Admin role
        $first_role = Role::firstOrCreate(['id' => 1], ['name' => 'Admin']);

        // Attach the first user (if available) to the first role
        $first_role->users()->attach(Helpers::userModel()::first()?->id);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('leap.table_prefix') . 'role_user');
    }
};
