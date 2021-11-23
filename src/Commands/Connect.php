<?php

namespace MatinUtils\EasySocket\Commands;

use Illuminate\Console\Command;

class Connect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'easy-socket:connect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to a socket server';

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
        return app('easy-socket')->notLiveClient();
    }
}
