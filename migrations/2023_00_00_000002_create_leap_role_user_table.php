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
        Schema::create(config('leap.table_prefix') . 'role_user', function (Blueprint $table) {
            $table->boolean('accepted');
            $table->foreignIdFor(Role::class, 'role_id')->constrained(config('leap.table_prefix') . 'roles')->cascadeOnDelete();
            $table->foreignIdFor(Leap::userModel()::class, 'user_id')->constrained()->cascadeOnDelete();
            $table->string('accept_token')->nullable();
            $table->datetime('invited_on')->nullable();
            $table->datetime('accepted_on')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
            $table->index(['accepted', 'user_id']);
        });

        // Create the first Admin role
        $first_role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'superuser',
                'permissions' => [
                    '*' => ['create', 'read', 'update', 'delete'],
                ]
            ],
        );

        // Attach the first user (if available) to the first role
        $first_role->users()->attach(Leap::userModel()::first()?->id);
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
