@extends('layouts.app')

@section('title', 'Printer & Sinkronisasi')
@section('page_title', 'Pengaturan Printer Thermal & Antrean Offline')

@section('content')
<div
    x-data="{
        supported: true,
        connected: false,
        printerName: '',
        paperWidth: Number(localStorage.getItem('warmindo_paper_width')) || 58,
        connecting: false,
        printing: false,
        statusMsg: '',
        errorMsg: '',
        queuedItems: [],
        syncing: false,
        async initPage() {
            if (window.WarmindoPrinter) {
                this.supported = window.WarmindoPrinter.isSupported();
                this.connected = window.WarmindoPrinter.isConnected();
                this.printerName = window.WarmindoPrinter.savedPrinterName;
            }
            window.addEventListener('warmindo:printer-updated', (e) => {
                this.connected = e.detail.connected;
                this.printerName = e.detail.name;
            });
            window.addEventListener('warmindo:sync-updated', (e) => {
                this.queuedItems = e.detail.items || [];
            });
            if (window.WarmindoOffline) {
                this.queuedItems = await window.WarmindoOffline.getAllQueued();
            }
        },
        async connectPrinter() {
            this.connecting = true;
            this.errorMsg = '';
            this.statusMsg = '';
            try {
                const name = await window.WarmindoPrinter.connect();
                this.statusMsg = 'Berhasil terhubung ke printer: ' + name;
            } catch (err) {
                this.errorMsg = err.message || 'Gagal menghubungkan printer Bluetooth.';
            } finally {
                this.connecting = false;
            }
        },
        disconnectPrinter() {
            window.WarmindoPrinter.disconnect();
            this.statusMsg = 'Koneksi printer diputuskan.';
        },
        updatePaper(mm) {
            this.paperWidth = mm;
            window.WarmindoPrinter.setPaperWidth(mm);
            this.statusMsg = 'Ukuran kertas diubah ke ' + mm + 'mm.';
        },
        async testPrint() {
            this.printing = true;
            this.errorMsg = '';
            this.statusMsg = '';
            try {
                await window.WarmindoPrinter.printTest();
                this.statusMsg = 'Struk uji coba berhasil dikirim ke printer.';
            } catch (err) {
                this.errorMsg = err.message || 'Gagal mencetak struk uji coba.';
            } finally {
                this.printing = false;
            }
        },
        async forceSync() {
            if (!window.WarmindoOffline) return;
            this.syncing = true;
            this.errorMsg = '';
            const res = await window.WarmindoOffline.syncQueue();
            this.syncing = false;
            if (res.synced > 0) {
                this.statusMsg = res.synced + ' transaksi offline berhasil disinkronkan.';
            } else if (!navigator.onLine) {
                this.errorMsg = 'Perangkat masih offline. Sinkronisasi akan berjalan otomatis saat internet aktif.';
            }
        },
        formatRp(val) {
            return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
        }
    }"
    x-init="initPage()"
    class="max-w-4xl mx-auto space-y-6"
>
    <!-- Peringatan Kompatibilitas Web Bluetooth -->
    <div x-show="!supported" x-cloak role="alert" class="p-4 rounded-control bg-semantic-warningBg text-semantic-warningText text-sm font-semibold">
        Browser ini belum mendukung Web Bluetooth API (misal Safari iOS). Gunakan Google Chrome di HP Android atau laptop Windows/macOS via localhost/HTTPS untuk cetak Bluetooth langsung.
    </div>

    <div x-show="statusMsg" x-cloak role="status" class="p-3.5 rounded-control bg-semantic-successBg text-semantic-successText text-sm font-semibold" x-text="statusMsg"></div>
    <div x-show="errorMsg" x-cloak role="alert" class="p-3.5 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-sm font-semibold" x-text="errorMsg"></div>

    <!-- KARTU 1: Koneksi Printer Thermal Bluetooth -->
    <div class="bg-surface border border-border rounded-control p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border pb-4">
            <div>
                <h2 class="text-lg font-bold text-ink-primary">Printer Thermal Bluetooth (ESC/POS)</h2>
                <p class="text-xs text-ink-secondary mt-0.5">Hubungkan printer kasir RPP02N / Panda / Eppos 58mm atau 80mm.</p>
            </div>
            <span
                :class="connected ? 'bg-semantic-successBg text-semantic-successText' : 'bg-subtle text-ink-secondary'"
                class="self-start sm:self-auto px-3 py-1 rounded-full text-xs font-bold"
                x-text="connected ? ('Terhubung: ' + printerName) : 'Belum Terhubung'"
            ></span>
        </div>

        <!-- Pilihan Lebar Kertas -->
        <div>
            <label class="block text-xs font-bold uppercase text-ink-secondary mb-2">Ukuran Lebar Kertas Thermal</label>
            <div class="grid grid-cols-2 gap-3 max-w-md">
                <button
                    type="button"
                    @click="updatePaper(58)"
                    :class="paperWidth === 58 ? 'border-brand-primary bg-brand-tint text-brand-primary' : 'border-border bg-surface text-ink-primary'"
                    class="p-3.5 rounded-control border text-left transition-colors cursor-pointer"
                >
                    <p class="text-sm font-bold">58 mm (Standar UMKM)</p>
                    <p class="text-xs text-ink-secondary mt-0.5">32 karakter per baris</p>
                </button>
                <button
                    type="button"
                    @click="updatePaper(80)"
                    :class="paperWidth === 80 ? 'border-brand-primary bg-brand-tint text-brand-primary' : 'border-border bg-surface text-ink-primary'"
                    class="p-3.5 rounded-control border text-left transition-colors cursor-pointer"
                >
                    <p class="text-sm font-bold">80 mm (Lebar)</p>
                    <p class="text-xs text-ink-secondary mt-0.5">48 karakter per baris</p>
                </button>
            </div>
        </div>

        <!-- Tombol Aksi Printer -->
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button
                type="button"
                x-show="!connected"
                :disabled="connecting || !supported"
                @click="connectPrinter()"
                class="h-12 px-5 rounded-control bg-brand-primary hover:bg-brand-hover disabled:bg-border-strong text-white font-semibold text-sm cursor-pointer"
            >
                <span x-show="!connecting">Hubungkan Printer Bluetooth</span>
                <span x-show="connecting">Mencari Perangkat...</span>
            </button>

            <button
                type="button"
                x-show="connected"
                :disabled="printing"
                @click="testPrint()"
                class="h-12 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-semibold text-sm cursor-pointer"
            >
                <span x-show="!printing">Tes Cetak Struk</span>
                <span x-show="printing">Mencetak...</span>
            </button>

            <button
                type="button"
                x-show="connected"
                @click="disconnectPrinter()"
                class="h-12 px-4 rounded-control border border-border-strong text-ink-secondary hover:text-semantic-dangerText text-sm font-semibold cursor-pointer"
            >
                Putuskan Koneksi
            </button>
        </div>
    </div>

    <!-- KARTU 2: Monitor Antrean Transaksi Offline (IndexedDB) -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-border flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-ink-primary">Antrean Transaksi Offline Lokal</h2>
                <p class="text-xs text-ink-secondary">Transaksi yang dibuat saat internet terputus tersimpan aman di perangkat dan otomatis terkirim saat online.</p>
            </div>
            <button
                type="button"
                :disabled="syncing || queuedItems.length === 0"
                @click="forceSync()"
                class="h-10 px-4 rounded-control border border-border-strong bg-subtle hover:bg-border disabled:opacity-50 text-xs font-bold text-ink-primary cursor-pointer"
            >
                <span x-show="!syncing">Sinkronkan Sekarang (<span x-text="queuedItems.length"></span>)</span>
                <span x-show="syncing">Mengirim...</span>
            </button>
        </div>

        <div class="divide-y divide-border text-sm">
            <template x-if="queuedItems.length === 0">
                <div class="p-8 text-center text-xs text-ink-muted">
                    Tidak ada transaksi tertunda. Seluruh data sudah tersinkronisasi dengan server.
                </div>
            </template>

            <template x-for="item in queuedItems" :key="item.id">
                <div class="p-4 flex items-center justify-between gap-4">
                    <div>
                        <p class="font-mono font-bold text-ink-primary" x-text="item.receiptPreview.invoice_number"></p>
                        <p class="text-xs text-ink-secondary" x-text="item.receiptPreview.created_at + ' • ' + item.receiptPreview.payment_method"></p>
                        <p x-show="item.errorMessage" class="text-xs text-semantic-dangerText font-semibold mt-0.5" x-text="item.errorMessage"></p>
                    </div>
                    <div class="text-right">
                        <span class="font-mono font-bold text-ink-primary block" x-text="formatRp(item.receiptPreview.total_amount)"></span>
                        <span
                            :class="item.status === 'PENDING' ? 'bg-semantic-warningBg text-semantic-warningText' : 'bg-semantic-dangerBg text-semantic-dangerText'"
                            class="inline-block mt-1 px-2 py-0.5 rounded-badge text-[11px] font-bold"
                            x-text="item.status"
                        ></span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection