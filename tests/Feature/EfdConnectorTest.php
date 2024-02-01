<?php

namespace Tests\Feature;

use App\Http\Integrations\EfdConnector;
use Carbon\Carbon;
use EfdConnectorSingleton;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EfdConnectorTest extends TestCase
{
    public $transactions;

    public function test_efd_connector_fetches_transactions() : void
    {
        $con = EfdConnectorSingleton::getInstance();
        $con->init();

        $start = Carbon::parse('12/1/2023');
        $end = Carbon::parse('12/31/2023');

        $transactions = $con->fetchTransactions(Carbon::parse('12/1/2023'), Carbon::parse('12/31/2023'));

        foreach($transactions as $t) {
            $this->assertCount(14, $t);
        }
    }
}
