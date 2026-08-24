<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\Product;
use App\Services\CloudinaryAdapter;
use App\Support\CloudinaryImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;

/**
 * Copies product images from local storage up to Cloudinary.
 *
 * Must run BEFORE Cloudinary is switched on. Image paths do not change when
 * the switch is flipped - the same "products/abc.jpg" is simply looked for in
 * a different place - so enabling it with an unmigrated catalogue turns every
 * product image on the shop into a broken one, all at once.
 *
 * Deliberately builds the adapter itself instead of going through the
 * product_images disk: while Cloudinary is still off that disk points at
 * local storage, and this command would copy files onto themselves.
 */
class UploadProductImagesToCloud extends Command
{
    protected $signature = 'products:upload-to-cloud
                            {--dry-run : List what would be uploaded without sending anything}
                            {--force : Re-upload images already present in Cloudinary}';

    protected $description = 'Copy product images to Cloudinary so the switch can be turned on safely';

    public function handle(): int
    {
        $config = CloudinaryImage::config();

        foreach (['cloud_name', 'api_key', 'api_secret'] as $key) {
            if ($config[$key] === '') {
                $this->error('Cloudinary is not configured: ' . $key . ' is empty.');
                $this->line('Fill in the Cloudinary section of Settings first. The switch');
                $this->line('itself can stay off - this command does not need it on.');

                return self::FAILURE;
            }
        }

        $this->info('Cloud   : ' . $config['cloud_name']);
        $this->info('Folder  : ' . ($config['folder'] ?: '(none)'));
        $this->info('Enabled : ' . (CloudinaryImage::enabled() ? 'yes' : 'no - upload first, then switch on'));
        $this->newLine();

        $local   = Storage::disk('public_site');
        $cloud   = new CloudinaryAdapter($config);
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');

        $products = Product::whereNotNull('image')->where('image', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'image']);

        if ($products->isEmpty()) {
            $this->info('No product images on file.');
            $this->recordProgress(0, 0);

            return self::SUCCESS;
        }

        $uploaded = $already = $missing = $failed = 0;

        foreach ($products as $product) {
            $path = $product->image;

            if (! $force && $cloud->fileExists($path)) {
                $already++;
                continue;
            }

            if (! $local->exists($path)) {
                // The row points at a file that is gone. Nothing to upload,
                // and enabling Cloudinary will not make it reappear.
                $this->warn('  missing   ' . $product->name . '  (' . $path . ')');
                $missing++;
                continue;
            }

            if ($dryRun) {
                $this->line('  would send  ' . $product->name);
                $uploaded++;
                continue;
            }

            try {
                $cloud->write($path, $local->get($path), new Config());
                $this->line('  sent      ' . $product->name);
                $uploaded++;
            } catch (\Throwable $e) {
                // Keep going: one rejected file should not stop the rest of
                // the catalogue from migrating.
                $this->error('  failed    ' . $product->name . '  ' . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would send' : 'Sent') . ': ' . $uploaded);
        $this->line('Already in Cloudinary: ' . $already);

        if ($missing > 0) {
            $this->warn('File not found locally: ' . $missing . ' - these need re-uploading by hand.');
        }

        if ($failed > 0) {
            $this->error('Failed: ' . $failed);
        }

        if (! $dryRun) {
            $this->recordProgress($uploaded + $already, $products->count());
        }

        $ready = $failed === 0 && ($uploaded + $already + $missing) === $products->count();

        if (! $dryRun && $ready && $missing === 0) {
            $this->newLine();
            $this->info('Every product image is in Cloudinary. Safe to switch on in Settings.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Settings reads these to decide whether switching Cloudinary on is safe,
     * rather than asking the API about every product on each page load.
     */
    private function recordProgress(int $inCloud, int $total): void
    {
        AppSetting::set('cloudinary_synced_count', $inCloud);
        AppSetting::set('cloudinary_synced_at', now()->toDateTimeString());
        AppSetting::set('cloudinary_synced_total', $total);
    }
}
