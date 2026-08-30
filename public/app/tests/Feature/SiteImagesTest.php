<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pictures the shop front is built from, changed from the admin.
 *
 * Held as settings and a column rather than baked into a template, so the
 * pharmacy can put its own photograph up without anybody editing code. Every
 * slot falls back to something finished-looking, because a half-filled site is
 * worse than a plain one.
 */
class SiteImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public_site');
    }

    private function page(?User $as = null)
    {
        return Livewire::actingAs($as ?? User::factory()->create(['role' => ['admin'], 'status' => 'active']))
            ->test(\App\Livewire\Media\Index::class);
    }

    private function picture(string $name = 'hero.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 1600, 700);
    }

    // ── the shop front picture ──────────────────────────────────────────

    public function test_a_picture_can_be_put_on_the_shop_front(): void
    {
        $this->page()
            ->set('tab', 'storefront')
            ->set('heroPhoto', $this->picture())
            ->call('saveHero')
            ->assertHasNoErrors();

        $stored = AppSetting::get('site_hero_image');

        $this->assertNotEmpty($stored);
        Storage::disk('public_site')->assertExists($stored);
    }

    public function test_it_is_written_where_the_shop_can_serve_it(): void
    {
        // This app's own storage is not served by the shop, so a file written
        // there uploads cleanly and then shows as a broken image on the site.
        $this->page()
            ->set('tab', 'storefront')
            ->set('heroPhoto', $this->picture())
            ->call('saveHero');

        $this->assertStringStartsWith('site/', AppSetting::get('site_hero_image'));
    }

    public function test_replacing_it_removes_the_old_file(): void
    {
        $page = $this->page()->set('tab', 'storefront');

        $page->set('heroPhoto', $this->picture('first.jpg'))->call('saveHero');
        $first = AppSetting::get('site_hero_image');

        $page->set('heroPhoto', $this->picture('second.jpg'))->call('saveHero');
        $second = AppSetting::get('site_hero_image');

        $this->assertNotSame($first, $second);
        Storage::disk('public_site')->assertMissing($first);
        Storage::disk('public_site')->assertExists($second);
    }

    public function test_it_can_be_taken_down_again(): void
    {
        $page = $this->page()->set('tab', 'storefront');
        $page->set('heroPhoto', $this->picture())->call('saveHero');
        $path = AppSetting::get('site_hero_image');

        $page->call('removeHero');

        $this->assertEmpty(AppSetting::get('site_hero_image'));
        Storage::disk('public_site')->assertMissing($path);
    }

    public function test_something_that_is_not_a_picture_is_refused(): void
    {
        $this->page()
            ->set('tab', 'storefront')
            ->set('heroPhoto', UploadedFile::fake()->create('prices.pdf', 200))
            ->call('saveHero')
            ->assertHasErrors('heroPhoto');

        $this->assertEmpty(AppSetting::get('site_hero_image'));
    }

    public function test_an_enormous_picture_is_refused(): void
    {
        // It would load slowly on a phone, which is most of the traffic here.
        $this->page()
            ->set('tab', 'storefront')
            ->set('heroPhoto', UploadedFile::fake()->image('huge.jpg')->size(9000))
            ->call('saveHero')
            ->assertHasErrors('heroPhoto');
    }

    // ── category tiles ──────────────────────────────────────────────────

    public function test_a_category_can_be_given_a_picture(): void
    {
        $category = Category::create(['name' => 'ANTIMALARIA']);

        $this->page()
            ->set('tab', 'categories')
            ->call('startCategoryUpload', $category->id)
            ->set('categoryPhoto', $this->picture('malaria.jpg'))
            ->call('saveCategoryImage')
            ->assertHasNoErrors();

        $this->assertNotEmpty($category->fresh()->image);
        Storage::disk('public_site')->assertExists($category->fresh()->image);
    }

    public function test_a_category_picture_can_be_removed(): void
    {
        $category = Category::create(['name' => 'ANTIMALARIA']);

        $page = $this->page()->set('tab', 'categories');
        $page->call('startCategoryUpload', $category->id)
            ->set('categoryPhoto', $this->picture())
            ->call('saveCategoryImage');

        $path = $category->fresh()->image;
        $page->call('removeCategoryImage', $category->id);

        $this->assertNull($category->fresh()->image);
        Storage::disk('public_site')->assertMissing($path);
    }

    public function test_replacing_a_category_picture_removes_the_old_one(): void
    {
        $category = Category::create(['name' => 'ANTIMALARIA']);
        $page     = $this->page()->set('tab', 'categories');

        $page->call('startCategoryUpload', $category->id)
            ->set('categoryPhoto', $this->picture('a.jpg'))
            ->call('saveCategoryImage');
        $first = $category->fresh()->image;

        $page->call('startCategoryUpload', $category->id)
            ->set('categoryPhoto', $this->picture('b.jpg'))
            ->call('saveCategoryImage');

        $this->assertNotSame($first, $category->fresh()->image);
        Storage::disk('public_site')->assertMissing($first);
    }

    // ── who may change them ─────────────────────────────────────────────

    public function test_the_content_person_can_reach_the_page(): void
    {
        // The role exists for exactly this.
        $this->actingAs(User::factory()->create(['role' => ['content'], 'status' => 'active']))
            ->get(route('media.index'))
            ->assertOk();
    }

    public function test_a_cashier_cannot(): void
    {
        $this->actingAs(User::factory()->create(['role' => ['cashier'], 'status' => 'active']))
            ->get(route('media.index'))
            ->assertForbidden();
    }

    // ── nothing uploaded is a normal state ──────────────────────────────

    public function test_the_page_opens_with_no_pictures_at_all(): void
    {
        Category::create(['name' => 'ANTIMALARIA']);

        $this->page()->set('tab', 'storefront')->assertOk();
        $this->page()->set('tab', 'categories')->assertOk();
    }

    public function test_it_says_when_the_shop_front_has_no_picture(): void
    {
        $this->page()
            ->set('tab', 'storefront')
            ->assertSee('the shop front uses its plain colour');
    }
}
