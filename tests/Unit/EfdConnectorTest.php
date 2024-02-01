<?php

namespace Tests\Unit;

use App\Http\Integrations\EfdConnector;
use App\Http\Integrations\EfdConnectorSingleton;
use Carbon\Carbon;
use Generator;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertEquals;

class EfdConnectorTest extends TestCase
{
    public function test_efdConnector_has_correct_urls_on_construct(): void
    {
        $con = EfdConnectorSingleton::getInstance();
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
        $con = EfdConnectorSingleton::getInstance();
        $conReflected = new \ReflectionObject($con);
        
        $handshake = $conReflected->getMethod('makeInitialHandshake');
        $cookies = $conReflected->getProperty('jar');
        $getCsrf = $conReflected->getMethod('pullCsrfTokenFromHandhake');
        $csrfToken = $conReflected->getProperty('csrfMiddlewareToken');
        $setCsrfToken = $conReflected->getMethod('setCsrfMiddlewareToken');
        $handshake->setAccessible(true);
        $cookies->setAccessible(true);
        $getCsrf->setAccessible(true);
        $csrfToken->setAccessible(true);
        $setCsrfToken->setAccessible(true);

        $response = $handshake->invoke($con, $con->getClient(), $cookies->getValue($con));
        $token = $getCsrf->invoke($con, $response);
        $setCsrfToken->invoke($con, $token);

        $this->assertTrue($response->getStatusCode() === 200);
        $this->assertNotEmpty($token);
        $this->assertEquals($token, $csrfToken->getValue($con));
    }

     public function test_efd_connector_performs_agreement() : void
    {
        $con = EfdConnectorSingleton::getInstance();
        $conReflected = new \ReflectionObject($con);
        
        $cookies = $conReflected->getProperty('jar');
        $agreement = $conReflected->getMethod('makeAgreementRequest');
        $storedToken = $conReflected->getProperty('csrfMiddlewareToken');
        $getCsrf = $conReflected->getMethod('pullCsrfTokenFromHandhake');
        $setCsrf = $conReflected->getMethod('setCsrfMiddlewareToken');
        $storedToken->setAccessible(true);
        $agreement->setAccessible(true);
        $cookies->setAccessible(true);
        $getCsrf->setAccessible(true);
        $setCsrf->setAccessible(true);

        $originalToken = $storedToken->getValue($con);

        $this->assertNotEmpty($originalToken);

        $agreementResponse = $agreement->invoke($con, $con->getClient(), $cookies->getValue($con), $originalToken);
        $newToken = $getCsrf->invoke($con, $agreementResponse);
        $setCsrf->invoke($con, $newToken);

        $this->assertEquals($agreementResponse->getStatusCode(), 200);
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($originalToken, $storedToken->getValue($con));
    }

    public function test_efd_connector_fetches_transactions() : void
    {
        $con = EfdConnectorSingleton::getInstance();
        $conReflected = new \ReflectionObject($con);

        $offset = $conReflected->getProperty('initialOffset');
        $length = $conReflected->getProperty('paginationLength');
        $makeTransactionsCall = $conReflected->getMethod('makeTransactionsCall');
        $storedToken = $conReflected->getProperty('csrfMiddlewareToken');
        $offset->setAccessible(true);
        $length->setAccessible(true);
        $makeTransactionsCall->setAccessible(true);
        $storedToken->setAccessible(true);

        $start = Carbon::parse('12/1/2023');
        $end = Carbon::parse('12/31/2023');
        $offset = $offset->getValue($con);
        $length = $length->getValue($con);
        $token = $storedToken->getValue($con);

        $transactions = $makeTransactionsCall->invoke($con, Carbon::parse('12/1/2023'), Carbon::parse('12/31/2023'), $offset, $length, $token);
        
        $total = $transactions->recordsTotal;

        $this->assertEquals($total, 38);
    }
}
