<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Helpers;

class UserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update a user with random password.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->signature = 'leap:user {' . $this->getUsernameColumn() . ' : A valid ' . ($this->getUsernameColumn() == 'email' ? 'e-mail address' :  $this->getUsernameColumn()) . '} {name? : The fullname of the user, if ommited the name part of the e-mail address is used}';

        parent::__construct();
    }

    private function getUsernameColumn()
    {
        return config('leap.credentials')[0] ?? 'email';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $password = Str::random(40);
        $user = Helpers::userModel()->where($this->getUsernameColumn(), $this->arguments()[$this->getUsernameColumn()])->first();
        if ($user) {
            // Existing user, update name and password
            $password = $this->ask('New password (leave blank to leave unchanged');
            if ($password) {
                $user->password = Hash::make($password);
            }
            if ($this->argument('name')) {
                $user->name = $this->argument('name');
            }
            $user->save();
            $status = 'updated';
        } else {
            // Create new user
            $user = Helpers::userModel();
            $user->name = $this->arguments()['name'] ?: ucfirst(explode('@', $this->arguments()[$this->getUsernameColumn()])[0]);
            $column = $this->getUsernameColumn();
            $user->$column = $this->arguments()[$this->getUsernameColumn()];
            $user->password = Hash::make($this->ask('Password (blank for ' . $password . ')') ?: Str::random(40));
            $user->save();
            $status = 'created';
        }
        $this->info('User ' . $user[$this->getUsernameColumn()] . ' "' . $user->name . '" ' . $status);

        // // Give the user all permissions when requested
        // if (strtolower($this->ask('Do you want to give this user all permissions? (y/n)', 'n'))[0] == 'y') {
        //     if (Permission::where('user_id', $user->id)->count()) {
        //         $this->warn('User already has some permissions. Skipping.');
        //     } else {
        //         Permission::create([
        //             'user_id' => $user->id,
        //             'module' => '*',
        //             'create' => true,
        //             'read' => true,
        //             'update' => true,
        //             'delete' => true,
        //         ]);
        //     }
        // }
    }
}
