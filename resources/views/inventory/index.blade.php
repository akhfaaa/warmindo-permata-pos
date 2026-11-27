@extends('layouts.app')

@section('title', 'Stok & Restock')
@section('page_title', 'Inventori Bahan Baku & Catatan Belanja')

@section('content')
<div x-data="inventoryApp()" class="max-w-7xl mx-auto space-y-6">

    <!-- Header & Tombol Tambah Bahan -->
    <div class="bg-surface border border-border rounded-control p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink-primary">Stok Bahan Baku Warung</h2>
            <p class="text-xs text-ink-secondary mt-0.5">
                Klik <strong>+ Restock</strong> untuk menambah stok sekaligus mencatat pengeluaran belanja bulanan.
                @if ($lowStockCount > 0)
                    <span class="ml-1 px-2 py-0.5 rounded-badge bg-semantic-warningBg text-semantic-warningText font-bold">
                        {{ $lowStockCount }} Bahan Menipis
                    </span>
                @endif
            </p>
        </div>

        @if (auth()->user()->canEditDelete())
            <button type="button" @click="createModalOpen = true"
                    class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-bold cursor-pointer self-start sm:self-auto">
                + Tambah Bahan Baru
            </button>
        @endif
    </div>

    <!-- Tabel Stok Bahan Baku -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3.5 px-4">Nama Bahan Baku</th>
                        <th class="py-3.5 px-4 text-right">Stok Tersedia</th>
                        <th class="py-3.5 px-4 text-right">Batas Min.</th>
                        <th class="py-3.5 px-4 text-right">Harga Beli / Satuan</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($ingredients as $ing)
                        @php
                            $isLow = (float) $ing->current_stock <= $ing->resolved_min_stock;
                        @endphp
                        <tr class="hover:bg-subtle/40">
                            <td class="py-3.5 px-4 font-bold text-ink-primary">{{ $ing->name }}</td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold {{ $isLow ? 'text-semantic-dangerText' : 'text-ink-primary' }}">
                                {{ number_format($ing->current_stock, 0, ',', '.') }} {{ $ing->unit }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-ink-secondary">
                                {{ number_format($ing->resolved_min_stock, 0, ',', '.') }} {{ $ing->unit }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-ink-secondary">
                                Rp {{ number_format($ing->resolved_cost, 0, ',', '.') }} / {{ $ing->unit }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if ($isLow)
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-warningBg text-semantic-warningText">
                                        Perlu Belanja
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-successBg text-semantic-successText">
                                        Aman
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-1.5">
                                <button
                                    type="button"
                                    @click='openRestockModal({
                                        id: @json($ing->id),
                                        name: @json($ing->name),
                                        unit: @json($ing->unit),
                                        unit_cost: {{ (float) $ing->resolved_cost }}
                                    })'
                                    class="h-9 px-3 rounded-control bg-brand-tint hover:bg-brand-primary text-brand-primary hover:text-white text-xs font-bold transition-colors cursor-pointer"
                                >
                                    + Restock
                                </button>

                                @if (auth()->user()->canEditDelete())
                                    <button
                                        type="button"
                                        @click='openOpnameModal({
                                            id: @json($ing->id),
                                            name: @json($ing->name),
                                            unit: @json($ing->unit),
                                            current_stock: {{ (float) $ing->current_stock }}
                                        })'
                                        class="h-9 px-3 rounded-control border border-border-strong bg-surface hover:bg-subtle text-ink-primary text-xs font-bold cursor-pointer"
                                    >
                                        Opname
                                    </button>

                                    <button
                                        type="button"
                                        @click='openEditModal({
                                            id: @json($ing->id),
                                            name: @json($ing->name),
                                            unit: @json($ing->unit),
                                            min_stock: {{ (float) $ing->resolved_min_stock }},
                                            cost_per_unit: {{ (float) $ing->resolved_cost }}
                                        })'
                                        class="h-9 px-3 rounded-control border border-semantic-infoText/30 bg-semantic-infoBg text-semantic-infoText text-xs font-bold cursor-pointer"
                                    >
                                        Edit
                                    </button>

                                    <form method="POST" action="{{ route('inventory.destroy', $ing) }}" class="inline"
                                          onsubmit="return confirm('Hapus bahan baku {{ $ing->name }} secara permanen?')">
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
                            <td colspan="6" class="py-8 text-center text-xs text-ink-muted">
                                Belum ada data bahan baku.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log Pergerakan Stok & Belanja Terbaru -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="p-4 border-b border-border flex items-center justify-between">
            <h3 class="text-sm font-bold text-ink-primary">Riwayat Mutasi Stok & Belanja Terakhir</h3>
            @if (auth()->user()->hasMenu('recap'))
                <a href="{{ route('recap.index') }}" class="text-xs font-bold text-brand-primary hover:underline">
                    Lihat Rekap Belanja Bulanan &rarr;
                </a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3 px-4">Waktu</th>
                        <th class="py-3 px-4">Bahan Baku</th>
                        <th class="py-3 px-4">Jenis</th>
                        <th class="py-3 px-4 text-right">Perubahan</th>
                        <th class="py-3 px-4 text-right">Total Belanja</th>
                        <th class="py-3 px-4">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($movements as $mov)
                        @php
                            $qtyVal = (float) ($mov->resolved_qty ?? 0);
                            $costVal = (float) ($mov->total_cost ?? 0);
                        @endphp
                        <tr>
                            <td class="py-2.5 px-4 font-mono text-ink-secondary">{{ $mov->created_at?->format('d M Y H:i') }}</td>
                            <td class="py-2.5 px-4 font-bold text-ink-primary">{{ $mov->ingredient?->name ?? '-' }}</td>
                            <td class="py-2.5 px-4">
                                <span class="px-2 py-0.5 rounded-badge font-mono font-bold {{ $qtyVal >= 0 ? 'bg-semantic-successBg text-semantic-successText' : 'bg-subtle text-ink-secondary' }}">
                                    {{ $mov->type ?? ($qtyVal >= 0 ? 'IN' : 'OUT') }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold {{ $qtyVal >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }}">
                                {{ $qtyVal > 0 ? '+' : '' }}{{ number_format($qtyVal, 0, ',', '.') }} {{ $mov->ingredient?->unit }}
                            </td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold text-ink-primary">
                                {{ $costVal > 0 ? 'Rp ' . number_format($costVal, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-4 text-ink-secondary">{{ $mov->notes ?? $mov->description ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-ink-muted">Belum ada catatan mutasi stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL 1: + Restock & Catat Belanja -->
    <div x-show="restockModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="restockModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <span class="text-xs font-bold uppercase text-brand-primary">Tambah Stok & Catat Belanja</span>
                    <h3 class="text-lg font-bold text-ink-primary" x-text="restockModal.name"></h3>
                </div>
                <button type="button" @click="restockModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" action="{{ route('inventory.restock') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="ingredient_id" :value="restockModal.id">

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">
                        Jumlah Belanja (<span x-text="restockModal.unit"></span>)
                    </label>
                    <input type="number" step="0.01" min="0.01" name="quantity" x-model.number="restockModal.quantity"
                           @input="recalcRestockCost()" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">
                        Total Harga Belanja (Rp)
                    </label>
                    <input type="number" step="100" min="0" name="total_cost" x-model.number="restockModal.total_cost" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Catatan Belanja (Opsional)</label>
                    <input type="text" name="notes" x-model="restockModal.notes"
                           placeholder="Contoh: Belanja Pasar / Agen Indomie 1 dus"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="restockModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                        Simpan Restock & Belanja
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Stock Opname -->
    <div x-show="opnameModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="opnameModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <span class="text-xs font-bold uppercase text-ink-secondary">Penyesuaian Stok Fisik</span>
                    <h3 class="text-lg font-bold text-ink-primary" x-text="opnameModal.name"></h3>
                </div>
                <button type="button" @click="opnameModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" action="{{ route('inventory.opname') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="ingredient_id" :value="opnameModal.id">

                <div class="p-3 rounded-control bg-subtle text-xs flex justify-between">
                    <span class="text-ink-secondary">Stok Tercatat di Sistem:</span>
                    <strong class="font-mono text-ink-primary" x-text="opnameModal.current_stock + ' ' + opnameModal.unit"></strong>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">
                        Jumlah Stok Fisik Sebenarnya (<span x-text="opnameModal.unit"></span>)
                    </label>
                    <input type="number" step="0.01" min="0" name="actual_stock" x-model.number="opnameModal.actual_stock" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Alasan Selisih / Catatan Opname</label>
                    <input type="text" name="notes" x-model="opnameModal.notes" required
                           placeholder="Contoh: Hitung fisik akhir shift / bahan rusak"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="opnameModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-ink-primary text-white text-xs font-bold cursor-pointer">
                        Simpan Stock Opname
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Edit Master Bahan Baku -->
    <div x-show="editModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="editModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-lg font-bold text-ink-primary">Edit Bahan Baku</h3>
                <button type="button" @click="editModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" :action="'/inventory/' + editModal.id" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Nama Bahan Baku</label>
                    <input type="text" name="name" x-model="editModal.name" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Satuan</label>
                        <input type="text" name="unit" x-model="editModal.unit" required
                               class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Batas Minimum</label>
                        <input type="number" step="0.01" min="0" name="min_stock" x-model.number="editModal.min_stock" required
                               class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Harga Beli / Satuan (Rp)</label>
                    <input type="number" step="1" min="0" name="cost_per_unit" x-model.number="editModal.cost_per_unit" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary text-white text-xs font-bold cursor-pointer">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: Tambah Bahan Baku Baru -->
    <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="createModalOpen = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <h3 class="text-lg font-bold text-ink-primary">Tambah Bahan Baku Baru</h3>
                <button type="button" @click="createModalOpen = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" action="{{ route('inventory.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Nama Bahan Baku</label>
                    <input type="text" name="name" required placeholder="Contoh: Indomie Goreng / Telur Ayam"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Satuan</label>
                        <input type="text" name="unit" required placeholder="bungkus / butir / gram"
                               class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Harga Beli / Satuan (Rp)</label>
                        <input type="number" step="1" min="0" name="cost_per_unit" required value="0"
                               class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Stok Awal</label>
                        <input type="number" step="0.01" min="0" name="current_stock" required value="0"
                               class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Batas Minimum</label>
                        <input type="number" step="0.01" min="0" name="min_stock" required value="5"
                               class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="createModalOpen = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary text-white text-xs font-bold cursor-pointer">Simpan Bahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function inventoryApp() {
    return {
        createModalOpen: false,
        restockModal: {
            open: false,
            id: '',
            name: '',
            unit: '',
            unit_cost: 0,
            quantity: 1,
            total_cost: 0,
            notes: '',
        },
        opnameModal: {
            open: false,
            id: '',
            name: '',
            unit: '',
            current_stock: 0,
            actual_stock: 0,
            notes: '',
        },
        editModal: {
            open: false,
            id: '',
            name: '',
            unit: '',
            min_stock: 0,
            cost_per_unit: 0,
        },

        openRestockModal(item) {
            this.restockModal = {
                open: true,
                id: item.id,
                name: item.name,
                unit: item.unit,
                unit_cost: Number(item.unit_cost) || 0,
                quantity: 1,
                total_cost: Math.round(Number(item.unit_cost) || 0),
                notes: '',
            };
        },

        recalcRestockCost() {
            const qty = Number(this.restockModal.quantity) || 0;
            this.restockModal.total_cost = Math.round(qty * this.restockModal.unit_cost);
        },

        openOpnameModal(item) {
            this.opnameModal = {
                open: true,
                id: item.id,
                name: item.name,
                unit: item.unit,
                current_stock: Number(item.current_stock) || 0,
                actual_stock: Number(item.current_stock) || 0,
                notes: '',
            };
        },

        openEditModal(item) {
            this.editModal = {
                open: true,
                id: item.id,
                name: item.name,
                unit: item.unit,
                min_stock: Number(item.min_stock) || 0,
                cost_per_unit: Number(item.cost_per_unit) || 0,
            };
        },
    };
}
</script>
@endsection