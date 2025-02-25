<?php

namespace NickDeKruijk\Leap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use NickDeKruijk\Leap\Leap;
use NickDeKruijk\Leap\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

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
        $this->signature = 'leap:user {' . $this->getUsernameColumn() . '? : A valid ' . ($this->getUsernameColumn() == 'email' ? 'e-mail address' :  $this->getUsernameColumn()) . '} {name? : The fullname of the user, if ommited the name part of the e-mail address is used}';

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
        // Get username/emailaddress from arguments or ask for it
        $username = $this->arguments()[$this->getUsernameColumn()] ?: text(label: 'What ' . ($this->getUsernameColumn() == 'email' ? 'e-mail address' :  $this->getUsernameColumn()) . '?', required: true);

        // Check if user already exists
        $user = Leap::userModel()->where($this->getUsernameColumn(), $username)->first();

        // Get name from arguments or ask for it using the name part of the e-mail address as default
        $name = $this->arguments()['name'] ?: text(label: 'What is the name of this user?', default: $user?->name ?: ucfirst(explode('@', $username)[0]));

        // Update or create user
        if ($user) {
            // Existing user, update name and password
            $user->name = $name;

            // Ask for password
            $password = password('Update password for ' . $username . ' (' . $name . ') (blank to leave unchanged)');
            if ($password) {
                $user->password = Hash::make($password);
            }

            // Save the updated user
            $user->save();
            $status = 'updated';
        } else {
            // Create new user
            $user = Leap::userModel();

            // Update name
            $user->name = $name;

            // Set username/emailaddress
            $user->{$this->getUsernameColumn()} = $username;

            // Generate random password
            $random_password = Str::password(symbols: false);

            // Ask for password
            $password = password('Password for ' . $username . ' (' . $name . ') (blank for ' . $random_password . ')');
            $user->password = Hash::make($password ?: $random_password);

            // Save the new user
            $user->save();
            $status = 'created';
        }
        $this->info('User ' . $user[$this->getUsernameColumn()] . ' "' . $user->name . '" ' . $status);

        // If user has no roles suggest to give it the first role available
        $roles = $user->belongsToMany(Role::class, config('leap.table_prefix') . 'role_user')->withTimestamps();
        if (!$roles->count()) {
            $role = Role::first();
            if (strtolower($this->ask('Do you want to give this user the "' . $role->name . '" role? (y/n)', 'n'))[0] == 'y') {
                $roles->attach($role, ['accepted' => true]);
            }
        }
    }
}
