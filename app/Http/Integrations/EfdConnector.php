<?php

namespace App\Http\Integrations;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;

class EfdConnector {
    private Client $client;
    private Client $ptrClient;
    private String $baseUri = "https://efdsearch.senate.gov/search/";
    private String $homePath = "home/";
    private String $reportPath = "report/data/";
    private array $baseHeaders = [];
    private String $csrfMiddlewareToken;
    private CookieJar $jar;
    private CookieJar $ptrJar;
    private String $dateFormat = 'm/d/Y h:i:s';

    public function __construct()
    {
        $this->client = new Client(['base_uri' => $this->baseUri]);
        $this->ptrClient = new Client(['base_uri' => $this->baseUri]);
        $this->jar = new CookieJar();
        $this->ptrJar = new CookieJar();
    }

    public function init()
    {
        $handshake = $this->makeInitialHandshake($this->client, $this->jar);
        $ptrHandshake = $this->makeInitialHandshake($this->ptrClient, $this->ptrJar);

        $csrf = $this->pullCsrfTokenFromHandhake($handshake);
        $ptrCsrf = $this->pullCsrfTokenFromHandhake($ptrHandshake);

        $agreement = $this->makeAgreementRequest($this->client, $this->jar, $csrf);
        $ptrAgreement = $this->makeAgreementRequest($this->ptrClient, $this->ptrJar, $ptrCsrf);

        $this->csrfMiddlewareToken = $this->pullCsrfTokenFromHandhake($agreement);

        /*
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML((string) $handshake->getBody());
        $xpath = new DOMXPath($doc);
        $csrf = $xpath->query("//input[@name='csrfmiddlewaretoken']/@value")[0];

        $agreement = $this->client->post($this->homePath, [
            'form_params' => [
                'prohibition_agreement' => 1,
                'csrfmiddlewaretoken' => $csrf->value
            ],
            'headers' => [
                'Referer' => $this->baseUri . $this->homePath
            ],
            'cookies' => $this->jar
        ]);

        $doc = new DOMDocument();
        $doc->loadHTML((string) $agreement->getBody());
        $xpath = new DOMXPath($doc);
        $csrf = $xpath->query("//input[@name='csrfmiddlewaretoken']/@value")[0];

        $this->csrfMiddlewareToken = $csrf->value;

        $doc = new DOMDocument();
        $doc->loadHTML((string) $ptrHandshake->getBody());
        $xpath = new DOMXPath($doc);
        $csrf = $xpath->query("//input[@name='csrfmiddlewaretoken']/@value")[0];

        $agreement = $this->ptrClient->post($this->homePath, [
            'form_params' => [
                'prohibition_agreement' => 1,
                'csrfmiddlewaretoken' => $csrf->value
            ],
            'headers' => [
                'Referer' => $this->baseUri . $this->homePath
            ],
            'cookies' => $this->ptrJar
        ]);

        */
    }

    public function fetchTransactions(Carbon $start, Carbon $end)
    {
        if ($start->greaterThan($end)) {
            throw new Exception('Start cannot be greater than end');
        }

        $offset = 0;
        $length = 100;
        $recordsRetrieved = 0;
        $recordsTotal = 0;

        $handshake = $this->client->post($this->baseUri, [
            'form_params' => [
                'filer_type' => 1,
                'report_type' => 1,
                'csrfmiddlewaretoken' => $this->csrfMiddlewareToken
            ],
            'headers' => [
                'Referer' => $this->baseUri
            ],
            'cookies' => $this->jar
        ]);

        $csrfToken = $this->jar->getCookieByName('csrftoken')->getValue();

        $transactions = $this->makeTransactionsCall($start, $end, $offset, $length, $csrfToken);

        $recordsTotal = $transactions->recordsTotal;

        while ($recordsTotal > $recordsRetrieved) {
            $onlySenatorsAndPtrs = $this->filterSenatorsAndPtrs($transactions->data);

            $transactions = $this->pullTransactionsFromPtrs($onlySenatorsAndPtrs);

            yield $transactions;
            $recordsRetrieved += $length;
            $offset += $length;

            $transactions = $this->makeTransactionsCall($start, $end, $offset, $length, $csrfToken);
        }
    }

    private function makeInitialHandshake(Client $client, CookieJar $jar) : Response
    {
        return $client->get($this->homePath, [
            'cookies' => $jar
        ]);
    }

    private function makeAgreementRequest(client $client, CookieJar $jar, String $csrfMiddleWareToken) : Response
    {
        return $client->post($this->homePath, [
            'form_params' => [
                'prohibition_agreement' => 1,
                'csrfmiddlewaretoken' => $csrfMiddleWareToken
            ],
            'headers' => [
                'Referer' => $this->baseUri . $this->homePath
            ],
            'cookies' => $jar
        ]);
    }

    private function pullCsrfTokenFromHandhake(Response $handshake) : String
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML((string) $handshake->getBody());
        $xpath = new DOMXPath($doc);
        $csrf = $xpath->query("//input[@name='csrfmiddlewaretoken']/@value")[0];

        return $csrf->value;
    }

    private function makeTransactionsCall(Carbon $start, Carbon $end, int $offset, int $length, String $token)
    {
        $response = $this->client->post($this->reportPath, [
            'form_params' => [
                'order[0][column]' => '4',
                'order[0][dir]' => 'asc',
                'start' => $offset,
                'length' => $length,
                'report_types' => ['11'],
                'filer_types' => ['1'],
                'submitted_start_date' => $start->format($this->dateFormat),
                'submitted_end_date' => $end->format($this->dateFormat)
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Referer' => $this->baseUri,
                'X-Csrftoken' => $token
            ],
            'cookies' => $this->jar
        ]);

        $json = json_decode($response->getBody());

        return $json;
    }

    private function filterSenatorsAndPtrs(Array $list) 
    {
        return array_filter($list, function ($data) {
            return stripos($data[2], 'senator') !== false && stripos($data[3], 'ptr') !== false; 
        });
    }

    private function pullTransactionsFromPtrs(Array $list) 
    {
        return array_reduce($list, function($carry, $data) {
            $first_name = $data[0];
            $last_name = $data[1];

            $a = new \SimpleXMLElement($data[3]);
            $path = $a['href'];

            $exploded = explode('/', $path);

            $ptrId = $exploded[4];

            $path = implode('/', array_slice($exploded, 2, 4));

            $ptrResponse = $this->ptrClient->get($path, ['cookies' => $this->jar]);
            [$xpath, $doc] = $this->createXpathFromResponse($ptrResponse);

            $table = $xpath->query('//table');

            if (count($table) < 1) {
                return $carry;
            }

            $rows = $xpath->evaluate('//tbody//tr');

            foreach($rows as $tr) {
                $tds = $xpath->query('td', $tr);

                $transaction = [];
                $transaction['firstName'] = $first_name;
                $transaction['lastName'] = $last_name;
                $transaction['ptrId'] = $ptrId;
                $transaction['ptrRowNum'] = trim($tds->item(0)->nodeValue);
                $transaction['date'] = trim($tds->item(1)->nodeValue);
                $transaction['owner'] = trim($tds->item(2)->nodeValue);
                $transaction['ticker'] = trim($tds->item(3)->nodeValue);
                $transaction['type'] = trim($tds->item(6)->nodeValue);

                $amount = explode(' ', trim($tds->item(7)->nodeValue));

                $transaction['amountMin'] = substr($amount[0], 1);
                $transaction['amountMax'] = substr($amount[2], 1);

                $carry[] = $transaction;
            }

            return $carry;
        }, []);
    }

    private function fetchPtr()
    {

    }

    private function createXpathFromResponse(Response $response)
    {
        $doc = new DOMDocument();
        $doc->loadHTML((string) $response->getBody());
        $xpath = new DOMXPath($doc);

        return [$xpath, $doc];
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getPtrClient()
    {
        return $this->ptrClient;
    }
}