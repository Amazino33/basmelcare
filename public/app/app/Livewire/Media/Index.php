<?php

namespace App\Livewire\Media;

use App\Models\AppSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, WithPagination, WithFileUploads;

    public string $search = '';

    /** missing | has | all — defaults to the work that still needs doing. */
    #[Url]
    public string $filter = 'missing';

    public $photo = null;
    public ?int $uploadingId = null;

    /** products | storefront | categories */
    #[Url]
    public string $tab = 'products';

    /**
     * The pictures the shop front is built from.
     *
     * Held here rather than hard-coded into the site so the pharmacy can put
     * its own photograph up without anybody touching a template. Every one of
     * them falls back to something finished-looking when empty, so an
     * un-uploaded slot never leaves a hole on the page.
     */
    public $heroPhoto = null;
    public $categoryPhoto = null;
    public ?int $uploadingCategoryId = null;

    public function switchTab(string $tab): void
    {
        $this->tab = $tab;
        $this->cancelUpload();
        $this->cancelCategoryUpload();
        $this->heroPhoto = null;
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->cancelUpload();
        $this->resetPage();
    }

    public function startUpload(int $id): void
    {
        $this->uploadingId = $id;
        $this->photo = null;
        $this->resetValidation();
    }

    public function cancelUpload(): void
    {
        $this->uploadingId = null;
        $this->photo = null;
        $this->resetValidation();
    }

    public function saveImage(): void
    {
        $this->validate(['photo' => 'required|image|max:2048']);

        $product = Product::findOrFail($this->uploadingId);

        if ($product->image) {
            Storage::disk('product_images')->delete($product->image);
        }

        // 'products' disk points at the public site's storage — the shop cannot
        // serve a file written into this app's own storage.
        $product->update(['image' => $this->photo->store('products', 'product_images')]);

        $name = $product->name;
        $this->uploadingId = null;
        $this->photo = null;
        $this->success("Image saved for {$name}");
    }

    // ── the shop front ──────────────────────────────────────────────────

    /**
     * Where site pictures are written.
     *
     * The public_site disk, like product images: this app's own storage is not
     * served by the shop, so a file written there would upload cleanly and
     * then show as a broken image on the site.
     */
    private const SITE_FOLDER = 'site';

    public function saveHero(): void
    {
        $this->validate(
            ['heroPhoto' => 'required|image|max:4096'],
            ['heroPhoto.max' => 'The picture must be 4MB or smaller.'],
            ['heroPhoto' => 'hero picture']
        );

        $this->replaceSiteImage('site_hero_image', $this->heroPhoto);

        $this->heroPhoto = null;
        $this->success('The shop front picture has been changed.');
    }

    public function removeHero(): void
    {
        $existing = AppSetting::get('site_hero_image');

        if ($existing) {
            Storage::disk('public_site')->delete($existing);
        }

        AppSetting::set('site_hero_image', '');
        $this->success('Picture removed. The shop front goes back to its plain background.');
    }

    private function replaceSiteImage(string $key, $upload): void
    {
        // The old file goes only once the new one is stored, so a failed
        // upload cannot leave the site with nothing.
        $previous = AppSetting::get($key);
        $path     = $upload->store(self::SITE_FOLDER, 'public_site');

        AppSetting::set($key, $path);

        if ($previous && $previous !== $path) {
            Storage::disk('public_site')->delete($previous);
        }
    }

    // ── category tiles ──────────────────────────────────────────────────

    public function startCategoryUpload(int $id): void
    {
        $this->uploadingCategoryId = $id;
        $this->categoryPhoto = null;
        $this->resetValidation();
    }

    public function cancelCategoryUpload(): void
    {
        $this->uploadingCategoryId = null;
        $this->categoryPhoto = null;
        $this->resetValidation();
    }

    public function saveCategoryImage(): void
    {
        $this->validate(
            ['categoryPhoto' => 'required|image|max:4096'],
            ['categoryPhoto.max' => 'The picture must be 4MB or smaller.'],
            ['categoryPhoto' => 'picture']
        );

        $category = Category::findOrFail($this->uploadingCategoryId);
        $previous = $category->image;

        $category->update(['image' => $this->categoryPhoto->store(self::SITE_FOLDER, 'public_site')]);

        if ($previous && $previous !== $category->image) {
            Storage::disk('public_site')->delete($previous);
        }

        $name = $category->name;
        $this->cancelCategoryUpload();
        $this->success("Picture saved for {$name}.");
    }

    public function removeCategoryImage(int $id): void
    {
        $category = Category::findOrFail($id);

        if ($category->image) {
            Storage::disk('public_site')->delete($category->image);
        }

        $category->update(['image' => null]);
        $this->success($category->name . ' goes back to its icon.');
    }

    /** What a stored site picture looks like from here, for the preview. */
    public function siteImageUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public_site')->url($path) : null;
    }

    private function scopeFilter($query, string $filter)
    {
        return match ($filter) {
            'missing' => $query->where(fn($q) => $q->whereNull('image')->orWhere('image', '')),
            'has'     => $query->whereNotNull('image')->where('image', '!=', ''),
            default   => $query,
        };
    }

    public function render()
    {
        $products = $this->scopeFilter(
                Product::query()->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")),
                $this->filter
            )
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.media.index', [
            'products'     => $products,
            'missingCount' => $this->scopeFilter(Product::query(), 'missing')->count(),
            'hasCount'     => $this->scopeFilter(Product::query(), 'has')->count(),
            'allCount'     => Product::count(),
            'heroImage'    => AppSetting::get('site_hero_image') ?: null,
            'categories'   => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
