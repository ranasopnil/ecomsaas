<?php

namespace App\Livewire\Admin;

use App\Models\Brand;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Brands')]
class BrandIndex extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }

    public function edit(int $brandId): void
    {
        $brand = Brand::findOrFail($brandId);

        $this->editingId = $brand->id;
        $this->name = $brand->name;
        $this->is_active = $brand->is_active;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'is_active']);
    }

    public function save(): void
    {
        $this->validate();

        $brand = $this->editingId ? Brand::findOrFail($this->editingId) : new Brand;

        $brand->fill(['name' => $this->name, 'is_active' => $this->is_active]);

        if (! $brand->exists || $brand->isDirty('name')) {
            $brand->slug = $this->uniqueSlug($this->name, $brand->id);
        }

        $brand->save();

        $this->dispatch('toast', ['text' => "{$brand->name} was saved.", 'tone' => 'ok']);
        $this->cancel();
    }

    public function delete(int $brandId): void
    {
        $brand = Brand::withCount('products')->findOrFail($brandId);

        if ($brand->products_count > 0) {
            $this->dispatch('toast', ['text' => "{$brand->name} still has products. Move them to another brand first.", 'tone' => 'bad']);

            return;
        }

        $brand->delete();
        $this->dispatch('toast', ['text' => "{$brand->name} was deleted.", 'tone' => 'ok']);
    }

    protected function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'brand';
        $slug = $base;
        $suffix = 1;

        while (Brand::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function render()
    {
        return view('livewire.admin.brand-index', [
            'brands' => Brand::withCount('products')->orderBy('name')->get(),
        ]);
    }
}
