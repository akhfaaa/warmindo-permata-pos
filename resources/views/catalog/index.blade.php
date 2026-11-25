@extends('layouts.app')

@section('title', 'Menu & Resep')
@section('page_title', 'Manajemen Menu, Topping, & Resep (BOM)')

@section('content')
<div
    x-data="{
        activeTab: 'products',
        modalProduct: false,
        modalModifier: false,
        recipeDrawer: false,
        recipeTarget: { type: 'product', id: '', name: '', price: 0 },
        recipeRows: [],
        ingredientsMaster: {{ Js::from($ingredients->mapWithKeys(fn($i) => [$i->id => ['name' => $i->name, 'unit' => $i->unit, 'cost' => (float)$i->avg_cost_per_unit]])) }},
        openRecipeEditor(type, id, name, price, existingRecipes) {
            this.recipeTarget = { type, id, name, price: parseFloat(price) };
            this.recipeRows = existingRecipes.length
                ? existingRecipes.map(r => ({ ingredient_id: r.ingredient_id, quantity_required: parseFloat(r.quantity_required) }))
                : [{ ingredient_id: '', quantity_required: 1 }];
            this.recipeDrawer = true;
        },
        addRecipeRow() {
            this.recipeRows.push({ ingredient_id: '', quantity_required: 1 });
        },
        removeRecipeRow(idx) {
            this.recipeRows.splice(idx, 1);
        },
        computedHpp() {
            return this.recipeRows.reduce((sum, row) => {
                const ing = this.ingredientsMaster[row.ingredient_id];
                if (!ing || !row.quantity_required) return sum;
                return sum + (ing.cost * parseFloat(row.quantity_required));
            }, 0);
        },
        formatRp(val) {
            return 'Rp ' + Math.round(val).toLocaleString('id-ID');
        }
    }"
    class="max-w-6xl mx-auto space-y-6"
>
    <!-- Header Tabs & Primary Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="inline-flex p-1 rounded-control bg-subtle border border-border">
            <button type="button" @click="activeTab = 'products'"
                    :class="activeTab === 'products' ? 'bg-surface text-ink-primary shadow-xs' : 'text-ink-secondary'"
                    class="px-4 py-2 rounded-badge text-sm font-semibold transition-colors cursor-pointer">
                Daftar Menu ({{ $products->count() }})
            </button>
            <button type="button" @click="activeTab = 'modifiers'"
                    :class="activeTab === 'modifiers' ? 'bg-surface text-ink-primary shadow-xs' : 'text-ink-secondary'"
                    class="px-4 py-2 rounded-badge text-sm font-semibold transition-colors cursor-pointer">
                Topping / Tambahan ({{ $modifiers->count() }})
            </button>
        </div>

        <div class="flex gap-2">
            <button x-show="activeTab === 'products'" @click="modalProduct = true" type="button"
                    class="h-11 px-4 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">
                + Tambah Menu
            </button>
            <button x-show="activeTab === 'modifiers'" @click="modalModifier = true" type="button"
                    class="h-11 px-4 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">
                + Tambah Topping
            </button>
        </div>
    </div>

    <!-- TAB 1: Daftar Menu -->
    <div x-show="activeTab === 'products'" class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-subtle border-b border-border text-xs font-semibold text-ink-secondary uppercase">
                        <th class="py-3 px-4">Nama Menu</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4 text-right">Harga Jual</th>
                        <th class="py-3 px-4 text-right">Est. HPP</th>
                        <th class="py-3 px-4 text-right">Margin Kotor</th>
                        <th class="py-3 px-4">Komposisi Resep</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @foreach ($products as $product)
                        @php
                            $hpp = $product->estimated_hpp;
                            $price = (float) $product->price;
                            $margin = $price > 0 ? (($price - $hpp) / $price) * 100 : 0;
                        @endphp
                        <tr class="hover:bg-canvas/60">
                            <td class="py-3.5 px-4 font-semibold text-ink-primary">
                                {{ $product->name }}
                                @if (! $product->is_available)
                                    <span class="ml-1.5 px-1.5 py-0.5 rounded-badge text-[11px] bg-semantic-dangerBg text-semantic-dangerText">Habis</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-ink-secondary">{{ $product->category->name }}</td>
                            <td class="py-3.5 px-4 text-right font-mono tabular-nums font-bold">Rp {{ number_format($price, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-mono tabular-nums text-ink-secondary">Rp {{ number_format($hpp, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-mono tabular-nums font-semibold {{ $margin >= 50 ? 'text-semantic-successText' : 'text-semantic-warningText' }}">
                                {{ number_format($margin, 1) }}%
                            </td>
                            <td class="py-3.5 px-4 text-xs text-ink-secondary">
                                @if ($product->recipes->isEmpty())
                                    <span class="text-semantic-dangerText font-semibold">Belum diatur</span>
                                @else
                                    {{ $product->recipes->map(fn($r) => $r->ingredient->name . ' (' . (float)$r->quantity_required . ' ' . $r->ingredient->unit . ')')->implode(', ') }}
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click='openRecipeEditor("product", {{ Js::from($product->id) }}, {{ Js::from($product->name) }}, {{ Js::from($product->price) }}, {{ Js::from($product->recipes) }})'
                                    class="px-3 py-1.5 rounded-badge bg-brand-tint text-brand-primary hover:bg-brand-primary hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    Atur Resep
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: Topping / Tambahan -->
    <div x-show="activeTab === 'modifiers'" x-cloak class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-subtle border-b border-border text-xs font-semibold text-ink-secondary uppercase">
                        <th class="py-3 px-4">Nama Topping</th>
                        <th class="py-3 px-4 text-right">Harga Tambah</th>
                        <th class="py-3 px-4 text-right">Est. HPP</th>
                        <th class="py-3 px-4">Komposisi Resep</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-sm">
                    @foreach ($modifiers as $mod)
                        <tr class="hover:bg-canvas/60">
                            <td class="py-3.5 px-4 font-semibold text-ink-primary">{{ $mod->name }}</td>
                            <td class="py-3.5 px-4 text-right font-mono tabular-nums font-bold">Rp {{ number_format($mod->price, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-right font-mono tabular-nums text-ink-secondary">Rp {{ number_format($mod->estimated_hpp, 0, ',', '.') }}</td>
                            <td class="py-3.5 px-4 text-xs text-ink-secondary">
                                @if ($mod->recipes->isEmpty())
                                    <span class="text-semantic-dangerText font-semibold">Belum diatur</span>
                                @else
                                    {{ $mod->recipes->map(fn($r) => $r->ingredient->name . ' (' . (float)$r->quantity_required . ' ' . $r->ingredient->unit . ')')->implode(', ') }}
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <button
                                    type="button"
                                    @click='openRecipeEditor("modifier", {{ Js::from($mod->id) }}, {{ Js::from($mod->name) }}, {{ Js::from($mod->price) }}, {{ Js::from($mod->recipes) }})'
                                    class="px-3 py-1.5 rounded-badge bg-brand-tint text-brand-primary hover:bg-brand-primary hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                                >
                                    Atur Resep
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: Tambah Menu -->
    <div x-show="modalProduct" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="modalProduct = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6">
            <h2 class="text-lg font-bold text-ink-primary mb-4">Tambah Menu Baru</h2>
            <form method="POST" action="{{ route('catalog.products.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Kategori</label>
                    <select name="category_id" required class="w-full h-11 rounded-control border border-border-strong text-sm">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Nama Menu</label>
                    <input type="text" name="name" required placeholder="Contoh: Indomie Goreng Rendang"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Harga Jual (Rp)</label>
                    <input type="number" step="500" min="0" name="price" required placeholder="8500"
                           class="w-full h-11 rounded-control border border-border-strong font-mono">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalProduct = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Simpan Menu</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Tambah Topping -->
    <div x-show="modalModifier" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="modalModifier = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6">
            <h2 class="text-lg font-bold text-ink-primary mb-4">Tambah Topping Baru</h2>
            <form method="POST" action="{{ route('catalog.modifiers.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Nama Topping</label>
                    <input type="text" name="name" required placeholder="Contoh: Keju Parut"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Harga Tambahan (Rp)</label>
                    <input type="number" step="500" min="0" name="price" required placeholder="3000"
                           class="w-full h-11 rounded-control border border-border-strong font-mono">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalModifier = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Simpan Topping</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDITOR RESEP (BOM) -->
    <div x-show="recipeDrawer" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="recipeDrawer = false" class="w-full max-w-lg bg-surface border border-border rounded-modal shadow-floating p-6 max-h-[90vh] overflow-y-auto">
            <div class="mb-4">
                <span class="text-xs font-semibold uppercase text-brand-primary">Komposisi Resep (Auto-Deduct Stok)</span>
                <h2 class="text-lg font-bold text-ink-primary" x-text="recipeTarget.name"></h2>
            </div>

            <form method="POST" action="{{ route('catalog.recipes.sync') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="target_type" :value="recipeTarget.type">
                <input type="hidden" name="target_id" :value="recipeTarget.id">

                <div class="space-y-2.5">
                    <template x-for="(row, index) in recipeRows" :key="index">
                        <div class="flex items-center gap-2">
                            <select :name="'items[' + index + '][ingredient_id]'" x-model="row.ingredient_id" required
                                    class="flex-1 h-11 rounded-control border border-border-strong text-sm">
                                <option value="">-- Pilih Bahan Baku --</option>
                                @foreach ($ingredients as $ing)
                                    <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                                @endforeach
                            </select>

                            <input type="number" step="0.01" min="0.01"
                                   :name="'items[' + index + '][quantity_required]'"
                                   x-model="row.quantity_required" required
                                   placeholder="Takaran"
                                   class="w-28 h-11 rounded-control border border-border-strong font-mono text-sm">

                            <button type="button" @click="removeRecipeRow(index)"
                                    aria-label="Hapus baris bahan"
                                    class="h-11 px-3 rounded-control border border-border text-semantic-dangerText hover:bg-semantic-dangerBg text-xs font-bold cursor-pointer">
                                Hapus
                            </button>
                        </div>
                    </template>
                </div>

                <button type="button" @click="addRecipeRow()"
                        class="w-full h-10 rounded-control border border-dashed border-border-strong text-xs font-semibold text-ink-secondary hover:text-ink-primary cursor-pointer">
                    + Tambah Baris Bahan Baku
                </button>

                <!-- Ringkasan Kalkulasi HPP & Margin Live -->
                <div class="p-3.5 rounded-control bg-subtle border border-border flex items-center justify-between text-sm">
                    <div>
                        <p class="text-xs text-ink-secondary">Harga Jual: <strong class="font-mono text-ink-primary" x-text="formatRp(recipeTarget.price)"></strong></p>
                        <p class="text-xs text-ink-secondary mt-0.5">Estimasi HPP Resep: <strong class="font-mono text-ink-primary" x-text="formatRp(computedHpp())"></strong></p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-ink-secondary block">Laba Kotor / Porsi</span>
                        <span class="font-mono font-bold text-base text-semantic-successText" x-text="formatRp(recipeTarget.price - computedHpp())"></span>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="recipeDrawer = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Simpan Resep</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection