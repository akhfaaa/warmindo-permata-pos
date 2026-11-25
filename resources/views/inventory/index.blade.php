@extends('layouts.app')

@section('title', 'Stok Bahan Baku')
@section('page_title', 'Stok Bahan Baku & Restock')

@section('content')
<div
    x-data="{
        modalRestock: false,
        modalAdd: false,
        modalOpname: false,
        selectedIngredient: { id: '', name: '', unit: '', current_stock: 0 },
        openRestock(ing = null) {
            if (ing) {
                this.selectedIngredient = ing;
            } else {
                this.selectedIngredient = { id: '', name: '', unit: '', current_stock: 0 };
            }
            this.modalRestock = true;
        },
        openOpname(ing) {
            this.selectedIngredient = ing;
            this.modalOpname = true;
        }
    }"
    class="max-w-6xl mx-auto space-y-6"
>
    <!-- Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('inventory.index', ['filter' => 'all']) }}"
               class="px-3.5 py-2 rounded-full text-xs font-semibold transition-colors {{ $filter === 'all' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                Semua Bahan
            </a>
            <a href="{{ route('inventory.index', ['filter' => 'low']) }}"
               class="px-3.5 py-2 rounded-full text-xs font-semibold transition-colors {{ $filter === 'low' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                Stok Menipis
            </a>
            <a href="{{ route('inventory.index', ['filter' => 'critical']) }}"
               class="px-3.5 py-2 rounded-full text-xs font-semibold transition-colors {{ $filter === 'critical' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                Stok Habis / Minus
            </a>
        </div>

        <div class="flex items-center gap-2">
            @if (auth()->user()->isOwner())
                <button @click="modalAdd = true" type="button"
                        class="h-11 px-4 rounded-control border border-border-strong bg-surface hover:bg-subtle text-ink-primary text-sm font-semibold cursor-pointer">
                    + Bahan Baru
                </button>
            @endif
            <button @click="openRestock()" type="button"
                    class="h-11 px-4 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">
                + Catat Belanja (Restock)
            </button>
        </div>
    </div>

    <!-- Tabel Stok Bahan Baku -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        @if ($ingredients->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-semibold text-ink-primary">Tidak ada bahan baku pada filter ini.</p>
                <p class="text-xs text-ink-secondary mt-1">Ubah filter di atas atau tambahkan bahan baku baru.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-subtle border-b border-border text-xs font-semibold text-ink-secondary uppercase">
                            <th class="py-3 px-4">Nama Bahan Baku</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Sisa Stok</th>
                            <th class="py-3 px-4 text-right">Batas Min.</th>
                            <th class="py-3 px-4 text-right">HPP Rata-rata</th>
                            <th class="py-3 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        @foreach ($ingredients as $item)
                            @php
                                $stock = (float) $item->current_stock;
                                $min = (float) $item->min_stock_alert;
                            @endphp
                            <tr class="hover:bg-canvas/60">
                                <td class="py-3.5 px-4 font-semibold text-ink-primary">{{ $item->name }}</td>
                                <td class="py-3.5 px-4">
                                    @if ($stock <= 0)
                                        <span class="inline-block px-2 py-0.5 rounded-badge text-xs font-semibold bg-semantic-dangerBg text-semantic-dangerText">
                                            Habis / Minus
                                        </span>
                                    @elseif ($stock <= $min)
                                        <span class="inline-block px-2 py-0.5 rounded-badge text-xs font-semibold bg-semantic-warningBg text-semantic-warningText">
                                            Menipis
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-badge text-xs font-semibold bg-semantic-successBg text-semantic-successText">
                                            Aman
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono tabular-nums font-bold {{ $stock <= 0 ? 'text-semantic-dangerText' : 'text-ink-primary' }}">
                                    {{ rtrim(rtrim(number_format($stock, 2, '.', ''), '0'), '.') }} <span class="text-xs font-sans font-normal text-ink-secondary">{{ $item->unit }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono tabular-nums text-ink-secondary">
                                    {{ rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') }} {{ $item->unit }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono tabular-nums text-ink-primary">
                                    Rp {{ number_format($item->avg_cost_per_unit, 0, ',', '.') }}<span class="text-xs font-sans text-ink-secondary">/{{ $item->unit }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-right space-x-1.5 whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click='openRestock(@json(["id" => $item->id, "name" => $item->name, "unit" => $item->unit, "current_stock" => $item->current_stock]))'
                                        class="px-3 py-1.5 rounded-badge bg-brand-tint text-brand-primary hover:bg-brand-primary hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                                    >
                                        + Restock
                                    </button>
                                    @if (auth()->user()->isOwner())
                                        <button
                                            type="button"
                                            @click='openOpname(@json(["id" => $item->id, "name" => $item->name, "unit" => $item->unit, "current_stock" => $item->current_stock]))'
                                            class="px-3 py-1.5 rounded-badge border border-border-strong text-ink-secondary hover:text-ink-primary text-xs font-semibold cursor-pointer"
                                        >
                                            Opname
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Riwayat Mutasi Stok Terakhir -->
    <div class="bg-surface border border-border rounded-control p-4 lg:p-5">
        <h2 class="text-base font-bold text-ink-primary mb-3">15 Mutasi Stok Terakhir</h2>
        <div class="divide-y divide-border text-sm">
            @forelse ($recentMovements as $mov)
                <div class="py-2.5 flex items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-ink-primary">{{ $mov->ingredient->name }}</p>
                        <p class="text-xs text-ink-secondary">{{ $mov->notes }} • {{ $mov->created_at->format('d M H:i') }}</p>
                    </div>
                    <span class="font-mono tabular-nums font-bold {{ $mov->quantity_change >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }}">
                        {{ $mov->quantity_change >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($mov->quantity_change, 2, '.', ''), '0'), '.') }} {{ $mov->ingredient->unit }}
                    </span>
                </div>
            @empty
                <p class="text-xs text-ink-muted py-2">Belum ada riwayat pergerakan stok.</p>
            @endforelse
        </div>
    </div>

    <!-- MODAL 1: Catat Belanja (Restock) -->
    <div x-show="modalRestock" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="modalRestock = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6">
            <h2 class="text-lg font-bold text-ink-primary">Catat Belanja (Restock)</h2>
            <p class="text-xs text-ink-secondary mt-0.5 mb-4">Saldo stok bertambah dan HPP rata-rata otomatis dihitung ulang.</p>

            <form method="POST" action="{{ route('inventory.restock') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Pilih Bahan Baku</label>
                    <select name="ingredient_id" x-model="selectedIngredient.id" required
                            class="w-full h-11 rounded-control border border-border-strong bg-surface text-sm">
                        <option value="">-- Pilih Bahan --</option>
                        @foreach ($ingredients as $ing)
                            <option value="{{ $ing->id }}">{{ $ing->name }} (Satuan: {{ $ing->unit }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Jumlah Masuk (dalam satuan terkecil: butir/g/ml/pcs)</label>
                    <input type="number" step="0.01" min="0.01" name="quantity_added" required placeholder="Contoh: 60"
                           class="w-full h-11 rounded-control border border-border-strong font-mono">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Total Harga Beli (Rp)</label>
                    <input type="number" step="100" min="0" name="total_purchase_price" required placeholder="Contoh: 108000"
                           class="w-full h-11 rounded-control border border-border-strong font-mono">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">Catatan (Opsional)</label>
                    <input type="text" name="notes" placeholder="Contoh: Belanja Pasar Pagi"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalRestock = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Simpan Stok</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Stock Opname (Owner Only) -->
    @if (auth()->user()->isOwner())
    <div x-show="modalOpname" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="modalOpname = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6">
            <h2 class="text-lg font-bold text-ink-primary">Stock Opname Fisik</h2>
            <p class="text-xs text-ink-secondary mt-0.5 mb-4">
                Bahan: <strong class="text-ink-primary" x-text="selectedIngredient.name"></strong>
                (Stok sistem: <span class="font-mono" x-text="selectedIngredient.current_stock + ' ' + selectedIngredient.unit"></span>)
            </p>

            <form method="POST" action="{{ route('inventory.opname') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="ingredient_id" :value="selectedIngredient.id">
                <div>
                    <label class="block text-sm font-semibold mb-1">Jumlah Hitungan Fisik Aktual</label>
                    <input type="number" step="0.01" min="0" name="actual_stock" required
                           class="w-full h-11 rounded-control border border-border-strong font-mono">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Alasan Penyesuaian (Wajib)</label>
                    <input type="text" name="notes" required placeholder="Contoh: 2 butir telur pecah saat penyimpanan"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalOpname = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Sesuaikan Stok</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Tambah Bahan Baku Baru (Owner Only) -->
    <div x-show="modalAdd" x-cloak class="fixed inset-0 z-50 bg-ink-primary/50 flex items-center justify-center p-4">
        <div @click.away="modalAdd = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6">
            <h2 class="text-lg font-bold text-ink-primary mb-4">Tambah Bahan Baku Baru</h2>
            <form method="POST" action="{{ route('inventory.store') }}" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Nama Bahan Baku</label>
                    <input type="text" name="name" required placeholder="Contoh: Keju Cheddar Blok"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Satuan Terkecil</label>
                        <select name="unit" required class="w-full h-11 rounded-control border border-border-strong text-sm">
                            <option value="pcs">pcs</option>
                            <option value="bungkus">bungkus</option>
                            <option value="butir">butir</option>
                            <option value="g">gram (g)</option>
                            <option value="ml">mililiter (ml)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Batas Alert Menipis</label>
                        <input type="number" step="0.01" min="0" name="min_stock_alert" value="10" required
                               class="w-full h-11 rounded-control border border-border-strong font-mono">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold mb-1">Stok Awal</label>
                        <input type="number" step="0.01" min="0" name="current_stock" value="0" required
                               class="w-full h-11 rounded-control border border-border-strong font-mono">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-1">Harga Pokok / Satuan</label>
                        <input type="number" step="1" min="0" name="avg_cost_per_unit" value="0" required
                               class="w-full h-11 rounded-control border border-border-strong font-mono">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="modalAdd = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-semibold cursor-pointer">Simpan Bahan</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection