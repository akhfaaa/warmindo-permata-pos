@extends('layouts.app')

@section('title', 'Menu & Resep')
@section('page_title', 'Katalog Menu, Topping & Resep BOM')

@section('content')
<div x-data="catalogApp()" class="max-w-7xl mx-auto space-y-6">

    <!-- Header Aksi -->
    <div class="bg-surface border border-border rounded-control p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink-primary">Daftar Menu & Komposisi Bahan Baku</h2>
            <p class="text-xs text-ink-secondary mt-0.5">
                Klik <strong>Atur Resep</strong> pada setiap menu agar stok bahan baku otomatis terpotong setiap kali terjual di kasir.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" @click="openCreateModifierModal()"
                    class="h-11 px-4 rounded-control border border-border-strong bg-surface hover:bg-subtle text-xs font-bold text-ink-primary cursor-pointer">
                + Tambah Topping
            </button>
            <button type="button" @click="openCreateProductModal()"
                    class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                + Tambah Menu Baru
            </button>
        </div>
    </div>

    <!-- Tabel Daftar Menu -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3.5 px-4">Nama Menu & Kategori</th>
                        <th class="py-3.5 px-4 text-right">Harga Jual</th>
                        <th class="py-3.5 px-4 text-right">Est. HPP Bahan</th>
                        <th class="py-3.5 px-4">Komposisi Resep</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($products as $prod)
                        @php
                            $isAvail = (bool) ($prod->is_available ?? $prod->is_active ?? true);
                        @endphp
                        <tr class="hover:bg-subtle/40">
                            <td class="py-3.5 px-4">
                                <p class="font-bold text-ink-primary">{{ $prod->name }}</p>
                                <span class="text-xs text-ink-secondary">{{ $prod->category?->name ?? 'Umum' }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-ink-primary">
                                Rp {{ number_format($prod->price, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-ink-secondary">
                                Rp {{ number_format($prod->estimated_hpp, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($prod->recipes as $rec)
                                        <span class="px-2 py-0.5 rounded-badge bg-subtle border border-border text-[11px] font-semibold text-ink-primary">
                                            {{ $rec->ingredient?->name }} ({{ (float) ($rec->quantity_required ?? $rec->quantity ?? $rec->qty_required ?? 0) }} {{ $rec->ingredient?->unit }})
                                        </span>
                                    @empty
                                        <span class="text-xs italic text-semantic-warningText">Belum ada resep</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($isAvail)
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-successBg text-semantic-successText">Tersedia</span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-dangerBg text-semantic-dangerText">Habis</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                <button
                                    type="button"
                                    @click='openRecipeModal({
                                        id: @json($prod->id),
                                        name: @json($prod->name),
                                        items: @json($prod->recipe_items_json)
                                    })'
                                    class="h-9 px-3 rounded-control bg-brand-tint hover:bg-brand-primary text-brand-primary hover:text-white text-xs font-bold transition-colors cursor-pointer"
                                >
                                    Atur Resep
                                </button>

                                @if (auth()->user()->canEditDelete())
                                    <button
                                        type="button"
                                        @click='openEditProductModal({
                                            id: @json($prod->id),
                                            category_id: @json($prod->category_id),
                                            name: @json($prod->name),
                                            price: {{ (float) $prod->price }},
                                            is_available: {{ $isAvail ? "true" : "false" }}
                                        })'
                                        class="h-9 px-3 rounded-control border border-semantic-infoText/30 bg-semantic-infoBg text-semantic-infoText text-xs font-bold cursor-pointer"
                                    >
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('catalog.products.destroy', $prod) }}" class="inline"
                                          onsubmit="return confirm('Hapus menu {{ $prod->name }} secara permanen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="h-9 px-3 rounded-control border border-semantic-dangerText/30 bg-semantic-dangerBg text-semantic-dangerText text-xs font-bold cursor-pointer">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-xs text-ink-muted">Belum ada data menu.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Daftar Topping -->
    <div class="bg-surface border border-border rounded-control p-5 space-y-3">
        <h3 class="text-sm font-bold text-ink-primary">Daftar Topping / Tambahan</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
            @forelse ($modifiers as $mod)
                @php
                    $modPrice = (float) ($mod->price ?? $mod->additional_price ?? 0);
                @endphp
                <div class="p-3 rounded-control bg-subtle border border-border flex items-center justify-between gap-2">
                    <div>
                        <span class="text-xs font-bold text-ink-primary block">{{ $mod->name }}</span>
                        <span class="font-mono text-xs font-bold text-brand-primary">+Rp {{ number_format($modPrice, 0, ',', '.') }}</span>
                    </div>

                    @if (auth()->user()->canEditDelete())
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click='openEditModifierModal({
                                    id: @json($mod->id),
                                    name: @json($mod->name),
                                    price: {{ $modPrice }}
                                })'
                                class="h-8 px-2.5 rounded-badge border border-semantic-infoText/30 bg-semantic-infoBg text-semantic-infoText text-[11px] font-bold cursor-pointer"
                            >
                                Edit
                            </button>
                            <form method="POST" action="{{ route('catalog.modifiers.destroy', $mod) }}" class="inline"
                                  onsubmit="return confirm('Hapus topping {{ $mod->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="h-8 px-2.5 rounded-badge border border-semantic-dangerText/30 bg-semantic-dangerBg text-semantic-dangerText text-[11px] font-bold cursor-pointer">
                                    Hapus
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <span class="text-xs text-ink-muted">Belum ada topping tambahan.</span>
            @endforelse
        </div>
    </div>

    <!-- MODAL 1: Atur Resep Bahan Baku (BOM) -->
    <div x-show="recipeModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="recipeModal.open = false" class="w-full max-w-xl bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <span class="text-xs font-bold uppercase text-brand-primary">Komposisi Resep (BOM)</span>
                    <h3 class="text-lg font-bold text-ink-primary" x-text="recipeModal.productName"></h3>
                </div>
                <button type="button" @click="recipeModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" action="{{ route('catalog.recipes.sync') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="product_id" :value="recipeModal.productId">

                <div class="space-y-2.5">
                    <template x-for="(row, idx) in recipeModal.items" :key="idx">
                        <div class="flex items-center gap-2">
                            <select :name="'items[' + idx + '][ingredient_id]'" x-model="row.ingredient_id" required
                                    class="flex-1 h-11 px-3 rounded-control border border-border-strong text-xs font-semibold bg-surface">
                                <option value="">-- Pilih Bahan Baku --</option>
                                @foreach ($ingredients as $ing)
                                    <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit }})</option>
                                @endforeach
                            </select>

                            <input type="number" step="0.01" min="0.01" :name="'items[' + idx + '][quantity]'" x-model.number="row.quantity" required
                                   placeholder="Takaran"
                                   class="w-28 h-11 px-3 rounded-control border border-border-strong font-mono text-xs font-bold">

                            <button type="button" @click="removeRecipeRow(idx)"
                                    class="h-11 px-3 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-xs font-bold cursor-pointer">
                                Hapus
                            </button>
                        </div>
                    </template>

                    <div x-show="recipeModal.items.length === 0" class="p-4 rounded-control bg-subtle text-center text-xs text-ink-muted">
                        Belum ada bahan baku dalam resep ini. Klik tombol "+ Tambah Bahan" di bawah.
                    </div>
                </div>

                <button type="button" @click="addRecipeRow()"
                        class="h-10 px-4 rounded-control border border-brand-primary bg-brand-tint text-brand-primary text-xs font-bold cursor-pointer">
                    + Tambah Bahan
                </button>

                <div class="flex justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" @click="recipeModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-6 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                        Simpan Resep Menu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Tambah / Edit Menu -->
    <div x-show="productModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="productModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-lg font-bold text-ink-primary" x-text="productModal.isEdit ? 'Edit Menu' : 'Tambah Menu Baru'"></h3>
                <button type="button" @click="productModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" :action="productModal.isEdit ? ('/catalog/products/' + productModal.id) : '{{ route('catalog.products.store') }}'" class="space-y-4">
                @csrf
                <template x-if="productModal.isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Kategori (Makanan / Minuman)</label>
                    <select name="category_id" x-model="productModal.category_id" required
                            class="w-full h-11 px-3 rounded-control border border-border-strong text-sm bg-surface">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Nama Makanan / Minuman</label>
                    <input type="text" name="name" x-model="productModal.name" required
                           placeholder="Contoh: Indomie Goreng Spesial / Es Teh Manis"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Harga Jual (Rp)</label>
                    <input type="number" step="500" min="0" name="price" x-model.number="productModal.price" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink-primary cursor-pointer">
                    <input type="checkbox" name="is_available" value="1" x-model="productModal.is_available"
                           class="rounded border-border-strong text-brand-primary">
                    <span>Menu Tersedia (Tampil Aktif di Kasir)</span>
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="productModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary text-white text-xs font-bold cursor-pointer">Simpan Menu</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Tambah / Edit Topping -->
    <div x-show="modifierModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="modifierModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-lg font-bold text-ink-primary" x-text="modifierModal.isEdit ? 'Edit Topping' : 'Tambah Topping Baru'"></h3>
                <button type="button" @click="modifierModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" :action="modifierModal.isEdit ? ('/catalog/modifiers/' + modifierModal.id) : '{{ route('catalog.modifiers.store') }}'" class="space-y-4">
                @csrf
                <template x-if="modifierModal.isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Nama Topping</label>
                    <input type="text" name="name" x-model="modifierModal.name" required placeholder="Contoh: Telur Ceplok / Keju Parut"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Harga Tambahan (Rp)</label>
                    <input type="number" step="500" min="0" name="price" x-model.number="modifierModal.price" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modifierModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary text-white text-xs font-bold cursor-pointer">Simpan Topping</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function catalogApp() {
    return {
        modifierModal: {
            open: false,
            isEdit: false,
            id: '',
            name: '',
            price: 3000,
        },
        recipeModal: {
            open: false,
            productId: '',
            productName: '',
            items: [],
        },
        productModal: {
            open: false,
            isEdit: false,
            id: '',
            category_id: '{{ $categories->first()?->id ?? "" }}',
            name: '',
            price: 0,
            is_available: true,
        },

        openRecipeModal(data) {
            this.recipeModal = {
                open: true,
                productId: data.id,
                productName: data.name,
                items: (data.items || []).map(r => ({
                    ingredient_id: r.ingredient_id,
                    quantity: Number(r.quantity) || 1,
                })),
            };
        },

        addRecipeRow() {
            this.recipeModal.items.push({
                ingredient_id: '',
                quantity: 1,
            });
        },

        removeRecipeRow(idx) {
            this.recipeModal.items.splice(idx, 1);
        },

        openCreateProductModal() {
            this.productModal = {
                open: true,
                isEdit: false,
                id: '',
                category_id: '{{ $categories->first()?->id ?? "" }}',
                name: '',
                price: 0,
                is_available: true,
            };
        },

        openEditProductModal(data) {
            this.productModal = {
                open: true,
                isEdit: true,
                id: data.id,
                category_id: data.category_id,
                name: data.name,
                price: Number(data.price) || 0,
                is_available: data.is_available,
            };
        },

        openCreateModifierModal() {
            this.modifierModal = {
                open: true,
                isEdit: false,
                id: '',
                name: '',
                price: 3000,
            };
        },

        openEditModifierModal(data) {
            this.modifierModal = {
                open: true,
                isEdit: true,
                id: data.id,
                name: data.name,
                price: Number(data.price) || 0,
            };
        },
    };
}
</script>
@endsection