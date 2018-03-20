<?php

namespace League\Flysystem\AzureBlobStorage\Test;

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
        $adapter = new AzureBlobStorageAdapter($client, 'mycontainer', 'root_directory');
        $this->filesystem = new Filesystem($adapter);
        $this->filesystem->getConfig()->set('disable_asserts', true);
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

    /**
     * @test
     */
    public function writing_and_reading_a_stream()
    {
        $contents = 'with contents';
        $filename = 'a_file.txt';
        $handle = tmpfile();
        fwrite($handle, $contents);
        $this->assertTrue($this->filesystem->writeStream($filename, $handle));
        is_resource($handle) && fclose($handle);
        $handle = $this->filesystem->readStream($filename);
        $this->assertInternalType('resource', $handle);
        $this->assertEquals($contents, stream_get_contents($handle));
    }

    /**
     * @test
     */
    public function writing_and_listing_contents()
    {
        $contents = 'with contents';
        $filename = 'a_file.txt';
        $handle = tmpfile();
        fwrite($handle, $contents);
        $this->assertTrue($this->filesystem->writeStream($filename, $handle));
        is_resource($handle) && fclose($handle);
        $listing = $this->filesystem->listContents('', true);
        $this->assertCount(1, $listing);
    }

    /**
     * @test
     */
    public function deleting_an_checking_file_existence()
    {
        $this->filesystem->write('directory/filename.txt', 'contents');
        $this->assertNotFalse($this->filesystem->has('directory/filename.txt'));
        $this->assertTrue($this->filesystem->delete('directory/filename.txt'));
        $this->assertFalse($this->filesystem->has('directory/filename.txt'));
    }

    /**
     * @test
     */
    public function copying_files()
    {
        $this->assertNotFalse($this->filesystem->write('source.txt', 'contents'));
        $this->filesystem->copy('source.txt', 'destination.txt');
        $this->assertTrue($this->filesystem->has('destination.txt'));
        $this->assertEquals('contents', $this->filesystem->read('destination.txt'));
    }


    /**
     * @after
     */
    public function cleanup_files()
    {
        $files = $this->filesystem->listContents('', true);

        foreach ($files as $file) {
            if ($file['type'] === 'dir') continue;
            $this->filesystem->delete($file['path']);
        }
    }

}