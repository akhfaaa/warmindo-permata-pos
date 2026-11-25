@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page_title', 'Riwayat Transaksi & Pembatalan (Void)')

@section('content')
<div
    x-data="{
        voidModal: false,
        voidTarget: { id: '', invoice: '' },
        openVoid(id, invoice) {
            this.voidTarget = { id, invoice };
            this.voidModal = true;
        }
    }"
    class="max-w-6xl mx-auto space-y-6"
>
    <!-- Filter Tanggal & Ringkasan Harian -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface border border-border rounded-control p-4">
        <form method="GET" action="{{ route('transactions.index') }}" class="flex items-center gap-2">
            <label for="date" class="text-xs font-bold uppercase text-ink-secondary">Tanggal:</label>
            <input type="date" id="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                   class="h-10 px-3 rounded-control border border-border-strong font-mono text-sm">
        </form>

        <div class="flex flex-wrap items-center gap-4 text-xs">
            <div>
                <span class="text-ink-secondary block">Transaksi Sah</span>
                <strong class="font-mono text-base text-ink-primary">{{ $summary['count'] }} Nota</strong>
            </div>
            <div>
                <span class="text-ink-secondary block">Tunai</span>
                <strong class="font-mono text-base text-semantic-successText">Rp {{ number_format($summary['total_tunai'], 0, ',', '.') }}</strong>
            </div>
            <div>
                <span class="text-ink-secondary block">QRIS</span>
                <strong class="font-mono text-base text-semantic-infoText">Rp {{ number_format($summary['total_qris'], 0, ',', '.') }}</strong>
            </div>
            <div>
                <span class="text-ink-secondary block">Total Omzet</span>
                <strong class="font-mono text-base text-ink-primary">Rp {{ number_format($summary['total_paid'], 0, ',', '.') }}</strong>
            </div>
        </div>
    </div>

    <!-- Daftar Transaksi -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        @if ($transactions->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-semibold text-ink-primary">Belum ada transaksi pada tanggal {{ $date }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-subtle border-b border-border text-xs font-semibold text-ink-secondary uppercase">
                            <th class="py-3 px-4">No. Nota & Jam</th>
                            <th class="py-3 px-4">Rincian Pesanan</th>
                            <th class="py-3 px-4">Tipe / Bayar</th>
                            <th class="py-3 px-4 text-right">Total</th>
                            <th class="py-3 px-4">Status</th>
                            @if (auth()->user()->isOwner())
                                <th class="py-3 px-4 text-right">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-sm">
                        @foreach ($transactions as $tx)
                            <tr class="{{ $tx->status === 'VOID' ? 'bg-semantic-dangerBg/25' : 'hover:bg-canvas/60' }}">
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <p class="font-mono font-bold text-ink-primary">{{ $tx->invoice_number }}</p>
                                    <p class="text-xs text-ink-secondary">{{ $tx->created_at->format('H:i') }} • {{ $tx->user->name }}</p>
                                </td>
                                <td class="py-3.5 px-4 text-xs space-y-1">
                                    @foreach ($tx->items as $it)
                                        <div>
                                            <span class="font-semibold text-ink-primary">{{ $it->quantity }}x {{ $it->product->name }}</span>
                                            @if (!empty($it->modifiers_json))
                                                <span class="text-brand-primary">(+ {{ collect($it->modifiers_json)->pluck('name')->implode(', ') }})</span>
                                            @endif
                                            @if ($it->notes)
                                                <span class="text-ink-muted italic">— {{ $it->notes }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($tx->status === 'VOID')
                                        <p class="text-semantic-dangerText font-semibold mt-1">Alasan Void: {{ $tx->void_reason }}</p>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap space-x-1">
                                    <span class="px-2 py-0.5 rounded-badge text-[11px] font-semibold bg-subtle text-ink-secondary">{{ $tx->order_type }}</span>
                                    <span class="px-2 py-0.5 rounded-badge text-[11px] font-semibold {{ $tx->payment_method === 'QRIS' ? 'bg-semantic-infoBg text-semantic-infoText' : 'bg-semantic-successBg text-semantic-successText' }}">
                                        {{ $tx->payment_method }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono tabular-nums font-bold {{ $tx->status === 'VOID' ? 'line-through text-ink-muted' : 'text-ink-primary' }}">
                                    Rp {{ number_format($tx->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if ($tx->status === 'PAID')
                                        <span class="px-2 py-0.5 rounded-badge text-xs font-semibold bg-semantic-successBg text-semantic-successText">PAID</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-badge text-xs font-semibold bg-semantic-dangerBg text-semantic-dangerText">VOID</span>
                                    @endif
                                </td>
                                @if (auth()->user()->isOwner())
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        @if ($tx->status === 'PAID')
                                            <button
                                                type="button"
                                                @click='openVoid({{ Js::from($tx->id) }}, {{ Js::from($tx->invoice_number) }})'
                                                class="px-3 py-1.5 rounded-badge border border-semantic-dangerText text-semantic-dangerText hover:bg-semantic-dangerBg text-xs font-semibold cursor-pointer"
                                            >
                                                Void Nota
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- MODAL VOID TRANSAKSI (OWNER ONLY) -->
    @if (auth()->user()->isOwner())
    <div x-show="voidModal" x-cloak class="fixed inset-0 z-50 bg-ink-primary/55 flex items-center justify-center p-4">
        <div @click.away="voidModal = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div>
                <span class="text-xs font-bold uppercase text-semantic-dangerText">Konfirmasi Pembatalan</span>
                <h2 class="text-lg font-bold text-ink-primary">Void Nota <span class="font-mono" x-text="voidTarget.invoice"></span></h2>
                <p class="text-xs text-ink-secondary mt-1">Status nota akan diubah menjadi VOID dan seluruh stok bahan baku yang terpakai akan dikembalikan otomatis.</p>
            </div>

            <form method="POST" :action="'/transactions/' + voidTarget.id + '/void'" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold mb-1">Alasan Pembatalan (Wajib)</label>
                    <input type="text" name="void_reason" required placeholder="Contoh: Salah input menu pelanggan"
                           class="w-full h-11 rounded-control border border-border-strong text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="voidModal = false" class="h-11 px-4 rounded-control border border-border-strong text-sm font-semibold cursor-pointer">Batal</button>
                    <button type="submit" class="h-11 px-5 rounded-control bg-semantic-dangerText text-white text-sm font-semibold cursor-pointer">Ya, Void & Kembalikan Stok</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection