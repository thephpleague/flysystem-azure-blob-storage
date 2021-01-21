<?php

namespace League\Flysystem\AzureBlobStorage\Tests;

use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase as TestCase;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\FilesystemAdapter;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class AzureBlobStorageTest extends TestCase
{
    const CONTAINER_NAME = 'flysystem';

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $accountKey = getenv('FLYSYSTEM_AZURE_ACCOUNT_KEY');
        $accountName = getenv('FLYSYSTEM_AZURE_ACCOUNT_NAME');
        $connectString = "DefaultEndpointsProtocol=https;AccountName={$accountName};AccountKey={$accountKey}==;EndpointSuffix=core.windows.net";
        $client = BlobRestProxy::createBlobService($connectString);

        return new AzureBlobStorageAdapter($client, self::CONTAINER_NAME);
    }
}
