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
 *
 * Progress is recorded on the product, not asked of Cloudinary. Checking each
 * file over the API cost a round trip per product before a single upload
 * could start, which with a few hundred images made resuming an interrupted
 * run as slow as the run itself.
 */
class UploadProductImagesToCloud extends Command
{
    protected $signature = 'products:upload-to-cloud
                            {--dry-run : List what would be uploaded without sending anything}
                            {--limit=0 : Stop after this many, so a web request can do it in batches}
                            {--force : Re-upload images already marked as sent}';

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

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $limit  = (int) $this->option('limit');

        $this->info('Cloud   : ' . $config['cloud_name']);
        $this->info('Folder  : ' . ($config['folder'] ?: '(none)'));
        $this->info('Enabled : ' . (CloudinaryImage::enabled() ? 'yes' : 'no - upload first, then switch on'));
        $this->newLine();

        $local = Storage::disk('public_site');
        $cloud = new CloudinaryAdapter($config);

        $query = $force
            ? Product::whereNotNull('image')->where('image', '!=', '')
            : Product::awaitingCloudUpload();

        $outstanding = (clone $query)->count();

        if ($outstanding === 0) {
            $this->info('Every product image is already in Cloudinary.');
            $this->recordProgress();

            return self::SUCCESS;
        }

        $products = (clone $query)->orderBy('name')
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->get(['id', 'name', 'image']);

        $this->line('Outstanding: ' . $outstanding . ($limit > 0 ? ', doing ' . $products->count() . ' this run' : ''));
        $this->newLine();

        $uploaded = $missing = $failed = 0;

        foreach ($products as $product) {
            $path = $product->image;

            if (! $local->exists($path)) {
                // The row points at a file that is gone. Nothing to upload, and
                // enabling Cloudinary will not make it reappear. Left unsynced
                // on purpose so it keeps showing up as outstanding.
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

                // Marked without touching updated_at or firing the saving hook
                // that clears this very column.
                Product::where('id', $product->id)->update(['image_synced_at' => now()]);

                $this->line('  sent      ' . $product->name);
                $uploaded++;
            } catch (\Throwable $e) {
                // Keep going: one rejected file should not stop the rest of
                // the catalogue from migrating.
                $this->error('  failed    ' . $product->name . '  ' . $e->getMessage());
                $failed++;
            }
        }

        if (! $dryRun) {
            $this->recordProgress();
        }

        $remaining = $this->outstandingCount();

        $this->newLine();
        $this->info(($dryRun ? 'Would send' : 'Sent') . ': ' . $uploaded);
        $this->line('Still outstanding: ' . $remaining);

        if ($missing > 0) {
            $this->warn('File not found locally: ' . $missing . ' - these need re-uploading by hand.');
        }

        if ($failed > 0) {
            $this->error('Failed: ' . $failed);
        }

        if (! $dryRun && $remaining === 0) {
            $this->newLine();
            $this->info('Every product image is in Cloudinary. Safe to switch on in Settings.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    public static function outstandingCount(): int
    {
        return Product::awaitingCloudUpload()->count();
    }

    /**
     * Settings reads these to decide whether switching Cloudinary on is safe.
     */
    private function recordProgress(): void
    {
        $total = Product::whereNotNull('image')->where('image', '!=', '')->count();

        AppSetting::set('cloudinary_synced_count', $total - static::outstandingCount());
        AppSetting::set('cloudinary_synced_at', now()->toDateTimeString());
        AppSetting::set('cloudinary_synced_total', $total);
    }
}
