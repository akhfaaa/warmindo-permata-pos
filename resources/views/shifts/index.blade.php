@extends('layouts.app')

@section('title', 'Tutup Kasir')
@section('page_title', 'Shift Kasir & Rekonsiliasi Laci Uang')

@section('content')
<div
    x-data="{
        expectedCash: {{ $shiftStats['expected_cash'] ?? 0 }},
        actualCash: {{ old('actual_cash', $shiftStats['expected_cash'] ?? 0) }},
        discrepancy() {
            return (Number(this.actualCash) || 0) - Number(this.expectedCash);
        },
        formatRp(val) {
            const abs = Math.abs(Math.round(val));
            return (val < 0 ? '-Rp ' : 'Rp ') + abs.toLocaleString('id-ID');
        }
    }"
    class="max-w-4xl mx-auto space-y-6"
>
    @if (! $activeShift)
        <!-- Form Buka Shift Baru -->
        <div class="bg-surface border border-border rounded-control p-6 max-w-lg">
            <span class="px-2.5 py-1 rounded-badge bg-brand-tint text-brand-primary text-xs font-bold">BELUM ADA SHIFT AKTIF</span>
            <h2 class="text-lg font-bold text-ink-primary mt-2">Buka Shift Kasir</h2>
            <p class="text-xs text-ink-secondary mt-1 mb-4">Masukkan jumlah uang modal kembalian awal di dalam laci kasir sebelum memulai operasional.</p>

            <form method="POST" action="{{ route('shifts.open') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Modal Awal Laci (Rp)</label>
                    <input type="number" step="1000" min="0" name="opening_cash" value="100000" required
                           class="w-full h-12 rounded-control border border-border-strong font-mono text-lg font-bold">
                </div>
                <button type="submit" class="w-full h-12 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-bold text-sm cursor-pointer">
                    Mulai Shift Kasir Sekarang
                </button>
            </form>
        </div>
    @else
        <!-- Panel Shift Aktif & Form Tutup Kasir -->
        <div class="bg-surface border border-border rounded-control p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border pb-4">
                <div>
                    <span class="px-2.5 py-0.5 rounded-badge bg-semantic-successBg text-semantic-successText text-xs font-bold">SHIFT AKTIF</span>
                    <h2 class="text-lg font-bold text-ink-primary mt-1">Rekonsiliasi Tutup Kasir</h2>
                    <p class="text-xs text-ink-secondary">Dibuka oleh <strong>{{ $activeShift->user->name }}</strong> pada {{ $activeShift->opened_at->format('d M Y H:i') }}</p>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs text-ink-secondary block">Total Transaksi Sah</span>
                    <span class="font-mono text-xl font-bold text-ink-primary">{{ $shiftStats['tx_count'] }} Nota</span>
                </div>
            </div>

            <!-- 4 Ringkasan Angka Sistem -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3.5 rounded-control bg-subtle">
                    <span class="text-xs text-ink-secondary block">Modal Awal Laci</span>
                    <span class="font-mono tabular-nums text-base font-bold mt-1 block">Rp {{ number_format($activeShift->opening_cash, 0, ',', '.') }}</span>
                </div>
                <div class="p-3.5 rounded-control bg-subtle">
                    <span class="text-xs text-ink-secondary block">Penjualan Tunai</span>
                    <span class="font-mono tabular-nums text-base font-bold text-semantic-successText mt-1 block">Rp {{ number_format($shiftStats['cash_sales'], 0, ',', '.') }}</span>
                </div>
                <div class="p-3.5 rounded-control bg-subtle">
                    <span class="text-xs text-ink-secondary block">Penjualan QRIS</span>
                    <span class="font-mono tabular-nums text-base font-bold text-semantic-infoText mt-1 block">Rp {{ number_format($shiftStats['qris_sales'], 0, ',', '.') }}</span>
                </div>
                <div class="p-3.5 rounded-control bg-brand-tint border border-brand-primary/30">
                    <span class="text-xs font-semibold text-brand-primary block">Target Tunai di Laci</span>
                    <span class="font-mono tabular-nums text-base font-bold text-ink-primary mt-1 block">Rp {{ number_format($shiftStats['expected_cash'], 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Form Hitung Uang Fisik Aktual -->
            <form method="POST" action="{{ route('shifts.close', $activeShift) }}" class="space-y-4 pt-2">
                @csrf
                <div>
                    <label class="block text-sm font-bold text-ink-primary mb-1.5">Total Uang Tunai Fisik di Laci (Rp)</label>
                    <input type="number" step="500" min="0" name="actual_cash" x-model.number="actualCash" required
                           class="w-full h-14 px-4 rounded-control border border-border-strong font-mono tabular-nums text-2xl font-bold">
                </div>

                <!-- Status Kecocokan Kas Live -->
                <div
                    aria-live="polite"
                    :class="{
                        'bg-semantic-successBg text-semantic-successText': discrepancy() === 0,
                        'bg-semantic-dangerBg text-semantic-dangerText': discrepancy() < 0,
                        'bg-semantic-warningBg text-semantic-warningText': discrepancy() > 0
                    }"
                    class="p-4 rounded-control flex items-center justify-between"
                >
                    <span class="text-xs font-bold uppercase"
                          x-text="discrepancy() === 0 ? 'STATUS: KAS SESUAI' : (discrepancy() < 0 ? 'STATUS: SELISIH KURANG' : 'STATUS: SELISIH LEBIH')"></span>
                    <span class="font-mono tabular-nums text-xl font-bold" x-text="formatRp(discrepancy())"></span>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-1">
                        Catatan Shift <span x-show="discrepancy() !== 0" class="text-semantic-dangerText">(Wajib diisi jika ada selisih kas)</span>
                    </label>
                    <input type="text" name="notes" value="{{ old('notes') }}" :required="discrepancy() !== 0"
                           placeholder="Contoh: Uang laci pas / selisih kembalian"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>

                <button type="submit" class="w-full h-14 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-bold text-base cursor-pointer">
                    Tutup Shift & Kunci Laporan Laci
                </button>
            </form>
        </div>
    @endif

    <!-- Riwayat Shift Sebelumnya -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="p-4 border-b border-border">
            <h2 class="text-base font-bold text-ink-primary">Riwayat Tutup Kasir Terakhir</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-subtle border-b border-border text-xs font-semibold text-ink-secondary uppercase">
                        <th class="py-3 px-4">Waktu Tutup</th>
                        <th class="py-3 px-4">Kasir</th>
                        <th class="py-3 px-4 text-right">Target Sistem</th>
                        <th class="py-3 px-4 text-right">Fisik Laci</th>
                        <th class="py-3 px-4 text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($closedShifts as $sh)
                        <tr>
                            <td class="py-3 px-4 text-xs">{{ $sh->closed_at?->format('d M Y H:i') }}</td>
                            <td class="py-3 px-4 font-semibold">{{ $sh->user->name }}</td>
                            <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($sh->expected_cash, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono">Rp {{ number_format($sh->actual_cash, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold {{ $sh->discrepancy == 0 ? 'text-semantic-successText' : 'text-semantic-dangerText' }}">
                                Rp {{ number_format($sh->discrepancy, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 px-4 text-center text-xs text-ink-muted">Belum ada riwayat shift yang ditutup.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection