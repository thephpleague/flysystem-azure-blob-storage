<?php

use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

const CONTAINER = 'mycontainer';
include __DIR__.'/vendor/autoload.php';

$tries = 0;
$waitSeconds = 1;
goto start;

wait:
$tries++;
fwrite(STDOUT, "Waiting...\n");
sleep($waitSeconds);

start:
try {
    $client = BlobRestProxy::createBlobService('DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://0.0.0.0:10000/devstoreaccount1;');
    $adapter = new AzureBlobStorageAdapter($client, 'mycontainer', 'prefix_directory');
    (new Filesystem($adapter))->listContents();

    fwrite(STDOUT, "All is good!\n");
    return;
} catch (Exception $exception) {
    if ($tries < 30) {
        goto wait;
    }
}

throw new Exception("Service is unavailable");