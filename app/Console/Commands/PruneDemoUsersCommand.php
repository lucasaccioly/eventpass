<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:prune')]
#[Description('Delete expired demo users with their events, tickets and devices')]
class PruneDemoUsersCommand extends Command
{
    public function handle(): int
    {
        $this->info('Pruned '.(new User)->pruneAll().' demo users');

        return self::SUCCESS;
    }
}
