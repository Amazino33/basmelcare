<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public string $name = '';
    public string $description = '';
    public ?int $categoryId = null;
    public bool $modal = false;

    public function create()
    {
        $this->reset(['name', 'description', 'categoryId']);
        $this->modal = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $isNew = !$this->categoryId;

        Category::updateOrCreate(
            ['id' => $this->categoryId],
            ['name' => $this->name, 'description' => $this->description]
        );

        $this->reset(['name', 'description', 'categoryId']);

        if ($isNew) {
            $this->success('Category saved. Add another or click Done.');
            $this->dispatch('focus-category-name');
        } else {
            $this->modal = false;
            $this->success('Category updated.');
        }
    }

    public function edit($id)
    {
        $category = Category::findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->description = $category->description ?? '';
        $this->modal = true;
    }

    public function delete($id)
    {
        Category::findOrFail($id)->delete();
        $this->success('Category deleted.');
    }

    public function render()
    {
        $headers = [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'description', 'label' => 'Description'],
        ];

        return view('livewire.categories.index', [
            'headers' => $headers,
            'categories' => Category::latest()->get(),
        ]);
    }
}
