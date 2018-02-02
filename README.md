# reMarkable File Sync API

Goal of this project is to figure out and document the API which is used by the [reMarkable Paper Tablet](https://remarkable.com/) for syncing documents between the device, the desktop and mobile clients and the cloud service. A sample client implementation in PHP is also part of the project.

The API allows you to exchange files with your reMarkable tablet without the need to be in the same network or have physical access to the device. This makes it possible to create your own cloud services. Eg. tools that periodically sync files to other services like Dropbox or you could add automatic export to reMarkable in your tools.

## API Documentation

I did my best to document what I cold figure out about the reMarkable File Sync API in the wiki: [API Documentation](https://github.com/splitbrain/ReMarkableAPI/wiki)

It should give you a good starting point when implementing your own client. You can also run the command line client in this repository (see below) with the `--loglevel debug` option to see the API calls in action. 

Please feel free to extend and improve the documentation.

## PHP API Client Library

This repository implements a PHP client to talk to the reMarkable file API. To use it in your projects, install via [composer](https://getcomposer.org/) (currently only `dev-master` is available, versioning will be introduced later).

    composer require splitbrain/remarkable-api

The library consists of three classes:

* `splitbrain\ReMarkableAPI\ReMarkableAPI` - this the main API interface, implementing the calls as described in the [wiki](https://github.com/splitbrain/ReMarkableAPI/wiki)
* `splitbrain\ReMarkableAPI\ReMarkableFS` - the reMarkable stores all info in a flat hierarchy with documents identified by UUIDs only. This class makes the items accessible by path names (using `/` as a directory separator)
* `splitbrain\ReMarkableAPI\Client` - this is just a thin wrapper around the Guzzle HTTP client which adds logging and handles authentication

After instantiating the `ReMarkableAPI` object, you need to call either `register()` or `init()` on it before you can issue any of the other calls. The first call will register your client through a [8 char code](https://my.remarkable.com/generator-desktop/) and return an API token. You need to save that token somewhere and pass it to `init()` for subsequent calls. Have a look at the command line client in `remarkable.php` to see how to use it all.

## Command Line Client

To demonstrate the use of the PHP client library a command line script is included in the project. To use it, clone this project, then initialize the dependencies with [composer](https://getcomposer.org/):

    composer install

Run the following to get a help screen on the usage:

    ./remarkable.php -h

Currently the following commands are implemented:

* `register` - register this application as a new device
* `list` - list all available files
* `delete` - delete a folder or document
* `mkdir` - create new folders
* `upload` - upload a new PDF
* `download` - download a Document
