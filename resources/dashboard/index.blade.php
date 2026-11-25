@extends('layouts.app')

@section('title', 'Dashboard Owner')
@section('page_title', 'Laporan Omzet & Laba Kotor — Warmindo Permata')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <!-- Filter Periode & Tombol Ekspor CSV -->
    <div class="bg-surface border border-border rounded-control p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <!-- Preset Cepat -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('dashboard.index', ['preset' => 'today']) }}"
               class="px-3.5 py-2 rounded-control text-xs font-bold transition-colors {{ $preset === 'today' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                Hari Ini
            </a>
            <a href="{{ route('dashboard.index', ['preset' => '7days']) }}"
               class="px-3.5 py-2 rounded-control text-xs font-bold transition-colors {{ $preset === '7days' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                7 Hari Terakhir
            </a>
            <a href="{{ route('dashboard.index', ['preset' => '30days']) }}"
               class="px-3.5 py-2 rounded-control text-xs font-bold transition-colors {{ $preset === '30days' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary' }}">
                30 Hari Terakhir
            </a>
        </div>

        <!-- Rentang Tanggal Kustom & Ekspor -->
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('dashboard.index') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="preset" value="custom">
                <input type="date" name="start_date" value="{{ $startDateInput }}" aria-label="Tanggal Mulai"
                       class="h-10 px-3 rounded-control border border-border-strong text-xs font-mono text-ink-primary bg-surface">
                <span class="text-xs text-ink-muted font-semibold">s/d</span>
                <input type="date" name="end_date" value="{{ $endDateInput }}" aria-label="Tanggal Selesai"
                       class="h-10 px-3 rounded-control border border-border-strong text-xs font-mono text-ink-primary bg-surface">
                <button type="submit"
                        class="h-10 px-4 rounded-control bg-ink-primary hover:bg-ink-primary/90 text-white text-xs font-bold cursor-pointer">
                    Terapkan
                </button>
            </form>

            <a href="{{ route('dashboard.export', ['preset' => $preset, 'start_date' => $startDateInput, 'end_date' => $endDateInput]) }}"
               class="h-10 px-4 rounded-control border border-brand-primary bg-brand-tint hover:bg-brand-primary hover:text-white text-brand-primary text-xs font-bold inline-flex items-center transition-colors">
                Ekspor CSV Excel
            </a>
        </div>
    </div>

    <!-- 4 KARTU KPI UTAMA -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- KPI 1: Omzet Kotor -->
        <div class="bg-surface border border-border rounded-control p-5 space-y-1.5">
            <span class="text-xs font-bold uppercase tracking-wide text-ink-secondary">Total Omzet (PAID)</span>
            <p class="font-mono tabular-nums text-2xl font-bold text-ink-primary">
                Rp {{ number_format($kpis['omzet'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink-secondary">
                HPP Bahan: <span class="font-mono font-semibold text-ink-primary">Rp {{ number_format($kpis['hpp'], 0, ',', '.') }}</span>
            </p>
        </div>

        <!-- KPI 2: Estimasi Laba Kotor -->
        <div class="bg-surface border border-border rounded-control p-5 space-y-1.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wide text-ink-secondary">Estimasi Laba Kotor</span>
                <span class="px-2 py-0.5 rounded-badge font-mono text-[11px] font-bold {{ $kpis['profit'] >= 0 ? 'bg-semantic-successBg text-semantic-successText' : 'bg-semantic-dangerBg text-semantic-dangerText' }}">
                    Margin {{ $kpis['margin_pct'] }}%
                </span>
            </div>
            <p class="font-mono tabular-nums text-2xl font-bold {{ $kpis['profit'] >= 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }}">
                Rp {{ number_format($kpis['profit'], 0, ',', '.') }}
            </p>
            <p class="text-xs text-ink-muted">Dihitung otomatis dari resep BOM saat transaksi</p>
        </div>

        <!-- KPI 3: Jumlah Transaksi & Rata-rata Nota -->
        <div class="bg-surface border border-border rounded-control p-5 space-y-1.5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wide text-ink-secondary">Total Nota Sukses</span>
                @if ($kpis['void_count'] > 0)
                    <span class="px-2 py-0.5 rounded-badge bg-semantic-dangerBg text-semantic-dangerText font-mono text-[11px] font-bold">
                        {{ $kpis['void_count'] }} Void
                    </span>
                @endif
            </div>
            <p class="font-mono tabular-nums text-2xl font-bold text-ink-primary">
                {{ number_format($kpis['tx_count'], 0, ',', '.') }} <span class="text-sm font-sans font-semibold text-ink-secondary">Nota</span>
            </p>
            <p class="text-xs text-ink-secondary">
                Rata-rata Nota: <span class="font-mono font-semibold text-ink-primary">Rp {{ number_format($kpis['avg_order'], 0, ',', '.') }}</span>
            </p>
        </div>

        <!-- KPI 4: Status Stok Bahan Baku -->
        <div class="bg-surface border border-border rounded-control p-5 space-y-1.5">
            <span class="text-xs font-bold uppercase tracking-wide text-ink-secondary">Stok Bahan Baku Kritis</span>
            <p class="font-mono tabular-nums text-2xl font-bold {{ $lowStockIngredients->count() > 0 ? 'text-semantic-warningText' : 'text-semantic-successText' }}">
                {{ $lowStockIngredients->count() }} <span class="text-sm font-sans font-semibold text-ink-secondary">Item</span>
            </p>
            <a href="{{ route('inventory.index') }}" class="inline-block text-xs font-bold text-brand-primary hover:underline">
                Kelola & Restock Gudang &rarr;
            </a>
        </div>
    </div>

    <!-- BARIS 2: Komposisi Pembayaran/Pesanan & Grafik Tren Harian -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Kiri (5 Kolom): Komposisi Tunai vs QRIS & Dine-In vs Takeaway -->
        <div class="lg:col-span-5 bg-surface border border-border rounded-control p-5 space-y-5">
            <div>
                <h2 class="text-base font-bold text-ink-primary">Komposisi Metode Pembayaran</h2>
                <p class="text-xs text-ink-secondary">Perbandingan pemasukan laci tunai dan transfer QRIS</p>
            </div>

            <!-- Progress Bar Proporsi -->
            <div class="space-y-2">
                <div class="h-3 w-full rounded-full bg-subtle overflow-hidden flex">
                    <div style="width: {{ $paymentBreakdown['tunai_pct'] }}%" class="bg-brand-primary transition-all"></div>
                    <div style="width: {{ $paymentBreakdown['qris_pct'] }}%" class="bg-semantic-infoText transition-all"></div>
                </div>
                <div class="flex justify-between text-xs font-semibold">
                    <span class="text-brand-primary">Tunai ({{ $paymentBreakdown['tunai_pct'] }}%)</span>
                    <span class="text-semantic-infoText">QRIS ({{ $paymentBreakdown['qris_pct'] }}%)</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1">
                <div class="p-3.5 rounded-control bg-subtle border border-border">
                    <span class="text-[11px] font-bold uppercase text-ink-secondary block">TUNAI ({{ $paymentBreakdown['tunai_count'] }}x)</span>
                    <span class="font-mono tabular-nums text-base font-bold text-ink-primary mt-1 block">
                        Rp {{ number_format($paymentBreakdown['tunai_amount'], 0, ',', '.') }}
                    </span>
                </div>
                <div class="p-3.5 rounded-control bg-subtle border border-border">
                    <span class="text-[11px] font-bold uppercase text-ink-secondary block">QRIS ({{ $paymentBreakdown['qris_count'] }}x)</span>
                    <span class="font-mono tabular-nums text-base font-bold text-ink-primary mt-1 block">
                        Rp {{ number_format($paymentBreakdown['qris_amount'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            <div class="border-t border-border pt-4 space-y-2.5">
                <h3 class="text-xs font-bold uppercase text-ink-secondary">Tipe Layanan Pesanan</h3>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-secondary">Makan di Tempat (Dine-In)</span>
                    <span class="font-mono font-bold text-ink-primary">
                        {{ $orderTypeBreakdown['dine_in_count'] }} nota • Rp {{ number_format($orderTypeBreakdown['dine_in_amount'], 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-secondary">Bungkus (Takeaway)</span>
                    <span class="font-mono font-bold text-ink-primary">
                        {{ $orderTypeBreakdown['takeaway_count'] }} nota • Rp {{ number_format($orderTypeBreakdown['takeaway_amount'], 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Kanan (7 Kolom): Grafik Batang Tren Omzet & Laba Harian -->
        <div class="lg:col-span-7 bg-surface border border-border rounded-control p-5 flex flex-col justify-between space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-ink-primary">Tren Omzet & Laba Kotor Harian</h2>
                    <p class="text-xs text-ink-secondary">Visualisasi performa harian berdasarkan periode filter</p>
                </div>
                <div class="flex items-center gap-3 text-xs font-semibold">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-brand-primary"></span> Omzet</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-xs bg-semantic-successText"></span> Laba Kotor</span>
                </div>
            </div>

            @if (count($dailyTrend) === 0)
                <div class="flex-1 flex items-center justify-center py-12 text-center text-xs text-ink-muted">
                    Belum ada transaksi berstatus PAID pada rentang tanggal yang dipilih.
                </div>
            @else
                <div class="overflow-x-auto pb-2">
                    <div class="min-w-[420px] h-52 flex items-end gap-3 pt-6 px-2 border-b border-border">
                        @foreach ($dailyTrend as $day)
                            @php
                                $omzetHeight = $maxDailyOmzet > 0 ? max(8, round(($day['omzet'] / $maxDailyOmzet) * 100)) : 8;
                                $profitHeight = $maxDailyOmzet > 0 ? max(4, round((max(0, $day['profit']) / $maxDailyOmzet) * 100)) : 4;
                            @endphp
                            <div class="flex-1 flex flex-col items-center gap-1.5 group relative">
                                <!-- Tooltip Nilai saat Hover -->
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none absolute -top-2 z-10 bg-ink-primary text-white text-[10px] font-mono rounded-badge px-2 py-1 whitespace-nowrap shadow-floating">
                                    Omzet: Rp {{ number_format($day['omzet'], 0, ',', '.') }} | Laba: Rp {{ number_format($day['profit'], 0, ',', '.') }}
                                </div>

                                <div class="w-full max-w-[44px] h-36 flex items-end justify-center gap-1">
                                    <div style="height: {{ $omzetHeight }}%" class="w-1/2 bg-brand-primary rounded-t-xs transition-all"></div>
                                    <div style="height: {{ $profitHeight }}%" class="w-1/2 bg-semantic-successText rounded-t-xs transition-all"></div>
                                </div>
                                <span class="text-[11px] font-mono font-semibold text-ink-secondary">{{ $day['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- BARIS 3: Top 5 Menu Terlaris & Peringatan Stok / Shift -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Top 5 Menu Terlaris -->
        <div class="lg:col-span-7 bg-surface border border-border rounded-control overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-border">
                <h2 class="text-base font-bold text-ink-primary">5 Menu Paling Laris</h2>
                <p class="text-xs text-ink-secondary">Diurutkan berdasarkan jumlah porsi terjual pada periode aktif</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                            <th class="py-3 px-4">Nama Menu</th>
                            <th class="py-3 px-4 text-right">Terjual</th>
                            <th class="py-3 px-4 text-right">Omzet Menu</th>
                            <th class="py-3 px-4 text-right">Est. Laba</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($topProducts as $idx => $item)
                            <tr>
                                <td class="py-3 px-4 font-semibold text-ink-primary">
                                    <span class="font-mono text-xs text-ink-muted mr-1.5">#{{ $idx + 1 }}</span>
                                    {{ $item['name'] }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-ink-primary">
                                    {{ number_format($item['qty'], 0, ',', '.') }}x
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-ink-secondary">
                                    Rp {{ number_format($item['revenue'], 0, ',', '.') }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-semantic-successText">
                                    Rp {{ number_format($item['profit'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-xs text-ink-muted">
                                    Belum ada data penjualan menu pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kolom Kanan: Peringatan Stok Kritis & Riwayat Tutup Shift -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Kartu Stok Kritis -->
            <div class="bg-surface border border-border rounded-control overflow-hidden">
                <div class="p-4 border-b border-border flex items-center justify-between">
                    <h2 class="text-sm font-bold text-ink-primary">Bahan Baku Perlu Restock</h2>
                    <a href="{{ route('inventory.index') }}" class="text-xs font-bold text-brand-primary hover:underline">Buka Stok</a>
                </div>
                <div class="divide-y divide-border text-xs">
                    @forelse ($lowStockIngredients as $ing)
                        <div class="p-3.5 flex items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-ink-primary">{{ $ing->name }}</p>
                                <p class="text-ink-secondary">Batas min: {{ number_format($ing->min_stock, 0, ',', '.') }} {{ $ing->unit }}</p>
                            </div>
                            <span class="px-2.5 py-1 rounded-badge font-mono font-bold bg-semantic-warningBg text-semantic-warningText">
                                Sisa {{ number_format($ing->current_stock, 0, ',', '.') }} {{ $ing->unit }}
                            </span>
                        </div>
                    @empty
                        <div class="p-6 text-center text-ink-muted">
                            Seluruh stok bahan baku berada di atas batas minimum.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Kartu Log Tutup Shift Terbaru -->
            <div class="bg-surface border border-border rounded-control overflow-hidden">
                <div class="p-4 border-b border-border flex items-center justify-between">
                    <h2 class="text-sm font-bold text-ink-primary">Log Tutup Kasir Terbaru</h2>
                    <a href="{{ route('shifts.index') }}" class="text-xs font-bold text-brand-primary hover:underline">Lihat Shift</a>
                </div>
                <div class="divide-y divide-border text-xs">
                    @forelse ($recentShifts as $shift)
                        <div class="p-3.5 flex items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-ink-primary">{{ $shift->user?->name ?? 'Kasir' }}</p>
                                <p class="text-ink-secondary font-mono">{{ $shift->closed_at?->format('d M Y H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <span class="font-mono font-bold text-ink-primary block">
                                    Rp {{ number_format($shift->actual_cash, 0, ',', '.') }}
                                </span>
                                @if ((float) $shift->discrepancy === 0.0)
                                    <span class="text-[11px] font-bold text-semantic-successText">Klop (Rp 0)</span>
                                @else
                                    <span class="text-[11px] font-bold text-semantic-dangerText">
                                        Selisih: {{ $shift->discrepancy > 0 ? '+' : '' }}Rp {{ number_format($shift->discrepancy, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-ink-muted">
                            Belum ada riwayat shift yang ditutup.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection