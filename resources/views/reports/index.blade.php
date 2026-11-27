@extends('layouts.app')

@section('title', 'Pusat Laporan')
@section('page_title', 'Pusat Laporan Operasional & Keuangan')

@section('content')
@php
    $tabs = [
        'daily_sales'         => '1. Penjualan Harian',
        'menu_performance'    => '2. Performa Menu',
        'cashflow'            => '3. Arus Kas & Laba Rugi',
        'inventory_valuation' => '4. Valuasi Stok Bahan',
        'cash_shifts'         => '5. Audit Shift Kasir',
        'peak_hours'          => '6. Jam Ramai (Peak Hours)',
    ];

    $todayStr     = now()->format('Y-m-d');
    $sevenDaysStr = now()->subDays(6)->format('Y-m-d');
    $monthStart   = now()->startOfMonth()->format('Y-m-d');
    $lastMStart   = now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
    $lastMEnd     = now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
@endphp

<div x-data="{ tab: '{{ $activeTab }}', pdfOrientation: 'portrait' }" class="max-w-7xl mx-auto space-y-6">

    <!-- Panel Filter Rentang Tanggal, Preset Cepat & Export DomPDF -->
    <div class="bg-surface border border-border rounded-control p-5 space-y-4">
        <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-4">
            <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" :value="tab">

                <div>
                    <label class="block text-[11px] font-bold uppercase text-ink-secondary mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" value="{{ $startDate }}"
                           class="h-10 px-3 rounded-control border border-border-strong font-mono text-xs font-bold bg-surface text-ink-primary">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-ink-secondary mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ $endDate }}"
                           class="h-10 px-3 rounded-control border border-border-strong font-mono text-xs font-bold bg-surface text-ink-primary">
                </div>

                <button type="submit"
                        class="h-10 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                    Terapkan Filter
                </button>
            </form>

            <!-- Tombol Pintas Periode & DomPDF Custom -->
            <div class="flex flex-wrap items-center gap-2">
                <a :href="'{{ route('reports.index') }}?tab=' + tab + '&start_date={{ $todayStr }}&end_date={{ $todayStr }}'"
                   class="h-9 px-3 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-semibold text-ink-primary inline-flex items-center">
                    Hari Ini
                </a>
                <a :href="'{{ route('reports.index') }}?tab=' + tab + '&start_date={{ $sevenDaysStr }}&end_date={{ $todayStr }}'"
                   class="h-9 px-3 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-semibold text-ink-primary inline-flex items-center">
                    7 Hari
                </a>
                <a :href="'{{ route('reports.index') }}?tab=' + tab + '&start_date={{ $monthStart }}&end_date={{ $todayStr }}'"
                   class="h-9 px-3 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-semibold text-ink-primary inline-flex items-center">
                    Bulan Ini
                </a>
                <a :href="'{{ route('reports.index') }}?tab=' + tab + '&start_date={{ $lastMStart }}&end_date={{ $lastMEnd }}'"
                   class="h-9 px-3 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-semibold text-ink-primary inline-flex items-center">
                    Bulan Lalu
                </a>

                <span class="text-ink-muted mx-1">|</span>

                <select x-model="pdfOrientation" aria-label="Orientasi Kertas PDF"
                        class="h-9 px-2.5 rounded-control border border-border-strong bg-surface text-xs font-semibold text-ink-primary">
                    <option value="portrait">A4 Portrait</option>
                    <option value="landscape">A4 Landscape</option>
                </select>

                <a :href="'{{ route('reports.pdf') }}?type=' + tab + '&start_date={{ $startDate }}&end_date={{ $endDate }}&orientation=' + pdfOrientation + '&mode=stream'"
                   target="_blank"
                   class="h-9 px-3.5 rounded-control bg-ink-primary hover:opacity-90 text-white text-xs font-bold inline-flex items-center">
                    Preview PDF
                </a>

                <a :href="'{{ route('reports.pdf') }}?type=' + tab + '&start_date={{ $startDate }}&end_date={{ $endDate }}&orientation=' + pdfOrientation + '&mode=download'"
                   class="h-9 px-3.5 rounded-control border border-brand-primary bg-brand-tint hover:bg-brand-primary text-brand-primary hover:text-white text-xs font-bold inline-flex items-center transition-colors">
                    Unduh PDF
                </a>
            </div>
        </div>

        <!-- Navigasi 6 Jenis Laporan -->
        <div class="flex flex-wrap gap-2 pt-3 border-t border-border">
            @foreach ($tabs as $tKey => $tLabel)
                <button
                    type="button"
                    @click="tab = '{{ $tKey }}'"
                    :class="tab === '{{ $tKey }}'
                        ? 'bg-brand-primary text-white border-brand-primary'
                        : 'bg-subtle text-ink-secondary border-border hover:text-ink-primary'"
                    class="h-10 px-3.5 rounded-control border text-xs font-bold transition-colors cursor-pointer"
                >
                    {{ $tLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- REPORT 1: PENJUALAN HARIAN & METODE PEMBAYARAN -->
    <div x-show="tab === 'daily_sales'" class="space-y-4">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Omzet ({{ $startDate }} s/d {{ $endDate }})</span>
                <span class="font-mono text-xl font-bold text-ink-primary mt-1 block">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-ink-muted">{{ $summary['total_paid_tx'] }} nota sukses • {{ $summary['total_portions'] }} porsi</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Pemasukan Tunai (CASH)</span>
                <span class="font-mono text-xl font-bold text-semantic-successText mt-1 block">Rp {{ number_format($summary['total_cash'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-ink-muted">Pembayaran uang tunai kasir</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Pemasukan QRIS / Non-Tunai</span>
                <span class="font-mono text-xl font-bold text-brand-primary mt-1 block">Rp {{ number_format($summary['total_qris'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-ink-muted">Pembayaran digital / QRIS</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Transaksi Dibatalkan (VOID)</span>
                <span class="font-mono text-xl font-bold text-semantic-dangerText mt-1 block">{{ $summary['total_void_tx'] }} Nota</span>
                <span class="text-[11px] text-ink-muted">Tidak dihitung dalam omzet</span>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 1: Rekapitulasi Penjualan Harian & Metode Bayar</h3>
                    <p class="text-xs text-ink-secondary">Periode {{ $startDate }} sampai {{ $endDate }}</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'daily_sales', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4 text-right">Nota Sukses</th>
                            <th class="py-3 px-4 text-right">Nota Void</th>
                            <th class="py-3 px-4 text-right">Porsi Terjual</th>
                            <th class="py-3 px-4 text-right">Tunai (CASH)</th>
                            <th class="py-3 px-4 text-right">QRIS / Non-Tunai</th>
                            <th class="py-3 px-4 text-right">Total Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($dailySalesReport as $row)
                            <tr class="hover:bg-subtle/40">
                                <td class="py-3 px-4 font-mono text-xs font-bold text-ink-primary">{{ $row['tanggal'] }}</td>
                                <td class="py-3 px-4 text-right font-mono">{{ $row['paid_count'] }}</td>
                                <td class="py-3 px-4 text-right font-mono text-semantic-dangerText">{{ $row['void_count'] }}</td>
                                <td class="py-3 px-4 text-right font-mono font-semibold">{{ number_format($row['portions'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">Rp {{ number_format($row['cash_total'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">Rp {{ number_format($row['qris_total'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-semantic-successText">Rp {{ number_format($row['total_omzet'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-ink-muted">Tidak ada data penjualan pada rentang tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORT 2: PERFORMA & PERINGKAT MENU TERLARIS -->
    <div x-show="tab === 'menu_performance'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Penjualan Menu</span>
                <span class="font-mono text-xl font-bold text-ink-primary mt-1 block">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Estimasi HPP Resep</span>
                <span class="font-mono text-xl font-bold text-semantic-warningText mt-1 block">Rp {{ number_format($summary['total_est_hpp'], 0, ',', '.') }}</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Estimasi Laba Kotor Menu</span>
                <span class="font-mono text-xl font-bold text-semantic-successText mt-1 block">Rp {{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 2: Performa & Peringkat Menu Terlaris</h3>
                    <p class="text-xs text-ink-secondary">Diurutkan berdasarkan jumlah porsi terjual terbanyak ({{ $startDate }} s/d {{ $endDate }})</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'menu_performance', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">Nama Makanan / Minuman</th>
                            <th class="py-3 px-4 text-right">Harga Jual</th>
                            <th class="py-3 px-4 text-right">Terjual</th>
                            <th class="py-3 px-4 text-right">Total Omzet</th>
                            <th class="py-3 px-4 text-right">Est. HPP</th>
                            <th class="py-3 px-4 text-right">Est. Laba Kotor</th>
                            <th class="py-3 px-4 text-right">Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($menuReport as $idx => $row)
                            <tr class="hover:bg-subtle/40">
                                <td class="py-3 px-4 font-mono text-xs font-bold text-ink-secondary">{{ $idx + 1 }}</td>
                                <td class="py-3 px-4">
                                    <span class="font-bold text-ink-primary">{{ $row['name'] }}</span>
                                    <span class="ml-1.5 px-2 py-0.5 rounded-badge bg-subtle border border-border text-[11px] font-semibold text-ink-secondary">{{ $row['category'] }}</span>
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-ink-primary">{{ number_format($row['qty_sold'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-ink-primary">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">Rp {{ number_format($row['total_hpp'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-semantic-successText">Rp {{ number_format($row['gross_profit'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono text-xs font-bold text-brand-primary">{{ number_format($row['share_pct'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-xs text-ink-muted">Belum ada menu yang terjual pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORT 3: ARUS KAS & LABA RUGI -->
    <div x-show="tab === 'cashflow'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Pemasukan Penjualan</span>
                <span class="font-mono text-xl font-bold text-ink-primary mt-1 block">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Pengeluaran Belanja Bahan</span>
                <span class="font-mono text-xl font-bold text-semantic-dangerText mt-1 block">Rp {{ number_format($summary['total_expenses'], 0, ',', '.') }}</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Pendapatan Bersih (Penjualan &minus; Belanja)</span>
                <span class="font-mono text-xl font-bold {{ $summary['net_cashflow'] >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }} mt-1 block">
                    Rp {{ number_format($summary['net_cashflow'], 0, ',', '.') }}
                </span>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 3: Arus Kas Harian (Penjualan vs Pengeluaran Belanja)</h3>
                    <p class="text-xs text-ink-secondary">Perbandingan arus uang masuk dan belanja restock harian ({{ $startDate }} s/d {{ $endDate }})</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'cashflow', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4 text-right">Pemasukan Penjualan</th>
                            <th class="py-3 px-4 text-right">Pengeluaran Belanja</th>
                            <th class="py-3 px-4 text-right">Selisih Bersih Harian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($cashflowDaily as $row)
                            <tr class="hover:bg-subtle/40">
                                <td class="py-3 px-4 font-mono text-xs font-bold text-ink-primary">{{ $row['tanggal'] }}</td>
                                <td class="py-3 px-4 text-right font-mono text-ink-primary">Rp {{ number_format($row['sales'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono text-semantic-dangerText">Rp {{ number_format($row['expenses'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold {{ $row['net'] >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }}">
                                    Rp {{ number_format($row['net'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-xs text-ink-muted">Tidak ada pergerakan arus kas pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORT 4: VALUASI & MUTASI STOK BAHAN BAKU -->
    <div x-show="tab === 'inventory_valuation'" x-cloak class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Nilai Aset Stok Tersisa</span>
                <span class="font-mono text-xl font-bold text-brand-primary mt-1 block">Rp {{ number_format($summary['total_inventory_asset'], 0, ',', '.') }}</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Total Jenis Bahan Baku</span>
                <span class="font-mono text-xl font-bold text-ink-primary mt-1 block">{{ $inventoryReport->count() }} Item</span>
            </div>
            <div class="bg-surface border border-border rounded-control p-4">
                <span class="text-[11px] font-bold uppercase text-ink-secondary block">Bahan Perlu Restock</span>
                <span class="font-mono text-xl font-bold {{ $summary['low_stock_count'] > 0 ? 'text-semantic-warningText' : 'text-semantic-successText' }} mt-1 block">
                    {{ $summary['low_stock_count'] }} Item
                </span>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 4: Valuasi Aset & Mutasi Stok Bahan Baku</h3>
                    <p class="text-xs text-ink-secondary">Mutasi masuk/keluar periode {{ $startDate }} s/d {{ $endDate }} beserta nilai rupiah stok akhir</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'inventory_valuation', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">Bahan Baku</th>
                            <th class="py-3 px-4 text-right">Masuk (Restock)</th>
                            <th class="py-3 px-4 text-right">Keluar (Terpakai)</th>
                            <th class="py-3 px-4 text-right">Stok Akhir</th>
                            <th class="py-3 px-4 text-right">Harga Beli/Satuan</th>
                            <th class="py-3 px-4 text-right">Nilai Aset Stok</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($inventoryReport as $row)
                            <tr class="hover:bg-subtle/40">
                                <td class="py-3 px-4 font-bold text-ink-primary">{{ $row['name'] }}</td>
                                <td class="py-3 px-4 text-right font-mono text-semantic-successText">+{{ number_format($row['qty_in'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                                <td class="py-3 px-4 text-right font-mono text-semantic-dangerText">-{{ number_format($row['qty_out'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-ink-primary">{{ number_format($row['current_stock'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">Rp {{ number_format($row['unit_cost'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-brand-primary">Rp {{ number_format($row['asset_value'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4">
                                    @if ($row['is_low'])
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-semantic-warningBg text-semantic-warningText">Menipis</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-semantic-successBg text-semantic-successText">Aman</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-ink-muted">Belum ada data bahan baku.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORT 5: AUDIT SHIFT & SELISIH LACI KASIR -->
    <div x-show="tab === 'cash_shifts'" x-cloak class="space-y-4">
        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 5: Audit Shift & Selisih Laci Uang Kasir</h3>
                    <p class="text-xs text-ink-secondary">Rekam jejak pembukaan modal awal, kas sistem, dan kas fisik aktual setiap shift</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'cash_shifts', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">Waktu Buka</th>
                            <th class="py-3 px-4">Waktu Tutup</th>
                            <th class="py-3 px-4">Petugas Kasir</th>
                            <th class="py-3 px-4 text-right">Modal Awal</th>
                            <th class="py-3 px-4 text-right">Kas Sistem</th>
                            <th class="py-3 px-4 text-right">Kas Aktual</th>
                            <th class="py-3 px-4 text-right">Selisih</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($shiftReport as $row)
                            <tr class="hover:bg-subtle/40">
                                <td class="py-3 px-4 font-mono text-xs text-ink-secondary">{{ $row['opened_at'] }}</td>
                                <td class="py-3 px-4 font-mono text-xs text-ink-secondary">{{ $row['closed_at'] }}</td>
                                <td class="py-3 px-4 font-bold text-ink-primary">{{ $row['cashier_name'] }}</td>
                                <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($row['opening_cash'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($row['expected_cash'], 0, ',', '.') }}</td>
                                <td class="py-3 px-4 text-right font-mono font-bold">
                                    {{ $row['actual_cash'] !== null ? 'Rp ' . number_format($row['actual_cash'], 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold {{ $row['difference'] < 0 ? 'text-semantic-dangerText' : ($row['difference'] > 0 ? 'text-semantic-infoText' : 'text-semantic-successText') }}">
                                    {{ $row['actual_cash'] !== null ? 'Rp ' . number_format($row['difference'], 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $row['status'] === 'OPEN' ? 'bg-semantic-warningBg text-semantic-warningText' : 'bg-semantic-successBg text-semantic-successText' }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-xs text-ink-muted">Tidak ada catatan shift kasir pada rentang tanggal ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- REPORT 6: ANALISIS JAM RAMAI (PEAK HOURS) -->
    <div x-show="tab === 'peak_hours'" x-cloak class="space-y-4">
        @if ($busiestHour && $busiestHour['tx_count'] > 0)
            <div class="bg-brand-tint/50 border border-brand-primary/30 rounded-control p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <span class="text-xs font-bold uppercase text-brand-primary">Jam Tersibuk Warung</span>
                    <p class="text-sm font-bold text-ink-primary mt-0.5">
                        Pukul {{ $busiestHour['hour_label'] }} ({{ $busiestHour['tx_count'] }} transaksi • {{ $busiestHour['portions'] }} porsi)
                    </p>
                </div>
                <span class="font-mono text-lg font-bold text-brand-primary">
                    Rp {{ number_format($busiestHour['revenue'], 0, ',', '.') }}
                </span>
            </div>
        @endif

        <div class="bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-ink-primary">Laporan 6: Analisis Jam Ramai Transaksi (Peak Hours)</h3>
                    <p class="text-xs text-ink-secondary">Distribusi kepadatan transaksi dan omzet berdasarkan jam operasional</p>
                </div>
                <a href="{{ route('reports.export', ['type' => 'peak_hours', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="h-9 px-3.5 rounded-control border border-border-strong bg-subtle hover:bg-surface text-xs font-bold text-ink-primary inline-flex items-center">
                    Export CSV
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4 w-40">Jam Operasional</th>
                            <th class="py-3 px-4 text-right w-28">Transaksi</th>
                            <th class="py-3 px-4 text-right w-28">Porsi</th>
                            <th class="py-3 px-4 text-right w-40">Total Omzet</th>
                            <th class="py-3 px-4">Grafik Kepadatan Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($peakHoursReport as $row)
                            @php
                                $barPct = $row['revenue'] > 0 ? max(4, round(($row['revenue'] / $maxHourlyRevenue) * 100)) : 0;
                            @endphp
                            <tr class="hover:bg-subtle/40">
                                <td class="py-2.5 px-4 font-mono text-xs font-bold text-ink-primary">{{ $row['hour_label'] }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">{{ $row['tx_count'] }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">{{ $row['portions'] }}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-ink-primary">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="py-2.5 px-4">
                                    <div class="w-full h-3 rounded-full bg-subtle overflow-hidden">
                                        <div class="h-full rounded-full bg-brand-primary" style="width: {{ $barPct }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection