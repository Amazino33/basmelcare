<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves product images already uploaded into this app's own storage across to
 * the public site's storage, where the shop can actually serve them.
 *
 * The two applications share a database but not a filesystem, so anything
 * uploaded before the 'products' disk existed is sitting on the staff
 * subdomain and shows as a broken image to customers.
 */
class MoveProductImages extends Command
{
    protected $signature = 'products:move-images {--dry-run : List what would move without copying}';

    protected $description = 'Copy product images into the public site storage so the shop can serve them';

    public function handle(): int
    {
        $url  = (string) config('filesystems.disks.public_site.url');
        $root = (string) config('filesystems.disks.public_site.root');

        // realpath so the operator sees where the '../..' actually lands.
        $target = realpath($root);

        $this->info('Source : ' . (realpath(config('filesystems.disks.public.root')) ?: config('filesystems.disks.public.root')));
        $this->info('Target : ' . ($target ?: $root));
        $this->info('Served from : ' . $url . '/products/');
        $this->newLine();

        // Check configuration BEFORE asking for the disks. Requesting a disk
        // whose root does not exist makes Flysystem try to create it, and the
        // operator gets a stack trace instead of an explanation.
        //
        // A misconfigured server is otherwise silent: files land somewhere the
        // shop cannot see, or links come out relative to the staff subdomain,
        // and the only symptom is the broken image this all exists to fix.
        // An empty PUBLIC_SITE_URL is the dangerous case - env() only falls
        // back when the key is absent, so a blank value yields '/storage'.
        if (! preg_match('#^https?://[^/]+#', $url)) {
            $this->error('PUBLIC_SITE_URL is missing or empty in .env.');
            $this->line('Every product image link would be broken. Set the full shop address:');
            $this->line('  PUBLIC_SITE_URL=https://basmelcare.com');

            return self::FAILURE;
        }

        if (! $target) {
            $this->error('The target folder does not exist: ' . $root);
            $this->line('Set PUBLIC_SITE_STORAGE in .env to the storage/app/public folder of the shop.');

            return self::FAILURE;
        }

        if (! is_writable($target)) {
            $this->error('The target folder is not writable by this user: ' . $target);

            return self::FAILURE;
        }

        $from = Storage::disk('public');       // this app
        $to   = Storage::disk('public_site');  // the public site

        $products = Product::whereNotNull('image')->where('image', '!=', '')->get(['id', 'name', 'image']);

        if ($products->isEmpty()) {
            $this->info('No product images on file.');

            return self::SUCCESS;
        }

        $moved = $already = $missing = 0;

        foreach ($products as $product) {
            $path = $product->image;

            if ($to->exists($path)) {
                $already++;
                continue;
            }

            if (! $from->exists($path)) {
                // Neither side has it — the row points at a file that is gone.
                $this->warn("  missing  {$product->name}  ({$path})");
                $missing++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  would move  {$product->name}  ({$path})");
                $moved++;
                continue;
            }

            // Copy rather than move: if anything goes wrong the original is
            // still there to retry from.
            $to->put($path, $from->get($path));
            $this->line("  moved  {$product->name}");
            $moved++;
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'Would move' : 'Moved') . ": {$moved}");
        $this->line("Already in place: {$already}");

        if ($missing > 0) {
            $this->warn("File not found anywhere: {$missing} — these need re-uploading.");
        }

        return self::SUCCESS;
    }
}
