<?php
/**
 * Created by PhpStorm.
 * User: barsa
 * Date: 29-Aug-17
 * Time: 10:15
 */

namespace mpesa;

$root_dir = dirname(dirname(__FILE__));

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;

require_once $root_dir . '/vendor/autoload.php';

class MpesaFactory
{
    /**
     * Base url for the API endpoints
     * @var string
     */
    protected $BASE_URL;

    //public $access_token;
    protected $MPESA_CONSUMER_KEY;
    protected $MPESA_CONSUMER_SECRET;
    protected $MPESA_ENV;
    protected $client;
    protected $database;

    /**
     * MPESA_FACTORY constructor.
     */
    function __construct()
    {
        //read the environment variables
        $dotenv = new Dotenv(dirname(__DIR__));
//        $dotenv->required(['consumer_key', 'consumer_secret', 'application_status']);
        $dotenv->load();

        $this->MPESA_CONSUMER_KEY = getenv('MPESA_CONSUMER_KEY');
        $this->MPESA_CONSUMER_SECRET = getenv('MPESA_CONSUMER_SECRET');
        $this->MPESA_ENV = getenv('MPESA_ENV');

        if ($this->MPESA_ENV == 'live') {
            $this->BASE_URL = 'https://api.safaricom.co.ke';
        } else {
            $this->BASE_URL = 'https://sandbox.safaricom.co.ke';
        }

        $this->client = new Client([
            // Base URI is used with relative requests
            'base_uri' => $this->BASE_URL,
            // You can set any number of default request options.
            'timeout' => 120, //timeout after 30 seconds
            //'verify' => false
        ]);
    }


    /**
     * Get access token used to authorize mpesa transactions
     * @param string $endpoint
     * @return array|object|string
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function GenerateToken($endpoint = '/oauth/v1/generate?grant_type=client_credentials')
    {
        $credentials = base64_encode("{$this->MPESA_CONSUMER_KEY}:{$this->MPESA_CONSUMER_SECRET}");
        $headers = ['Authorization' => 'Basic ' . $credentials];
        $response = $this->client->request('GET', $endpoint, [
            'headers' => $headers
        ]);

        $bodyContent = $response->getBody()->getContents();
        $content = json_decode($bodyContent);

        return $content->access_token;
    }

    /**
     * For Lipa Na M-Pesa online payment using STK Push.
     * @param $body
     * @param string $endpoint
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function LipaNaMpesaRequestQuery($body, $endpoint = '/mpesa/stkpushquery/v1/query')
    {

        return $this->processApiRequest($body, $endpoint);
    }

    /**
     * For Lipa Na M-Pesa online payment using STK Push.
     * @param array $body
     * @param string $endpoint https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function LipaNaMpesaProcessRequest(array $body, $endpoint = '/mpesa/stkpush/v1/processrequest')
    {

        return $this->processApiRequest($body, $endpoint);
    }

    /**
     * For C2B Simulation
     * @param array $body
     * @param string $endpoint
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function customerToBusiness(array $body, $endpoint = '/mpesa/c2b/v1/simulate')
    {

        return $this->processApiRequest($body, $endpoint);
    }

    /**
     * @param array $body
     * @param string $endpoint
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function registerC2BUrls(array $body, $endpoint = '/mpesa/c2b/v1/registerurl')
    {
        return $this->processApiRequest($body, $endpoint);
    }

    /**
     * @param bool $asDate
     * @return int|string
     */
    public function GetTimeStamp($asDate = false)
    {
        $date = new \DateTime();

        return $asDate ? $date->format('Ymdhis') : $date->getTimestamp();
    }

    /**
     * @param array $body
     * @param $uri
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    protected function processApiRequest(array $body, $uri)
    {
        try {
            $token = $this->GenerateToken();

            $response = $this->client->request('POST', $uri, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($body)
            ]);
            $bodyContent = $response->getBody()->getContents();
            $content = json_decode($bodyContent);
        } catch (ClientException $exception) {
            $bodyContent = $exception->getResponse()->getBody();
            $content = json_decode($bodyContent);
        } catch (ServerException $exception) {
            $bodyContent = $exception->getResponse()->getBody();
            $content = json_decode($bodyContent);
        } catch (ConnectException $exception) {
            $bodyContent = $exception->getResponse()->getBody();
            $content = json_decode($bodyContent);
        }

        return $content;
    }
}