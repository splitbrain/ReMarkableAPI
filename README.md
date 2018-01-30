# ReMarkableAPI

Work in Progress

Goal of this project is to figure out and document the API which is used by the ReMarkable Paper Tablet for syncing documents between the device, the desktop and mobile clients and the cloud service. A sample client implementation in PHP is also part of the project.

## API Docs

The API is documented in the wiki: [API Documentation](https://github.com/splitbrain/ReMarkableAPI/wiki)

## PHP API

A sample API implementation will be provided as a composer package

## Command Line Client

To demonstrate the use of the PHP client library a command line script is included in the project. To use it, clone this project, then initialize the dependencies with composer:

    composer install

Run the following to get a help screen on the usage:

    ./remarkable.php -h
