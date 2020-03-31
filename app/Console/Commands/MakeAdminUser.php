<?php

namespace App\Console\Commands;

use App\User;
use App\UserRole;
use Illuminate\Console\Command;

class MakeAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make-admin-user {email : Email address of the user} {--undo : Provide this parameter if you want to remove admin role for the given user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Give admin role to the account that belongs to the provided email address';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');

        $userId = User::where('email', $email)->pluck('id')->first();

        if(!$userId) {
            $this->error('Given user does not exist!');
            return;
        }

        if(UserRole::where('user_id', $userId)->where('role', 'ADMIN')->count() === 1) {
            if($this->option('undo') === false) {
                $this->error('Given user is already an admin!');
            } else {
                UserRole::where('user_id', $userId)->where('role', 'ADMIN')->delete();
                $this->info('Given user is no longer an admin!');
            }
            return;
        }

        if($this->option('undo') === false) {
            UserRole::create([
                'user_id' => $userId,
                'role' => 'ADMIN'
            ]);

            $this->info('Given user is now an admin!');
        } else {
            $this->error('Given user is already not an admin!');
        }
    }
}
