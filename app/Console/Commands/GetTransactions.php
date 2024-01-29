<?php

namespace App\Console\Commands;

use App\Http\Integrations\EfdConnector;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GetTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:get {--S|start=2/11/14} {--E|end=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ['start' => $start, 'end' => $end] = $this->options();

        $start = new Carbon($start);
        $end = new Carbon($end);

        if ($start->greaterThan($end)) {
            $this->error('Start cannot be greater than end');
            return 1;
        }

        $connector = new EfdConnector();
        
        foreach($connector->fetchTransactions($start, $end) as $transactions) {
            $temp = 0;

        }

        /*
        try {
            foreach($connector->fetchTransactions($start, $end) as $transactions) {

            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
        */
    }
}
