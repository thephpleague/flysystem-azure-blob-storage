<?php

use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use PHPUnit\Framework\TestCase;

class AzureBlobStorageTest extends TestCase
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @before
     */
    public function setup_filesystem()
    {
        $client = BlobRestProxy::createBlobService('DefaultEndpointsProtocol=http;AccountName=devstoreaccount1;AccountKey=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==;BlobEndpoint=http://0.0.0.0:10000/devstoreaccount1;');
        $adapter = new AzureBlobStorageAdapter($client, 'mycontainer', 'prefix_directory');
        $this->filesystem = new Filesystem($adapter);
    }

    /**
     * @test
     */
    public function writing_and_reading_a_file()
    {
        $contents = 'with contents';
        $filename = 'a_file.txt';
        $this->assertTrue($this->filesystem->write($filename, $contents));
        $this->assertEquals($contents, $this->filesystem->read($filename));
        $this->assertTrue($this->filesystem->delete($filename));
    }
}