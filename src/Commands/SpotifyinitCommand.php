<?php

declare(strict_types=1);

namespace JordanPartridge\ConduitSpotify\Commands;

use Illuminate\Console\Command;

class SpotifyinitCommand extends Command
{
    protected $signature = 'spotify:init';

    protected $description = 'Sample command for spotify component';

    public function handle(): int
    {
        $this->info('🚀 spotify component is working!');
        $this->line('This is a sample command. Implement your logic here.');
        
        return 0;
    }
}