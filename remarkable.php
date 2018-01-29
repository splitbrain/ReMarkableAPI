#!/usr/bin/php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use splitbrain\RemarkableAPI\RemarkableAPI;
use splitbrain\RemarkableAPI\RemarkableFS;

class Remarkable extends \splitbrain\phpcli\CLI
{

    const TOKEN_FILE = __DIR__ . '/auth.token';

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
        $api = new RemarkableAPI();


        $args = $options->getArgs();
        switch ($options->getCmd()) {
            case 'register':
                $token = $api->register($args[0]);
                $this->saveToken($token);
                break;
            case 'list':
                $api->init($this->loadToken());
                $list = $api->listFiles();
                $fs = new RemarkableFS($list);
                $tree = $fs->getTree();
                $tf = new \splitbrain\phpcli\TableFormatter($this->colors);

                foreach ($tree as $path => $items) {
                    foreach ($items as $item) {
                        echo $tf->format(
                            [3, 25, '*'],
                            [
                                $fs->typeToIcon($item['Type']),
                                (new \DateTime($item['ModifiedClient']))->format('Y-m-d H:i:s'),
                                $path
                            ]
                        );
                    }
                }
                break;

            default:
                $options->help();
        }
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