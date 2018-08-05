<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Providers\CoreServiceProvider;

class Core extends Command
{


    protected  $b;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
        parent::__construct();
        
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $changes = CoreServiceProvider::start();
        foreach ($changes as $changeKey => $changeParams) {
            $question = CoreServiceProvider::getQuestion($changeKey);
            $response = $this->ask($question);
            if ($response == 'y') {
                CoreServiceProvider::makeChange($changeKey);
            } else {
                $this->info('NO OK');
            }
        }
        
    }
}
