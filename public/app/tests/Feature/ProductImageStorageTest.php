<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Product images must be written where the SHOP can serve them.
 *
 * The staff app and the public site share a database but not a filesystem. An
 * image uploaded into this app's own storage left the shop with a row pointing
 * at a file that existed only on the staff subdomain - a broken image for every
 * customer. They now go to the 'products' disk, which is the public site's
 * storage, and the URL is built from that site's address.
 */
class ProductImageStorageTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::create([
            'name' => 'PARACETAMOL 500MG',
            'category_id' => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 850, 'reorder_level' => 1,
        ]);
    }

    private function uploader(): User
    {
        return User::factory()->create(['role' => ['content'], 'status' => 'active']);
    }

    public function test_an_upload_lands_on_the_shared_disk_not_this_apps_storage(): void
    {
        Storage::fake('public_site');
        Storage::fake('public');

        $product = $this->product();

        Livewire::actingAs($this->uploader())
            ->test(\App\Livewire\Media\Index::class)
            ->call('startUpload', $product->id)
            ->set('photo', UploadedFile::fake()->image('drug.jpg'))
            ->call('saveImage');

        $stored = $product->fresh()->image;

        $this->assertNotNull($stored, 'No image was recorded against the product.');
        Storage::disk('public_site')->assertExists($stored);
        Storage::disk('public')->assertMissing($stored);
    }

    public function test_the_url_points_at_the_public_site(): void
    {
        config(['filesystems.disks.public_site.url' => 'https://basmelcare.test/storage']);

        $product = $this->product();
        $product->update(['image' => 'products/drug.jpg']);

        $this->assertSame(
            'https://basmelcare.test/storage/products/drug.jpg',
            $product->fresh()->imageUrl(),
            'The staff app is still pointing image URLs at its own domain.'
        );
    }

    public function test_a_product_without_an_image_has_no_url(): void
    {
        $this->assertNull($this->product()->imageUrl());
    }

    public function test_replacing_an_image_removes_the_old_file_from_the_shared_disk(): void
    {
        Storage::fake('public_site');

        $product = $this->product();

        $component = Livewire::actingAs($this->uploader())
            ->test(\App\Livewire\Media\Index::class)
            ->call('startUpload', $product->id)
            ->set('photo', UploadedFile::fake()->image('first.jpg'))
            ->call('saveImage');

        $first = $product->fresh()->image;

        $component->call('startUpload', $product->id)
            ->set('photo', UploadedFile::fake()->image('second.jpg'))
            ->call('saveImage');

        $second = $product->fresh()->image;

        $this->assertNotSame($first, $second);
        Storage::disk('public_site')->assertMissing($first);
        Storage::disk('public_site')->assertExists($second);
    }

    public function test_the_shared_disk_is_configured_separately_from_this_apps_storage(): void
    {
        // If these ever point at the same place the bug is back: uploads would
        // land in the staff app's storage again.
        $this->assertNotSame(
            config('filesystems.disks.public.root'),
            config('filesystems.disks.public_site.root'),
            'The products disk is pointing at this app\'s own storage.'
        );
    }

    public function test_the_product_form_also_writes_to_the_shared_disk(): void
    {
        // Two places upload a product image: the Media page and the product
        // edit form. The form kept writing to this app's storage long after
        // the Media page was fixed.
        $this->assertStringNotContainsString(
            "store('products', 'public')",
            file_get_contents(app_path('Livewire/Products/Index.php')),
            'The product form is still writing images into the staff app storage.'
        );
    }

    public function test_no_view_builds_a_product_or_prescription_url_from_this_domain(): void
    {
        // asset() resolves against APP_URL — the staff subdomain — where none
        // of these files exist. Only medical records may use it.
        $offenders = [];

        foreach (glob(resource_path('views/livewire/**/*.blade.php'), GLOB_BRACE) as $view) {
            foreach (file($view) as $no => $line) {
                if (! str_contains($line, "asset('storage/")) {
                    continue;
                }

                if (str_contains($line, 'file_path')) {
                    continue; // medical record — correctly served from here
                }

                $offenders[] = basename(dirname($view)) . '/' . basename($view) . ':' . ($no + 1);
            }
        }

        $this->assertSame([], $offenders, "These views point at the staff domain for files stored on the public site:
  " . implode("
  ", $offenders));
    }

    public function test_medical_records_still_use_this_apps_own_storage(): void
    {
        // Patient attachments must not move to the customer-facing site.
        $this->assertStringContainsString(
            'medical-records',
            file_get_contents(app_path('Livewire/Customers/Index.php'))
        );
        $this->assertStringContainsString(
            "store('medical-records', 'public')",
            file_get_contents(app_path('Livewire/Customers/Index.php')),
            'Medical record files were moved onto the shared public-site disk.'
        );
    }
}
