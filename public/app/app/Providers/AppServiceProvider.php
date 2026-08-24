<?php

namespace App\Providers;

use App\Services\CloudinaryAdapter;
use App\Support\CloudinaryImage;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerProductImageDisk();
    }

    /**
     * The 'product_images' disk is Cloudinary when it is switched on in
     * Settings, and the public site's own storage when it is not.
     *
     * Resolved lazily rather than by swapping config during boot. Whether
     * Cloudinary is on lives in the database, and boot() runs for migrations
     * and for a freshly cloned install where app_settings does not exist yet
     * - reading it eagerly would break the very commands needed to create it.
     * This closure only runs the first time something actually asks for the
     * disk, by which point a request is underway and the table is there.
     *
     * One disk name covers upload, delete and URL, so the three cannot end up
     * pointing at different places - which is exactly how a file gets written
     * to one home and linked from another.
     */
    private function registerProductImageDisk(): void
    {
        Storage::extend('product_images', function ($app, array $config) {
            if (! CloudinaryImage::enabled()) {
                // Unchanged behaviour: the public site's storage, which is
                // where product images have lived since the two apps were
                // split. Switching Cloudinary off returns to exactly this.
                return Storage::disk('public_site');
            }

            $adapter = new CloudinaryAdapter(CloudinaryImage::config());

            return new LaravelFilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config,
            );
        });
    }
}
