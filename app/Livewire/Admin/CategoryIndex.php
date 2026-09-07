<?php

namespace App\Livewire\Admin;

use App\Exceptions\LimitReached;
use App\Models\Category;
use App\Services\Catalogue\ImageService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Categories')]
class CategoryIndex extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $parent_id = null;

    public bool $is_active = true;

    /** A new picture being uploaded, before it is saved. */
    public ?TemporaryUploadedFile $photo = null;

    /** Set when the shopkeeper asks for the existing picture to go. */
    public bool $dropPhoto = false;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ];
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id;
        $this->is_active = $category->is_active;
        $this->photo = null;
        $this->dropPhoto = false;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'parent_id', 'is_active', 'photo', 'dropPhoto']);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId !== null && $this->parent_id === $this->editingId) {
            $this->addError('parent_id', 'A category cannot sit inside itself.');

            return;
        }

        $category = $this->editingId ? Category::findOrFail($this->editingId) : new Category;

        $category->fill([
            'name' => $this->name,
            'parent_id' => $this->parent_id ?: null,
            'is_active' => $this->is_active,
        ]);

        if (! $category->exists || $category->isDirty('name')) {
            $category->slug = $this->uniqueSlug($this->name, $category->id);
        }

        $category->save();

        // The picture is saved after the category, because a new category has
        // no id to file it under until then.
        if (! $this->applyPhoto($category)) {
            return;
        }

        $this->dispatch('toast', ['text' => "{$category->name} was saved.", 'tone' => 'ok']);
        $this->cancel();
    }

    /**
     * Put the chosen picture on, or take the old one off.
     *
     * Returns false when the shop has run out of its image allowance, so the
     * form stays open with the category saved and the reason shown.
     */
    protected function applyPhoto(Category $category): bool
    {
        $images = app(ImageService::class);

        if ($this->photo !== null) {
            try {
                $images->storeForCategory($category, $this->photo);
            } catch (LimitReached $e) {
                $this->addError('photo', $e->getMessage());

                return false;
            }

            return true;
        }

        if ($this->dropPhoto) {
            $images->deleteCategoryImage($category);
        }

        return true;
    }

    /**
     * Take a picture off a category straight from the list.
     */
    public function removeImage(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        app(ImageService::class)->deleteCategoryImage($category);

        $this->dispatch('toast', ['text' => "The picture on {$category->name} was removed.", 'tone' => 'ok']);
    }

    public function delete(int $categoryId): void
    {
        $category = Category::withCount('products')->findOrFail($categoryId);

        if ($category->products_count > 0) {
            $this->dispatch('toast', ['text' => "{$category->name} still has products in it. Move them first.", 'tone' => 'bad']);

            return;
        }

        app(ImageService::class)->deleteCategoryImage($category);

        $category->delete();
        $this->dispatch('toast', ['text' => "{$category->name} was deleted.", 'tone' => 'ok']);
    }

    protected function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $suffix = 1;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.category-index', [
            'categories' => Category::with('parent.parent.parent')->withCount('products')->orderBy('name')->get(),
        ]);
    }
}
