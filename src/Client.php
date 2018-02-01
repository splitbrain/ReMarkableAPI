<?php

namespace splitbrain\RemarkableAPI;

use Psr\Log\LoggerInterface;

class Client
{

    /** @var LoggerInterface */
    protected $logger;

    /** @var \GuzzleHttp\Client */
    protected $client;

    /** @var array default options */
    protected $options = [
        'headers' => [
            'Authorization' => 'Bearer'
        ]
    ];

    /**
     * Client constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->client = new \GuzzleHttp\Client();
    }

    /**
     * Set the auth token
     *
     * @param string $token
     */
    public function setBearerToken($token)
    {
        $this->options['headers']['Authorization'] = "Bearer $token";
    }

    /**
     * Generic request (with logging)
     *
     * @param string $verb
     * @param string $url
     * @param array $options
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function request($verb, $url, $options = [])
    {
        $opt = array_merge($this->options, $options);

        // log request
        $this->logger->debug('-> {verb} {url}', ['verb' => $verb, 'url' => $url]);
        if (isset($opt['json'])) {
            $this->logger->debug(json_encode($opt['json'], JSON_PRETTY_PRINT));
        }
        if (isset($opt['query'])) {
            $this->logger->debug('?' . http_build_query($opt['query']));
        }

        // execute
        $response = $this->client->request($verb, $url, $opt);

        // log response
        $this->logger->debug(
            '<- Status {status} {length} bytes',
            [
                'status' => $response->getStatusCode(),
                'length' => $response->getBody()->getSize()
            ]
        );
        $body = (string)$response->getBody();
        if (
            substr($body, 0, 1) == '[' or
            substr($body, 0, 1) == '{'
        ) {
            $body = @json_decode($body, true);
            if ($body) {
                $this->logger->debug(json_encode($body, JSON_PRETTY_PRINT));
            }
        }

        return $response;
    }

    /**
     * Requests with a JSON body
     *
     * @param string $verb
     * @param string $url
     * @param mixed $data Data to be encoded as JSON
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function requestJSON($verb, $url, $data)
    {
        return $this->request($verb, $url, ['json' => $data]);
    }
}