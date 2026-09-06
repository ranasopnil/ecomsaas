<?php

namespace App\Livewire\Admin;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Categories')]
class CategoryIndex extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?int $parent_id = null;

    public bool $is_active = true;

    public string $message = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
        ];
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_id = $category->parent_id;
        $this->is_active = $category->is_active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'parent_id', 'is_active']);
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

        $this->message = "{$category->name} was saved.";
        $this->cancel();
    }

    public function delete(int $categoryId): void
    {
        $category = Category::withCount('products')->findOrFail($categoryId);

        if ($category->products_count > 0) {
            $this->message = "{$category->name} still has products in it. Move them first.";

            return;
        }

        $category->delete();
        $this->message = "{$category->name} was deleted.";
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
            'categories' => Category::with('parent')->withCount('products')->orderBy('name')->get(),
        ]);
    }
}
