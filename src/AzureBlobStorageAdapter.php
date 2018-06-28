<?php

declare(strict_types=1);

namespace League\Flysystem\AzureBlobStorage;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use League\Flysystem\Util;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\BlobPrefix;
use MicrosoftAzure\Storage\Blob\Models\BlobProperties;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateContainerOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Common\Models\ContinuationToken;
use function array_merge;
use function compact;
use function stream_get_contents;
use function strpos;
use function var_dump;

/**
 * Class AzureBlobStorageAdapter
 * @package App\Core\Flysystem\Adapter
 */
class AzureBlobStorageAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    /**
     * @var string[]
     */
    protected static $containerOptions = [
        'PublicAccess'
    ];

    /**
     * @var string[]
     */
    protected static $blobOptions = [
        'CacheControl',
        'ContentType',
        'Metadata',
        'ContentLanguage',
        'ContentEncoding',
    ];

    /**
     * @var BlobRestProxy
     */
    private $client;

    /**
     * @var string
     */
    private $container;

    /**
     * @var array
     */
    private $config;

    /**
     * @var int
     */
    private $maxResultsForContentsListing = 5000;

    /**
     * AzureBlobStorageAdapter constructor.
     * @param BlobRestProxy $client
     * @param $container
     * @param null $prefix
     * @param array $config
     */
    public function __construct(BlobRestProxy $client, $container, $prefix = null, $config = [])
    {
        $this->client = $client;
        $this->container = $container;
        $this->config = $config;

        $this->setPathPrefix($prefix);
    }

    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|false
     */
    public function write($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config) + compact('contents');
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * @param $path
     * @param $contents
     * @param Config $config
     * @return array
     */
    protected function upload($path, $contents, Config $config)
    {
        $this->createContainerIfNotExists($this->container, new Config($this->config));

        $destination = $this->applyPathPrefix($path);
        $response = $this->client->createBlockBlob(
            $this->container,
            $destination,
            $contents,
            $this->getBlockBlobOptionsFromConfig($config)
        );

        return [
            'path'      => $path,
            'timestamp' => (int) $response->getLastModified()->getTimestamp(),
            'dirname'   => Util::dirname($path),
            'type'      => 'file',
        ];
    }

    /**
    /**
     * @param string $path
     * @param string $contents
     * @param Config $config
     * @return array|false
     */
    public function update($path, $contents, Config $config)
    {
        return $this->upload($path, $contents, $config) + compact('contents');
    }

    /**
     * @param string $path
     * @param resource $resource
     * @param Config $config
     * @return array|false
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->upload($path, $resource, $config);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function rename($path, $newpath)
    {
        return $this->copy($path, $newpath) && $this->delete($path);
    }

    /**
     * @param string $path
     * @param string $newpath
     * @return bool
     */
    public function copy($path, $newpath)
    {
        $source = $this->applyPathPrefix($path);
        $destination = $this->applyPathPrefix($newpath);
        $this->client->copyBlob($this->container, $destination, $this->container, $source);

        return true;
    }

    /**
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        try {
            $this->client->deleteBlob($this->container, $this->applyPathPrefix($path));
        } catch (ServiceException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }

        return true;
    }

    /**
     * @param string $dirname
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $prefix = $this->applyPathPrefix($dirname);
        $options = new ListBlobsOptions();
        $options->setPrefix($prefix . '/');
        $listResults = $this->client->listBlobs($this->container, $options);
        foreach ($listResults->getBlobs() as $blob) {
            $this->client->deleteBlob($this->container, $blob->getName());
        }

        return true;
    }

    /**
     * @param string $dirname
     * @param Config $config
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * @param string $path
     * @return array|bool|false|null
     */
    public function has($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function read($path)
    {
        $response = $this->readStream($path);

        if (!isset($response['stream']) || ! is_resource($response['stream'])) {
            return $response;
        }

        $response['contents'] = stream_get_contents($response['stream']);
        unset($response['stream']);

        return $response;
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function readStream($path)
    {
        $location = $this->applyPathPrefix($path);
        try {
            $response = $this->client->getBlob(
                $this->container,
                $location
            );
            return $this->normalizeBlobProperties($location, $response->getProperties())
                + ['stream' => $response->getContentStream()];
        } catch (ServiceException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * @param string $directory
     * @param bool $recursive
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $result = [];
        $location = $this->applyPathPrefix($directory);

        if (strlen($location) > 0) {
            $location = rtrim($location, '/') . '/';
        }

        $options = new ListBlobsOptions();
        $options->setPrefix($location);
        $options->setMaxResults($this->maxResultsForContentsListing);

        if ( ! $recursive) {
            $options->setDelimiter('/');
        }

        list_contents:
        $response = $this->client->listBlobs($this->container, $options);
        $continuationToken = $response->getContinuationToken();
        foreach ($response->getBlobs() as $blob) {
            $name = $blob->getName();

            if ($location === '' || strpos($name, $location) === 0) {
                $result[] = $this->normalizeBlobProperties($name, $blob->getProperties());
            }
        }

        if ( ! $recursive) {
            $result = array_merge($result, array_map([$this, 'normalizeBlobPrefix'], $response->getBlobPrefixes()));
        }

        if ($continuationToken instanceof ContinuationToken) {
            $options->setContinuationToken($continuationToken);
            goto list_contents;
        }

        return Util::emulateDirectories($result);
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function getMetadata($path)
    {
        $path = $this->applyPathPrefix($path);

        try {
            return $this->normalizeBlobProperties(
                $path,
                $this->client->getBlobProperties($this->container, $path)->getProperties()
            );
        } catch (ServiceException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }

            return false;
        }
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param string $path
     * @return array|bool|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param Config $config
     * @return CreateContainerOptions
     */
    protected function getContainerOptionsFromConfig(Config $config)
    {
        $options = new CreateContainerOptions();
        foreach (static::$containerOptions as $option) {
            if (!$config->has($option)) {
                continue;
            }
            call_user_func([$options, "set$option"], $config->get($option));
        }

        return $options;
    }

    /**
     * @param Config $config
     * @return CreateBlockBlobOptions
     */
    protected function getBlockBlobOptionsFromConfig(Config $config)
    {
        $options = new CreateBlockBlobOptions();
        foreach (static::$blobOptions as $option) {
            if (!$config->has($option)) {
                continue;
            }
            call_user_func([$options, "set$option"], $config->get($option));
        }
        $mimetype = $config->get('mimetype');
        if (isset($mimetype)) {
            $options->setContentType($mimetype);
        }

        return $options;
    }

    /**
     * @param $path
     * @param BlobProperties $properties
     * @return array
     */
    protected function normalizeBlobProperties($path, BlobProperties $properties)
    {
        $path = $this->removePathPrefix($path);
        if (substr($path, -1) === '/') {
            return ['type' => 'dir', 'path' => rtrim($path, '/')];
        }

        return [
            'path'      => $path,
            'timestamp' => (int) $properties->getLastModified()->format('U'),
            'dirname'   => Util::dirname($path),
            'mimetype'  => $properties->getContentType(),
            'size'      => $properties->getContentLength(),
            'type'      => 'file',
        ];
    }

    /**
     * @param int $numberOfResults
     */
    public function setMaxResultsForContentsListing($numberOfResults)
    {
        $this->maxResultsForContentsListing = $numberOfResults;
    }

    /**
     * @param BlobPrefix $blobPrefix
     * @return array
     */
    protected function normalizeBlobPrefix(BlobPrefix $blobPrefix)
    {
        return ['type' => 'dir', 'path' => $this->removePathPrefix(rtrim($blobPrefix->getName(), '/'))];
    }

    /**
     * @param string $container
     * @param Config $config
     */
    private function createContainerIfNotExists(string $container, Config $config)
    {
        try {
            $this->client->getContainerProperties($container);
            // The container exists.
        } catch (ServiceException $e) {
            // Code ContainerNotFound (404) means the container does not exist.
            if (404 === $e->getCode()) {
                $this->client->createContainer(
                    $this->container,
                    $this->getContainerOptionsFromConfig($config)
                );
            }
        }
    }
}
