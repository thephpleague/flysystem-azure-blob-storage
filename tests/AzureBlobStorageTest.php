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
        $client = BlobRestProxy::createBlobService(getenv('FLYSYSTEM_AZURE_CONNECTION_STRING'));
        $adapter = new AzureBlobStorageAdapter($client, 'flysystem', 'root_directory');
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
    public function read_errors()
    {
        $this->assertFalse($this->filesystem->read('not-existing.txt'));
    }

    /**
     * @test
     */
    public function writing_and_updating_and_reading_a_file()
    {
        $contents = 'new contents';
        $filename = 'a_file.txt';
        $this->assertTrue($this->filesystem->write($filename, 'original contents'));
        $this->assertTrue($this->filesystem->update($filename, $contents));
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
    public function updating_contents()
    {
        $contents = 'with contents';
        $filename = 'a_file.txt';
        $handle = tmpfile();
        fwrite($handle, $contents);
        $this->assertTrue($this->filesystem->updateStream($filename, $handle));
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
     * @test
     */
    public function creating_a_directory()
    {
        $this->assertTrue($this->filesystem->createDir('dirname'));
    }

    /**
     * @test
     */
    public function listing_a_directory()
    {
        $this->filesystem->write('path/to/file.txt', 'a file');
        $this->filesystem->write('path/to/another/file.txt', 'a file');
        $this->assertCount(2, $this->filesystem->listContents('path/to'));
        $this->assertCount(3, $this->filesystem->listContents('path/to', true));
    }

    /**
     * @test
     */
    public function metadata_getters()
    {
        $this->filesystem->write('file.txt', 'contents');
        $this->assertInternalType('int', $this->filesystem->getTimestamp('file.txt'));
        $this->assertInternalType('array', $this->filesystem->getMetadata('file.txt'));
        $this->assertInternalType('int', $this->filesystem->getSize('file.txt'));
        $this->assertInternalType('string', $this->filesystem->getMimetype('file.txt'));
    }

    /**
     * @test
     */
    public function renaming_a_file()
    {
        $this->filesystem->write('path/to/file.txt', 'contents');
        $this->filesystem->rename('path/to/file.txt', 'new/path.txt');
        $this->assertTrue($this->filesystem->has('new/path.txt'));
        $this->assertFalse($this->filesystem->has('path/to/file.txt'));
    }

    /**
     * @test
     */
    public function deleting_a_directory()
    {
        $this->filesystem->write('path/to/file.txt', 'contents');
        $this->filesystem->write('path/to/another/file.txt', 'contents');
        $this->assertTrue($this->filesystem->deleteDir('path/to'));
        $this->assertFalse($this->filesystem->has('path/to/file.txt'));
        $this->assertFalse($this->filesystem->has('path/to/another/file.txt'));

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