<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\CloudinaryImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Product images are served either from the public site's storage or from
 * Cloudinary, decided by one setting. The stored path never changes, so the
 * switch redirects every image link at once - which is safe only if every
 * image is already up there.
 */
class CloudinaryImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CloudinaryImage::forget();
        Storage::forgetDisk('product_images');
    }

    protected function tearDown(): void
    {
        CloudinaryImage::forget();
        parent::tearDown();
    }

    private function enableCloudinary(): void
    {
        AppSetting::set('cloudinary_enabled', '1');
        AppSetting::set('cloudinary_cloud_name', 'test-cloud');
        AppSetting::set('cloudinary_api_key', 'key');
        AppSetting::set('cloudinary_api_secret', 'secret');
        AppSetting::set('cloudinary_folder', 'basmelcare');

        CloudinaryImage::forget();
        Storage::forgetDisk('product_images');
    }

    private function product(?string $image = 'products/abc.jpg'): Product
    {
        return Product::create([
            'name'          => 'PARACETAMOL 500MG',
            'category_id'   => Category::firstOrCreate(['name' => 'General'])->id,
            'selling_price' => 850,
            'reorder_level' => 1,
            'image'         => $image,
        ]);
    }

    // ── URL building ────────────────────────────────────────────────────

    public function test_the_public_id_drops_the_extension_and_carries_the_folder(): void
    {
        $this->enableCloudinary();

        // Keeping the extension in the id yields "abc.jpg.jpg", which resolves
        // to nothing. Dropping the folder puts the file somewhere else again.
        $this->assertSame('basmelcare/products/abc', CloudinaryImage::publicId('products/abc.jpg'));
        $this->assertSame('basmelcare/abc', CloudinaryImage::publicId('abc.jpg'));
        $this->assertSame('basmelcare/products/abc', CloudinaryImage::publicId('/products/abc.jpg'));
    }

    public function test_the_extension_appears_exactly_once_in_the_url(): void
    {
        $this->enableCloudinary();

        $url = CloudinaryImage::url('products/abc.jpg');

        $this->assertSame(1, substr_count($url, '.jpg'), 'Double extension in: ' . $url);
        $this->assertStringEndsWith('/basmelcare/products/abc.jpg', $url);
    }

    public function test_each_preset_puts_its_transformation_in_the_url(): void
    {
        $this->enableCloudinary();

        $this->assertStringContainsString('w_100,h_100', CloudinaryImage::url('products/abc.jpg', 'thumb'));
        $this->assertStringContainsString('w_400,h_400', CloudinaryImage::url('products/abc.jpg', 'card'));
        $this->assertStringContainsString('w_1200', CloudinaryImage::url('products/abc.jpg', 'zoom'));
    }

    public function test_an_unknown_preset_degrades_to_the_original_rather_than_breaking(): void
    {
        $this->enableCloudinary();

        $this->assertSame(
            CloudinaryImage::url('products/abc.jpg'),
            CloudinaryImage::url('products/abc.jpg', 'no-such-size')
        );
    }

    // ── the switch ──────────────────────────────────────────────────────

    public function test_images_come_from_local_storage_while_the_switch_is_off(): void
    {
        $this->assertStringNotContainsString('cloudinary', $this->product()->imageUrl('card'));
    }

    public function test_images_come_from_cloudinary_once_the_switch_is_on(): void
    {
        $this->enableCloudinary();

        $this->assertStringContainsString('res.cloudinary.com/test-cloud', $this->product()->imageUrl('card'));
    }

    public function test_the_switch_is_ignored_without_a_cloud_name(): void
    {
        // Otherwise every URL points at a host that does not exist.
        AppSetting::set('cloudinary_enabled', '1');
        AppSetting::set('cloudinary_cloud_name', '');
        CloudinaryImage::forget();

        $this->assertFalse(CloudinaryImage::enabled());
        $this->assertStringNotContainsString('cloudinary', $this->product()->imageUrl());
    }

    public function test_a_product_with_no_image_has_no_url_either_way(): void
    {
        $this->assertNull($this->product(null)->imageUrl('card'));

        $this->enableCloudinary();

        $this->assertNull($this->product(null)->imageUrl('card'));
    }

    // ── the disk ────────────────────────────────────────────────────────

    public function test_the_disk_follows_the_same_switch_as_the_urls(): void
    {
        // If the disk and the model disagreed, uploads would land in one place
        // and be linked from another - the bug this whole design prevents.
        $this->assertStringNotContainsString(
            'cloudinary',
            Storage::disk('product_images')->url('products/abc.jpg')
        );

        $this->enableCloudinary();

        $this->assertStringContainsString(
            'res.cloudinary.com',
            Storage::disk('product_images')->url('products/abc.jpg')
        );
    }

    public function test_upload_and_display_agree_on_the_url(): void
    {
        $this->enableCloudinary();

        $product = $this->product();

        $this->assertSame(
            Storage::disk('product_images')->url($product->image),
            $product->imageUrl(),
            'The disk and the model built different URLs for the same file.'
        );
    }

    // ── the guard against enabling too early ────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => ['admin'], 'status' => 'active']);
    }

    public function test_the_switch_cannot_be_turned_on_before_the_images_are_uploaded(): void
    {
        $this->product();                               // one image on file
        AppSetting::set('cloudinary_synced_count', 0);  // none uploaded

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Settings\Index::class)
            ->set('cloudinary_cloud_name', 'test-cloud')
            ->set('cloudinary_api_key', 'key')
            ->set('cloudinary_api_secret', 'secret')
            ->set('cloudinary_enabled', true)
            ->call('saveCloudinary')
            ->assertSet('cloudinary_enabled', false);

        CloudinaryImage::forget();

        $this->assertFalse(
            CloudinaryImage::enabled(),
            'Cloudinary was switched on with images still missing from it.'
        );
    }

    public function test_the_switch_turns_on_once_every_image_is_accounted_for(): void
    {
        $this->product();
        AppSetting::set('cloudinary_synced_count', 1);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Settings\Index::class)
            ->set('cloudinary_cloud_name', 'test-cloud')
            ->set('cloudinary_api_key', 'key')
            ->set('cloudinary_api_secret', 'secret')
            ->set('cloudinary_enabled', true)
            ->call('saveCloudinary')
            ->assertSet('cloudinary_enabled', true);

        CloudinaryImage::forget();

        $this->assertTrue(CloudinaryImage::enabled());
    }

    public function test_the_settings_page_renders(): void
    {
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Settings\Index::class)
            ->assertOk()
            ->assertSee('Cloudinary');
    }

    // ── patient files must not follow ───────────────────────────────────

    public function test_medical_records_and_prescriptions_stay_off_the_cdn(): void
    {
        // Cloudinary URLs are unauthenticated. Product photos are fine there;
        // patient documents are not.
        $customers = file_get_contents(app_path('Livewire/Customers/Index.php'));
        $checkout  = file_get_contents(app_path('Livewire/Shop/Checkout.php'));

        $this->assertStringContainsString("store('medical-records', 'public')", $customers);
        $this->assertStringContainsString("store('prescriptions', 'public')", $checkout);
        $this->assertStringNotContainsString('product_images', $customers);
        $this->assertStringNotContainsString('product_images', $checkout);
    }
}
