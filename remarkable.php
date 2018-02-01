#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use splitbrain\RemarkableAPI\RemarkableAPI;
use splitbrain\RemarkableAPI\RemarkableFS;

class Remarkable extends \splitbrain\phpcli\PSR3CLI
{

    const TOKEN_FILE = __DIR__ . '/auth.token';

    /** @var RemarkableAPI */
    protected $api;

    /** @var string default loglevel */
    protected $logdefault = 'error';

    /**
     * Register options and arguments on the given $options object
     *
     * @param \splitbrain\phpcli\Options $options
     * @return void
     */
    protected function setup(\splitbrain\phpcli\Options $options)
    {
        $options->setHelp('A simple command line client to speak to the ReMarkable file API.');

        $options->registerCommand('register', 'Register this CLI as a new device using a code');
        $options->registerArgument('code', 'The code obtained from https://my.remarkable.com/generator-device', true, 'register');

        $options->registerCommand('list', 'List all the available files');

        $options->registerCommand('upload', 'Upload the given file');
        $options->registerArgument('file', 'The file to upload', true, 'upload');

        $options->registerCommand('delete', 'Delete a file');
        $options->registerArgument('id', 'The ID of the file to delete', true, 'delete');

        $options->registerCommand('mkdir', 'Create a new folder hierarchy');
        $options->registerArgument('folder', 'The folders using Unix notation (slashes as path separators)', true, 'mkdir');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param \splitbrain\phpcli\Options $options
     * @return void
     */
    protected function main(\splitbrain\phpcli\Options $options)
    {
        $this->api = new RemarkableAPI($this);
        if (file_exists(self::TOKEN_FILE)) {
            $this->api->init($this->loadToken());
        }

        $args = $options->getArgs();
        switch ($options->getCmd()) {
            case 'register':
                $this->cmdRegister($args[0]);
                break;
            case 'list':
                $this->cmdList();
                break;
            case 'upload':
                $this->cmdUpload($args[0]);
                break;
            case 'delete':
                $this->cmdDelete($args[0]);
                break;
            case 'mkdir':
                $this->cmdMkDir($args[0]);
                break;

            default:
                $options->help();
        }
    }

    /**
     * Register command
     *
     * @param string $code
     */
    protected function cmdRegister($code)
    {
        $token = $this->api->register($code);
        $this->saveToken($token);
    }

    /**
     * List Command
     */
    protected function cmdList()
    {
        $fs = new RemarkableFS($this->api);
        $tree = $fs->getTree();
        $tf = new \splitbrain\phpcli\TableFormatter($this->colors);

        foreach ($tree as $path => $items) {
            foreach ($items as $item) {
                echo $tf->format(
                    [3, 25, '*', 40, 4],
                    [
                        $fs->typeToIcon($item['Type']),
                        (new \DateTime($item['ModifiedClient']))->format('Y-m-d H:i:s'),
                        $path,
                        $item['ID'],
                        $item['Version']
                    ]
                );
            }
        }
    }

    /**
     * Upload Command
     *
     * @param string $file
     */
    protected function cmdUpload($file)
    {
        $stream = \GuzzleHttp\Psr7\stream_for($file);
        $name = basename($file);
        $this->api->uploadDocument($stream, $name);
    }

    /**
     * Delete command
     *
     * @param string $id
     */
    protected function cmdDelete($id)
    {
        $this->api->deleteItem($id);
    }

    /**
     * Mkdir command
     *
     * @param string $dir
     */
    protected function cmdMkDir($dir)
    {
        $fs = new RemarkableFS($this->api);
        $fs->mkdirP($dir);
    }

    /**
     * Save the auth token
     *
     * @param string $token
     */
    protected function saveToken($token)
    {
        file_put_contents(self::TOKEN_FILE, $token);
    }

    /**
     * Get the saved auth token
     *
     * @return string the token
     * @throws Exception
     */
    protected function loadToken()
    {
        if (!file_exists(self::TOKEN_FILE)) throw new \Exception('No auth token available. Use register command');
        return file_get_contents(self::TOKEN_FILE);
    }
}

$remarkable = new Remarkable();
$remarkable->run();