<?php

namespace splitbrain\RemarkableAPI;


use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use splitbrain\PHPArchive\Zip;

/**
 * Class RemarkableAPI
 *
 * Implements the basic API access to the ReMarkable file API
 *
 * @package splitbrain\RemarkableAPI
 */
class RemarkableAPI
{

    const TYPE_COLLECTION = 'CollectionType';
    const TYPE_DOCUMENT = 'DocumentType';

    /** The endpoint where Authentication is handled */
    const AUTH_API = 'https://my.remarkable.com';

    /** The endpoint that tells us where to find the storage API */
    const SERVICE_DISCOVERY_API = 'https://service-manager-production-dot-remarkable-production.appspot.com';

    /** The endpoint where the files metadata is handled (may be changed by discovery above) */
    protected $STORAGE_API = 'https://document-storage-production-dot-remarkable-production.appspot.com';

    /** @var Client The HTTP client */
    protected $client;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * RemarkableAPI constructor.
     *
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $this->logger = new NullLogger();
        } else {
            $this->logger = $logger;
        }
        $this->client = new Client($this->logger);
    }

    /**
     * Exchange a website generated code against an auth token
     *
     * @link https://my.remarkable.com/generator-desktop
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

        $this->logger->info('Registering device');
        $response = $this->client->requestJSON(
            'POST',
            self::AUTH_API . '/token/json/2/device/new',
            $data
        );

        $token = (string)$response->getBody();
        $this->client->setBearerToken($token);
        return $token;
    }

    /**
     * Initialize the API with a previously acquired token
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
        $this->client->setBearerToken($token);
        $this->logger->info('Refreshing auth token');
        $response = $this->client->request(
            'POST',
            self::AUTH_API . '/token/json/2/user/new'
        );

        $token = (string)$response->getBody();
        $this->client->setBearerToken($token);
        return $token;
    }

    /**
     * Get all the files and directories meta data
     *
     * @return array
     */
    public function listItems()
    {
        $this->logger->info('Listing all items');
        $response = $this->client->request(
            'GET',
            $this->STORAGE_API . '/document-storage/json/2/docs'
        );
        $data = json_decode((string)$response->getBody(), true);
        return $data;
    }

    /**
     * Get a single item identified by the given ID
     *
     * @param string $id the document ID
     * @param bool $download should the download URL be included in the response?
     * @return array
     * @throws \Exception
     */
    public function getItem($id, $download = false)
    {
        $query = ['doc' => $id];
        if ($download) $query['withBlob'] = 'true';

        $this->logger->info('Listing item');
        $response = $this->client->request(
            'GET',
            $this->STORAGE_API . '/document-storage/json/2/docs',
            ['query' => $query]
        );

        $item = (json_decode((string)$response->getBody(), true))[0];
        if (!$item['Success']) throw new \Exception($item['Message']);

        return $item;
    }

    /**
     * Update a single item
     *
     * In theory this API supports updating multiple items at once, but it's easier to handle
     * exceptions of a single item
     *
     * @param array $item
     * @return array the updated item
     * @throws \Exception
     */
    public function updateMetaData($item)
    {
        $this->logger->info('Updating item metadata');
        return $this->storageRequest('PUT', 'upload/update-status', $item);
    }

    /**
     * Creates a new Folder
     *
     * @param string $name The visible name to use
     * @param string $parentID The parent folder ID or empty
     * @return array the created (minimal) item information
     */
    public function createFolder($name, $parentID = '')
    {
        $item = [
            'ID' => Uuid::uuid4()->toString(),
            'Parent' => $parentID,
            'Type' => self::TYPE_COLLECTION,
            'Version' => 1,
            'VissibleName' => $name,
            'ModifiedClient' => (new \DateTime())->format('c')
        ];

        $this->logger->info('Creating folder');
        return $this->updateMetaData($item);
    }

    /**
     * Creates a new Document Item ready to be uploaded
     *
     * @param string $id the new document ID
     * @return string the upload URL
     * @throws \Exception
     */
    protected function createUploadRequest($id)
    {
        $this->logger->info('Creating upload request');
        $stub = [
            'ID' => $id,
            'Type' => self::TYPE_DOCUMENT,
            'Version' => 1
        ];
        $item = $this->storageRequest('PUT', 'upload/request', $stub);

        if (!isset($item['BlobURLPut'])) {
            throw new \Exception('No BlobURLPut in upload request response');
        }

        return $item['BlobURLPut'];
    }

    /**
     * Upload a PDF File to the remarkable
     *
     * @param string $pdfBody The PDF contents
     * @param string $name Name to display
     * @param string $parentID Folder where the PDF should be stored
     */
    public function uploadPDF($pdfBody, $name, $parentID = '')
    {
        $item = [
            'ID' => Uuid::uuid4()->toString(),
            'Parent' => $parentID,
            'VissibleName' => $name,
            'ModifiedClient' => (new \DateTime())->format('c'),
            'Type' => self::TYPE_DOCUMENT,
            'Version' => 1
        ];

        $zip = new Zip();
        $zip->create();
        $zip->addData($item['ID'] . '.pdf', $pdfBody);
        $zip->addData($item['ID'] . '.pagedata', '');
        $zip->addData($item['ID'] . '.content', json_encode([
            'extraMetadata' => [],
            'fileType' => 'pdf',
            'lastOpenedPage' => 0,
            'lineHeight' => -1,
            'margins' => 100,
            'pageCount' => 0, # we don't know this, but it seems the reMarkable can count
            'textScale' => 1,
            'transform' => [] # no idea how to fill this, but it seems optional
        ], JSON_PRETTY_PRINT));
        $zipBody = $zip->getArchive();

        $this->uploadDocument($item, $zipBody);
    }

    /**
     * Upload a new document
     *
     * The document has to be an enriched zip file
     *
     * @param array $item The new item to be created
     * @param string $zipBody The zip compressed data to upload
     * @return array the newly created (minimal) item information
     */
    public function uploadDocument($item, $zipBody)
    {
        $puturl = $this->createUploadRequest($item['ID']);

        $this->logger->info('Uploading data');
        $this->client->request('PUT', $puturl, [
            'body' => $zipBody
        ]);

        $item = $this->updateMetaData($item);

        return $item;
    }

    /**
     * Download a document
     *
     * The document is an enriched zip file
     *
     * @param string $id
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function downloadDocument($id)
    {
        $item = $this->getItem($id, true);
        $geturl = $item['BlobURLGet'];

        $this->logger->info('Downloading data');
        $response = $this->client->request('GET', $geturl);
        return $response;
    }

    /**
     * Delete an existing item
     *
     * @param string $id the item's ID
     * @return mixed
     * @throws \Exception
     */
    public function deleteItem($id)
    {
        $item = $this->getItem($id);
        $this->logger->info('Deleting item');
        return $this->storageRequest('PUT', 'delete', $item);
    }

    /**
     * Executes an authenticated request on the storage JSON API
     *
     * @param string $verb The wanted HTTP verb
     * @param string $base The basic endpoint to talk to
     * @param array $item The item data to send (will be JSON encoded)
     * @return array The result of the request
     * @throws \Exception
     */
    protected function storageRequest($verb, $base, $item)
    {
        $response = $this->client->requestJSON($verb, $this->STORAGE_API . '/document-storage/json/2/' . $base, [$item]);

        $item = (json_decode((string)$response->getBody(), true))[0];
        if (!$item['Success']) throw new \Exception($item['Message']);

        return $item;
    }

    /**
     * Get the Storage meta API endpoint from the service discovery endpoint
     *
     * @throws \Exception
     */
    protected function discoverStorage()
    {
        $this->logger->info('Discovering storage host');
        $response = $this->client->request(
            'GET',
            self::SERVICE_DISCOVERY_API . '/service/json/1/document-storage',
            [
                'query' => [
                    'environment' => 'production',
                    'group' => 'auth0|5a68dc51cb30df3877a1d7c4', # FIXME what is this?
                    'apiVer' => 2,
                ]
            ]
        );

        $data = json_decode((string)$response->getBody(), true);
        if (!$data || $data['Status'] != 'OK') throw new \Exception('Service Discovery failed');

        $this->STORAGE_API = 'https://' . $data['Host'];
    }

}
