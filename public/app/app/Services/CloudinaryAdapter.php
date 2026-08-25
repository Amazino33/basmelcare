<?php

namespace App\Services;

use App\Support\CloudinaryImage;
use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

/**
 * Flysystem adapter for Cloudinary, so product images can be written through
 * the ordinary Storage::disk() API rather than a parallel upload path.
 *
 * That uniformity is the point. The alternative - a service class called only
 * where we remember to call it - is how upload and display end up disagreeing
 * about where a file lives.
 *
 * Addressing note, since it is the easy thing to get wrong: Cloudinary
 * identifies an asset by a "public id" that carries the folder but NOT the
 * file extension. Every path translation goes through
 * CloudinaryImage::publicId() so that write, delete, exists and URL can never
 * compute it differently from one another.
 */
class CloudinaryAdapter implements FilesystemAdapter
{
    protected Cloudinary $cloudinary;

    public function __construct(array $config)
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $config['cloud_name'] ?? '',
                'api_key'    => $config['api_key'] ?? '',
                'api_secret' => $config['api_secret'] ?? '',
            ],
            'url' => ['secure' => true],
        ]);
    }

    /**
     * The SDK's API objects take a Configuration, NOT the Cloudinary instance.
     * Passing the wrong one is accepted by PHP at the call site and only fails
     * inside the client, one frame deeper, on the first real request - which
     * is how it reached production having passed every test.
     *
     * Exposed rather than inlined so the wiring can be asserted without
     * uploading anything.
     */
    public function uploadApi(): UploadApi
    {
        return $this->cloudinary->uploadApi();
    }

    public function adminApi(): AdminApi
    {
        return $this->cloudinary->adminApi();
    }

    /** Laravel calls this for Storage::disk(...)->url(). */
    public function getUrl(string $path): string
    {
        return CloudinaryImage::url($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'cld');
        file_put_contents($temporary, $contents);

        try {
            $this->upload($temporary, $path);
        } finally {
            @unlink($temporary);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'cld');
        file_put_contents($temporary, stream_get_contents($contents));

        try {
            $this->upload($temporary, $path);
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * One upload path for both write() and writeStream(), so the two cannot
     * drift into producing different public ids for the same file.
     *
     * public_id already contains the folder, so 'folder' is deliberately not
     * passed as well - Cloudinary would prepend it a second time.
     */
    private function upload(string $localFile, string $path): void
    {
        try {
            $this->uploadApi()->upload($localFile, [
                'public_id'     => CloudinaryImage::publicId($path),
                'resource_type' => 'image',
                'overwrite'     => true,
                'invalidate'    => true,
            ]);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($this->getUrl($path));

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, 'Cloudinary returned no content.');
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $handle = @fopen($this->getUrl($path), 'rb');

        if ($handle === false) {
            throw UnableToReadFile::fromLocation($path, 'Cloudinary returned no content.');
        }

        return $handle;
    }

    public function delete(string $path): void
    {
        try {
            $this->uploadApi()->destroy(CloudinaryImage::publicId($path), [
                'invalidate' => true,
            ]);
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->adminApi()->deleteAssetsByPrefix(CloudinaryImage::publicId($path . '/x'));
        } catch (\Throwable) {
            // Nothing to delete, or no permission to. Not worth failing a
            // request over: directories are not a real concept here.
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Cloudinary creates folders implicitly on upload.
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->adminApi()->asset(CloudinaryImage::publicId($path));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return true;
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Delivery URLs are public; there is no per-file visibility to set.
        // Product images are the only thing on this disk, by design - patient
        // documents stay on the staff app's own storage.
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return new FileAttributes($path, null, null, null, $extension ? 'image/' . $extension : null);
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }
}
