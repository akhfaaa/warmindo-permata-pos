@extends('layouts.app')

@section('title', 'Rekap Penjualan & Belanja')
@section('page_title', 'Rekap Harian Penjualan, Belanja & Pendapatan Bulanan')

@section('content')
<div x-data="recapApp()" class="max-w-7xl mx-auto space-y-6">

    <!-- Filter Bulan -->
    <div class="bg-surface border border-border rounded-control p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-ink-primary">Periode Laporan: {{ $selectedMonthLabel }}</h2>
            <p class="text-xs text-ink-secondary">Pilih bulan untuk melihat rincian penjualan harian, belanja bahan, dan pendapatan bersih bulanan.</p>
        </div>

        <form method="GET" action="{{ route('recap.index') }}" class="flex items-center gap-2">
            <input type="month" name="month" value="{{ $selectedMonth }}" aria-label="Pilih Bulan"
                   class="h-10 px-3 rounded-control border border-border-strong font-mono text-xs font-bold bg-surface text-ink-primary">
            <button type="submit"
                    class="h-10 px-4 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                Tampilkan Bulan
            </button>
        </form>
    </div>

    <!-- RINGKASAN BULANAN: Total Penjualan - Total Belanja = Total Pendapatan -->
    <div class="bg-surface border border-border rounded-control p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-border pb-3">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-brand-primary">Rekapitulasi Keuangan Bulan {{ $selectedMonthLabel }}</span>
                <p class="text-xs text-ink-secondary mt-0.5">Rumus: Total Penjualan per Bulan &minus; Total Belanja/Pengeluaran per Bulan = Total Pendapatan</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-11 gap-3 items-center">
            <!-- Kartu 1: Total Penjualan per Bulan -->
            <div class="lg:col-span-3 p-4 rounded-control bg-subtle border border-border">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">1. Total Penjualan per Bulan</span>
                <span class="font-mono tabular-nums text-xl font-bold text-ink-primary mt-1 block">
                    Rp {{ number_format($monthlyTotalSales, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-ink-muted mt-0.5 block">{{ number_format($monthlyTotalPortions, 0, ',', '.') }} porsi terjual</span>
            </div>

            <!-- Simbol Minus -->
            <div class="lg:col-span-1 text-center font-mono text-2xl font-bold text-ink-secondary">
                &minus;
            </div>

            <!-- Kartu 2: Total Belanja / Pengeluaran per Bulan -->
            <div class="lg:col-span-3 p-4 rounded-control bg-subtle border border-border">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">2. Total Belanja / Pengeluaran</span>
                <span class="font-mono tabular-nums text-xl font-bold text-semantic-dangerText mt-1 block">
                    Rp {{ number_format($monthlyTotalExpenses, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-ink-muted mt-0.5 block">{{ $purchaseRecap->count() }} catatan belanja</span>
            </div>

            <!-- Simbol Sama Dengan -->
            <div class="lg:col-span-1 text-center font-mono text-2xl font-bold text-ink-secondary">
                =
            </div>

            <!-- Kartu 3: Total Pendapatan -->
            <div class="lg:col-span-3 p-4 rounded-control border-2 {{ $monthlyNetIncome >= 0 ? 'border-semantic-successText/40 bg-semantic-successBg/60' : 'border-semantic-dangerText/40 bg-semantic-dangerBg/60' }}">
                <span class="text-[11px] font-bold uppercase {{ $monthlyNetIncome >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }} block">
                    3. Total Pendapatan
                </span>
                <span class="font-mono tabular-nums text-2xl font-bold {{ $monthlyNetIncome >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }} mt-1 block">
                    Rp {{ number_format($monthlyNetIncome, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-ink-secondary mt-0.5 block">Selisih penjualan dikurangi belanja</span>
            </div>
        </div>
    </div>

    <!-- TABEL 1: REKAP HARIAN PENJUALAN -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-ink-primary">Rekap Harian Penjualan</h3>
                <p class="text-xs text-ink-secondary">Rincian penjualan harian per menu makanan dan minuman</p>
            </div>
            <span class="px-3 py-1 rounded-badge bg-brand-tint text-brand-primary font-mono text-xs font-bold">
                Total Penjualan: Rp {{ number_format($monthlyTotalSales, 0, ',', '.') }}
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3.5 px-4">Tanggal</th>
                        <th class="py-3.5 px-4">Nama Makanan / Minuman (Kategori)</th>
                        <th class="py-3.5 px-4 text-right">Harga</th>
                        <th class="py-3.5 px-4 text-right">Terjual</th>
                        <th class="py-3.5 px-4 text-right">Pendapatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($dailySalesRecap as $row)
                        <tr class="hover:bg-subtle/40">
                            <td class="py-3 px-4 font-mono text-xs text-ink-secondary whitespace-nowrap">
                                {{ $row['tanggal'] }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-ink-primary">{{ $row['product_name'] }}</span>
                                <span class="ml-1.5 px-2 py-0.5 rounded-badge bg-subtle border border-border text-[11px] font-semibold text-ink-secondary">
                                    {{ $row['category_name'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-ink-secondary whitespace-nowrap">
                                Rp {{ number_format($row['harga'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-ink-primary whitespace-nowrap">
                                {{ number_format($row['terjual'], 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-semantic-successText whitespace-nowrap">
                                Rp {{ number_format($row['pendapatan'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-xs text-ink-muted">
                                Belum ada data penjualan pada bulan {{ $selectedMonthLabel }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($dailySalesRecap->count() > 0)
                    <tfoot>
                        <tr class="border-t-2 border-border bg-subtle font-bold text-sm">
                            <td colspan="3" class="py-3 px-4 text-right text-ink-secondary uppercase text-xs">Total Penjualan Bulan {{ $selectedMonthLabel }}</td>
                            <td class="py-3 px-4 text-right font-mono text-ink-primary">{{ number_format($monthlyTotalPortions, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono text-ink-primary">Rp {{ number_format($monthlyTotalSales, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- TABEL 2: REKAP BELANJA -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-base font-bold text-ink-primary">Rekap Belanja / Pengeluaran Bahan</h3>
                <p class="text-xs text-ink-secondary">Otomatis tercatat setiap kali melakukan <strong>+ Restock</strong> pada halaman Stok & Restock</p>
            </div>
            <div class="flex items-center gap-2">
                @if (auth()->user()->hasMenu('inventory'))
                    <a href="{{ route('inventory.index') }}"
                       class="h-9 px-3.5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold inline-flex items-center">
                        + Catat Belanja / Restock
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3.5 px-4">Tanggal</th>
                        <th class="py-3.5 px-4">Belanja</th>
                        <th class="py-3.5 px-4 text-right">Total</th>
                        @if (auth()->user()->canEditDelete())
                            <th class="py-3.5 px-4 text-right">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($purchaseRecap as $buy)
                        <tr class="hover:bg-subtle/40">
                            <td class="py-3 px-4 font-mono text-xs text-ink-secondary whitespace-nowrap">
                                {{ $buy['tanggal'] }} <span class="text-ink-muted">({{ $buy['waktu'] }})</span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-ink-primary">
                                {{ $buy['belanja'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-semantic-dangerText whitespace-nowrap">
                                Rp {{ number_format($buy['total'], 0, ',', '.') }}
                            </td>
                            @if (auth()->user()->canEditDelete())
                                <td class="py-3 px-4 text-right whitespace-nowrap space-x-1.5">
                                    <button
                                        type="button"
                                        @click='openEditPurchaseModal({
                                            id: @json($buy["id"]),
                                            ingredient_name: @json($buy["ingredient_name"]),
                                            unit: @json($buy["unit"]),
                                            quantity: {{ (float) $buy["quantity"] }},
                                            total_cost: {{ (float) $buy["total"] }},
                                            notes: @json($buy["notes"])
                                        })'
                                        class="h-8 px-2.5 rounded-badge border border-semantic-infoText/30 bg-semantic-infoBg text-semantic-infoText text-xs font-bold cursor-pointer"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('recap.purchases.destroy', $buy['id']) }}" class="inline"
                                          onsubmit="return confirm('Hapus catatan belanja ini? Saldo stok bahan baku juga akan dikurangi kembali.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="h-8 px-2.5 rounded-badge border border-semantic-dangerText/30 bg-semantic-dangerBg text-semantic-dangerText text-xs font-bold cursor-pointer">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->canEditDelete() ? 4 : 3 }}" class="py-8 text-center text-xs text-ink-muted">
                                Belum ada catatan belanja pada bulan {{ $selectedMonthLabel }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($purchaseRecap->count() > 0)
                    <tfoot>
                        <tr class="border-t-2 border-border bg-subtle font-bold text-sm">
                            <td colspan="2" class="py-3 px-4 text-right text-ink-secondary uppercase text-xs">Total Belanja / Pengeluaran Bulan {{ $selectedMonthLabel }}</td>
                            <td class="py-3 px-4 text-right font-mono text-semantic-dangerText">Rp {{ number_format($monthlyTotalExpenses, 0, ',', '.') }}</td>
                            @if (auth()->user()->canEditDelete())
                                <td></td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- MODAL EDIT CATATAN BELANJA -->
    <div x-show="editModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="editModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <span class="text-xs font-bold uppercase text-brand-primary">Edit Catatan Belanja</span>
                    <h3 class="text-lg font-bold text-ink-primary" x-text="editModal.ingredient_name"></h3>
                </div>
                <button type="button" @click="editModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">Tutup</button>
            </div>

            <form method="POST" :action="'/recap/purchases/' + editModal.id" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">
                        Jumlah Belanja (<span x-text="editModal.unit"></span>)
                    </label>
                    <input type="number" step="0.01" min="0.01" name="quantity" x-model.number="editModal.quantity" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Total Harga Belanja (Rp)</label>
                    <input type="number" step="100" min="0" name="total_cost" x-model.number="editModal.total_cost" required
                           class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Keterangan</label>
                    <input type="text" name="notes" x-model="editModal.notes"
                           class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editModal.open = false" class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-brand-primary text-white text-xs font-bold cursor-pointer">Simpan Koreksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function recapApp() {
    return {
        editModal: {
            open: false,
            id: '',
            ingredient_name: '',
            unit: '',
            quantity: 0,
            total_cost: 0,
            notes: '',
        },

        openEditPurchaseModal(item) {
            this.editModal = {
                open: true,
                id: item.id,
                ingredient_name: item.ingredient_name,
                unit: item.unit,
                quantity: Number(item.quantity) || 0,
                total_cost: Number(item.total_cost) || 0,
                notes: item.notes || '',
            };
        },
    };
}
</script>
@endsection