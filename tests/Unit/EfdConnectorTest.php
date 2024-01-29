<?php

namespace Tests\Unit;

use App\Http\Integrations\EfdConnector;
use Carbon\Carbon;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;

class EfdConnectorTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_efdConnector_has_correct_urls_on_construct(): void
    {
        $con = new EfdConnector();
        $conReflected = new \ReflectionObject($con);

        $base = $conReflected->getProperty('baseUri');
        $home = $conReflected->getProperty('homePath');
        $report = $conReflected->getProperty('reportPath');
        $base->setAccessible(true);
        $home->setAccessible(true);
        $report->setAccessible(true);

        $this->assertEquals($base->getValue($con), 'https://efdsearch.senate.gov/search/');
        $this->assertEquals($home->getValue($con), 'home/');
        $this->assertEquals($report->getValue($con), 'report/data/');
    }
    
     public function test_efd_connector_performs_handshake(): void
    {
        $con = new EfdConnector();
        $conReflected = new \ReflectionObject($con);
        
        $handshake = $conReflected->getMethod('makeInitialHandshake');
        $cookies = $conReflected->getProperty('jar');
        $getCsrf = $conReflected->getMethod('pullCsrfTokenFromHandhake');
        $handshake->setAccessible(true);
        $cookies->setAccessible(true);
        $getCsrf->setAccessible(true);

        $response = $handshake->invoke($con, $con->getClient(), $cookies->getValue($con));
        $token = $getCsrf->invoke($con, $response);

        $this->assertTrue($response->getStatusCode() === 200);
        $this->assertNotEmpty($getCsrf->invoke($con, $response));
    }

     public function test_efd_connector_performs_agreement() : void
    {
        $con = new EfdConnector();
        $conReflected = new \ReflectionObject($con);
        
        $handshake = $conReflected->getMethod('makeInitialHandshake');
        $cookies = $conReflected->getProperty('jar');
        $getCsrf = $conReflected->getMethod('pullCsrfTokenFromHandhake');
        $agreement = $conReflected->getMethod('makeAgreementRequest');
        $storedToken = $conReflected->getProperty('csrfMiddlewareToken');
        $storedToken->setAccessible(true);
        $agreement->setAccessible(true);
        $handshake->setAccessible(true);
        $cookies->setAccessible(true);
        $getCsrf->setAccessible(true);

        $response = $handshake->invoke($con, $con->getClient(), $cookies->getValue($con));
        $token = $getCsrf->invoke($con, $response);

        $agreementResponse = $agreement->invoke($con, $con->getClient(), $cookies->getValue($con), $token);
        $token = $getCsrf->invoke($con, $agreementResponse);

        $this->assertEquals($agreementResponse->getStatusCode(), 200);
        $this->assertNotEmpty($token);
    }

    public function test_efd_connector_initializes() : void
    {
        $con = new EfdConnector();
        $conReflected = new \ReflectionObject($con);

        $csrfProp = $conReflected->getProperty('csrfMiddlewareToken');
        $csrfProp->setAccessible(true);

        $con->init();

        $this->assertNotEmpty($csrfProp->getValue($con));
    }

    public function test_efd_connector_fetches_transactions() : void
    {
        $con = new EfdConnector();
        $con->init();

        $start = Carbon::parse('12/1/2023');
        $end = Carbon::parse('12/31/2023');

        $transactions = $con->fetchTransactions(Carbon::parse('12/1/2023'), Carbon::parse('12/31/2023'));

        foreach($transactions as $t) {
            $this->assertCount(14, $t);
        }
    }
}
