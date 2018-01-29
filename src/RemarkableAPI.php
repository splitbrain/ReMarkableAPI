<?php

namespace splitbrain\RemarkableAPI;


use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;

/**
 * Class RemarkableAPI
 *
 * Implements the basic API access to the ReMarkable file API
 *
 * @package splitbrain\RemarkableAPI
 */
class RemarkableAPI
{
    /** The endpoint where Authentication is handled */
    const AUTH_API = 'https://my.remarkable.com';

    /** The endpoint that tells us where to find the storage API */
    const SERVICE_DISCOVERY_API = 'https://service-manager-production-dot-remarkable-production.appspot.com';

    /** The endpoint where the files metadata is handled (may be changed by discovery above) */
    protected $STORAGE_API = 'https://document-storage-production-dot-remarkable-production.appspot.com';

    /** @var string the current auth token */
    protected $token;

    /**
     * Exchange a website generated code against an auth token
     *
     * @link https://my.remarkable.com/generator-device
     *
     * @param string $code the auth code as displayed by the my.remarkable.com
     * @return string the bearer authentication token
     * @throws \Exception
     */
    public function register($code)
    {
        $device = Uuid::uuid4()->toString();

        $data = [
            'code' => $code,
            'deviceDesc' => 'desktop-windows', # we have to lie here
            'deviceID' => $device
        ];

        $client = new Client([
            'base_uri' => self::AUTH_API,
            'headers' => [
                'Authorization' => 'Bearer'
            ]
        ]);
        $response = $client->request('POST', '/token/device/new', ['json' => $data]);
        $this->token = (string)$response->getBody();

        return $this->token;
    }

    /**
     * Initialize the API with a previously aquired token
     *
     * @param $token
     */
    public function init($token)
    {
        $this->refreshToken($token);
        $this->discoverStorage();
    }

    /**
     * Refresh the current authentication token (if necessary)
     *
     * @param string $token the old token
     * @return string the new token
     * @throws \Exception
     */
    public function refreshToken($token)
    {
        $client = new Client([
            'base_uri' => self::AUTH_API,
            'headers' => [
                'Authorization' => "Bearer $token"
            ]
        ]);
        $response = $client->request('POST', '/token/user/new');
        $this->token = (string)$response->getBody();
        return $this->token;
    }

    /**
     * Get all the files and directories meta data
     *
     * @return array
     */
    public function listFiles()
    {
        $client = new Client([
            'base_uri' => $this->STORAGE_API,
            'headers' => [
                'Authorization' => "Bearer $this->token"
            ],
        ]);

        $response = $client->request('GET', '/document-storage/json/2/docs');
        $data = json_decode((string)$response->getBody(), true);

        return $data;
    }


    /**
     * Get the Storage meta API endpoint from the service discovery endpoint
     *
     * @throws \Exception
     */
    protected function discoverStorage()
    {
        $client = new Client([
            'base_uri' => self::SERVICE_DISCOVERY_API,
            'headers' => [
                'Authorization' => "Bearer $this->token"
            ],
        ]);

        $response = $client->request('GET', '/service/json/1/document-storage', [
            'query' => [
                'environment' => 'production',
                'group' => 'auth0|5a68dc51cb30df3877a1d7c4', # FIXME what is this?
                'apiVer' => 2,
            ]
        ]);

        $data = json_decode((string)$response->getBody(), true);
        if (!$data || $data['Status'] != 'OK') throw new \Exception('Service Discovery failed');

        $this->STORAGE_API = 'https://'.$data['Host'];
    }

}