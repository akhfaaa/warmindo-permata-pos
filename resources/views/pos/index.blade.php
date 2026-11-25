@extends('layouts.app')

@section('title', 'Kasir POS')
@section('page_title', 'Kasir Utama — Warmindo Permata')

@section('content')
<div
    x-data="posApp()"
    x-init="initPos()"
    @keydown.window="handleGlobalKey($event)"
    class="h-full flex flex-col lg:flex-row gap-4 lg:gap-6"
>
    <!-- KIRI: Pencarian, Filter Kategori, & Grid Menu -->
    <section aria-label="Katalog Menu" class="flex-1 flex flex-col min-w-0">
        <!-- Offline Mode Notice -->
        <div x-show="!isOnline" x-cloak role="status"
             class="mb-3 px-3.5 py-2.5 rounded-control bg-semantic-warningBg text-semantic-warningText text-xs font-semibold flex items-center justify-between">
            <span>Mode Offline Aktif: Transaksi tetap berjalan & disimpan ke antrean lokal perangkat.</span>
        </div>

        <!-- Search & Category Filter Bar -->
        <div class="bg-surface border border-border rounded-control p-3 mb-4 space-y-3">
            <div class="relative">
                <input
                    x-ref="searchInput"
                    x-model="searchQuery"
                    type="search"
                    aria-label="Cari menu"
                    placeholder="Cari nama menu... (tekan / untuk fokus)"
                    class="w-full h-11 pl-3.5 pr-16 rounded-control border border-border-strong bg-subtle/60 text-sm text-ink-primary placeholder:text-ink-muted focus:bg-surface focus:border-brand-primary focus:ring-0"
                >
                <button
                    x-show="searchQuery.length > 0"
                    @click="searchQuery = ''"
                    type="button"
                    aria-label="Hapus pencarian"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-1 text-xs font-semibold text-ink-secondary hover:text-ink-primary cursor-pointer"
                >
                    Reset
                </button>
            </div>

            <!-- Category Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-0.5">
                <button
                    type="button"
                    @click="selectedCategory = 'ALL'"
                    :class="selectedCategory === 'ALL' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary'"
                    class="px-3.5 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                >
                    Semua Menu
                </button>
                @foreach ($categories as $cat)
                    <button
                        type="button"
                        @click="selectedCategory = '{{ $cat->id }}'"
                        :class="selectedCategory === '{{ $cat->id }}' ? 'bg-brand-primary text-white' : 'bg-subtle text-ink-secondary hover:text-ink-primary'"
                        class="px-3.5 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer"
                    >
                        {{ $cat->name }}
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Grid Kartu Menu (2 kolom Mobile, 3 Tablet, 4 Desktop) -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3 pb-24 lg:pb-6">
            <template x-for="product in filteredProducts()" :key="product.id">
                <button
                    type="button"
                    :disabled="!product.is_available"
                    @click="openModifierSheet(product)"
                    :class="{
                        'border-brand-primary bg-brand-tint/40': getProductQtyInCart(product.id) > 0,
                        'border-border bg-surface hover:border-border-strong': getProductQtyInCart(product.id) === 0 && product.is_available,
                        'opacity-55 cursor-not-allowed bg-subtle border-border': !product.is_available
                    }"
                    class="relative text-left p-3.5 rounded-control border transition-colors flex flex-col justify-between min-h-[104px] cursor-pointer active:scale-[0.99]"
                >
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-[11px] font-semibold text-ink-muted uppercase tracking-wide" x-text="product.category_name"></span>
                            <span
                                x-show="getProductQtyInCart(product.id) > 0"
                                x-text="getProductQtyInCart(product.id) + 'x'"
                                class="px-2 py-0.5 rounded-badge bg-brand-primary text-white font-mono text-xs font-bold"
                            ></span>
                            <span
                                x-show="!product.is_available"
                                class="px-1.5 py-0.5 rounded-badge bg-semantic-dangerBg text-semantic-dangerText text-[10px] font-bold"
                            >
                                Habis
                            </span>
                        </div>
                        <h2 class="text-[15px] leading-snug font-semibold text-ink-primary mt-1.5 line-clamp-2" x-text="product.name"></h2>
                    </div>

                    <p class="mt-3 font-mono tabular-nums text-sm font-bold text-ink-primary" x-text="formatRp(product.price)"></p>
                </button>
            </template>

            <div x-show="filteredProducts().length === 0" class="col-span-full bg-surface border border-border rounded-control p-8 text-center">
                <p class="text-sm font-semibold text-ink-primary">Menu tidak ditemukan</p>
                <p class="text-xs text-ink-secondary mt-1">Coba kata kunci lain atau pilih kategori "Semua Menu".</p>
            </div>
        </div>
    </section>

    <!-- KANAN: OrderTicketPanel (Tablet & Desktop Sticky Sidebar) -->
    <aside aria-label="Tiket Pesanan Aktif" class="hidden md:flex md:flex-col md:w-88 lg:w-96 bg-surface border border-border rounded-control md:sticky md:top-18 md:h-[calc(100vh-6.5rem)] overflow-hidden">
        <!-- Header Tiket -->
        <div class="p-4 border-b border-border space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-ink-primary">Pesanan Aktif</h2>
                <button
                    x-show="cart.length > 0"
                    @click="clearCart()"
                    type="button"
                    class="text-xs font-semibold text-semantic-dangerText hover:underline cursor-pointer"
                >
                    Kosongkan
                </button>
            </div>

            <!-- Order Type Segmented Control -->
            <div role="radiogroup" aria-label="Tipe Pesanan" class="grid grid-cols-2 gap-1.5 p-1 rounded-control bg-subtle">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="orderType === 'DINE_IN'"
                    @click="orderType = 'DINE_IN'; saveCartState()"
                    :class="orderType === 'DINE_IN' ? 'bg-surface text-ink-primary shadow-xs' : 'text-ink-secondary'"
                    class="h-9 rounded-badge text-xs font-bold transition-colors cursor-pointer"
                >
                    Makan di Tempat
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="orderType === 'TAKEAWAY'"
                    @click="orderType = 'TAKEAWAY'; saveCartState()"
                    :class="orderType === 'TAKEAWAY' ? 'bg-surface text-ink-primary shadow-xs' : 'text-ink-secondary'"
                    class="h-9 rounded-badge text-xs font-bold transition-colors cursor-pointer"
                >
                    Bungkus (Takeaway)
                </button>
            </div>
        </div>

        <!-- Daftar Item Keranjang -->
        <div class="flex-1 overflow-y-auto p-4 divide-y divide-border">
            <template x-if="cart.length === 0">
                <div class="h-full flex flex-col items-center justify-center text-center py-10 px-4">
                    <p class="text-sm font-semibold text-ink-secondary">Belum ada pesanan</p>
                    <p class="text-xs text-ink-muted mt-1">Ketuk kartu menu di sebelah kiri untuk menambahkan ke tiket pesanan.</p>
                </div>
            </template>

            <template x-for="(item, idx) in cart" :key="item.uid">
                <div class="py-3 first:pt-0 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink-primary leading-snug" x-text="item.name"></p>
                            <p x-show="item.modifiers.length > 0" class="text-xs text-brand-primary font-medium mt-0.5"
                               x-text="'+ ' + item.modifiers.map(m => m.name).join(', ')"></p>
                            <p x-show="item.notes" class="text-xs text-ink-secondary italic mt-0.5"
                               x-text="'Catatan: ' + item.notes"></p>
                        </div>
                        <span class="font-mono tabular-nums text-sm font-bold text-ink-primary whitespace-nowrap"
                              x-text="formatRp(item.unitPrice * item.quantity)"></span>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="inline-flex items-center border border-border-strong rounded-badge overflow-hidden">
                            <button
                                type="button"
                                @click="decrementItem(idx)"
                                :aria-label="'Kurangi jumlah ' + item.name"
                                class="w-9 h-9 flex items-center justify-center bg-subtle hover:bg-border text-ink-primary font-bold cursor-pointer"
                            >
                                -
                            </button>
                            <span class="w-9 text-center font-mono text-sm font-bold" x-text="item.quantity"></span>
                            <button
                                type="button"
                                @click="incrementItem(idx)"
                                :aria-label="'Tambah jumlah ' + item.name"
                                class="w-9 h-9 flex items-center justify-center bg-subtle hover:bg-border text-ink-primary font-bold cursor-pointer"
                            >
                                +
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="removeItem(idx)"
                            :aria-label="'Hapus ' + item.name"
                            class="px-2.5 py-1.5 text-xs font-semibold text-semantic-dangerText hover:bg-semantic-dangerBg rounded-badge cursor-pointer"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer Total & Tombol Bayar -->
        <div class="p-4 border-t border-border bg-subtle/50 space-y-3" aria-live="polite" aria-atomic="true">
            <div class="flex items-center justify-between text-xs text-ink-secondary">
                <span>Total Item: <strong class="font-mono text-ink-primary" x-text="totalItemsCount()"></strong></span>
                <span class="px-2 py-0.5 rounded-badge font-semibold"
                      :class="orderType === 'DINE_IN' ? 'bg-semantic-successBg text-semantic-successText' : 'bg-semantic-infoBg text-semantic-infoText'"
                      x-text="orderType === 'DINE_IN' ? 'DINE-IN' : 'TAKEAWAY'"></span>
            </div>

            <div class="flex items-baseline justify-between">
                <span class="text-sm font-bold text-ink-primary">TOTAL TAGIHAN</span>
                <span class="font-mono tabular-nums text-2xl font-bold text-ink-primary" x-text="formatRp(cartTotal())"></span>
            </div>

            <button
                type="button"
                :disabled="cart.length === 0"
                @click="openCheckoutModal()"
                :class="cart.length === 0 ? 'bg-border-strong text-white cursor-not-allowed' : 'bg-brand-primary hover:bg-brand-hover text-white cursor-pointer'"
                class="w-full h-14 rounded-control font-bold text-base transition-colors"
            >
                BAYAR SEKARANG
            </button>
        </div>
    </aside>

    <!-- MOBILE STICKY CART BAR (< 768px) -->
    <div
        x-show="cart.length > 0"
        x-cloak
        class="md:hidden fixed bottom-15 inset-x-3 z-20 bg-ink-primary text-white rounded-control shadow-floating p-3 flex items-center justify-between gap-3"
    >
        <div @click="mobileCartDrawer = true" class="cursor-pointer">
            <p class="text-xs text-white/80"><span class="font-mono font-bold" x-text="totalItemsCount()"></span> Item • <span x-text="orderType === 'DINE_IN' ? 'Dine-In' : 'Bungkus'"></span></p>
            <p class="font-mono tabular-nums text-lg font-bold" x-text="formatRp(cartTotal())"></p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="mobileCartDrawer = true" class="h-11 px-3 rounded-badge bg-white/15 text-xs font-semibold">
                Rincian
            </button>
            <button type="button" @click="openCheckoutModal()" class="h-11 px-4 rounded-badge bg-brand-primary text-white text-sm font-bold">
                Bayar
            </button>
        </div>
    </div>

    <!-- MOBILE CART DRAWER -->
    <div x-show="mobileCartDrawer" x-cloak class="md:hidden fixed inset-0 z-50 bg-ink-primary/60 flex items-end">
        <div @click.away="mobileCartDrawer = false" class="w-full max-h-[85vh] bg-surface rounded-t-modal flex flex-col overflow-hidden">
            <div class="p-4 border-b border-border flex items-center justify-between">
                <h2 class="text-base font-bold">Rincian Pesanan (<span x-text="totalItemsCount()"></span>)</h2>
                <button type="button" @click="mobileCartDrawer = false" class="text-sm font-semibold text-ink-secondary">Tutup</button>
            </div>
            <div class="p-3 border-b border-border grid grid-cols-2 gap-2 bg-subtle">
                <button type="button" @click="orderType = 'DINE_IN'; saveCartState()"
                        :class="orderType === 'DINE_IN' ? 'bg-surface text-ink-primary' : 'text-ink-secondary'"
                        class="h-10 rounded-badge text-xs font-bold">Makan di Tempat</button>
                <button type="button" @click="orderType = 'TAKEAWAY'; saveCartState()"
                        :class="orderType === 'TAKEAWAY' ? 'bg-surface text-ink-primary' : 'text-ink-secondary'"
                        class="h-10 rounded-badge text-xs font-bold">Bungkus</button>
            </div>
            <div class="flex-1 overflow-y-auto p-4 divide-y divide-border">
                <template x-for="(item, idx) in cart" :key="item.uid">
                    <div class="py-3 flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold" x-text="item.name"></p>
                            <p x-show="item.modifiers.length > 0" class="text-xs text-brand-primary" x-text="'+ ' + item.modifiers.map(m => m.name).join(', ')"></p>
                            <p class="font-mono text-xs font-bold mt-1" x-text="formatRp(item.unitPrice * item.quantity)"></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="decrementItem(idx)" class="w-10 h-10 rounded-badge bg-subtle font-bold">-</button>
                            <span class="font-mono font-bold w-6 text-center" x-text="item.quantity"></span>
                            <button type="button" @click="incrementItem(idx)" class="w-10 h-10 rounded-badge bg-subtle font-bold">+</button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="p-4 border-t border-border bg-subtle">
                <button type="button" @click="mobileCartDrawer = false; openCheckoutModal()"
                        class="w-full h-14 rounded-control bg-brand-primary text-white font-bold text-base">
                    Lanjut Bayar • <span class="font-mono" x-text="formatRp(cartTotal())"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL / SHEET 1: ModifierSheet (Pilih Topping & Catatan) -->
    <div x-show="modifierModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/55 flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div @click.away="modifierModal.open = false" class="w-full sm:max-w-md bg-surface border border-border rounded-t-modal sm:rounded-modal shadow-floating p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="text-xs font-semibold text-brand-primary uppercase" x-text="modifierModal.product?.category_name"></span>
                    <h2 class="text-lg font-bold text-ink-primary" x-text="modifierModal.product?.name"></h2>
                    <p class="font-mono text-sm font-semibold text-ink-secondary" x-text="formatRp(modifierModal.product?.price || 0)"></p>
                </div>
                <button type="button" @click="modifierModal.open = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">
                    Batal
                </button>
            </div>

            <!-- Pilihan Topping -->
            <div x-show="modifiersMaster.length > 0">
                <label class="block text-xs font-bold uppercase text-ink-secondary mb-2">Tambah Topping / Ekstra</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="mod in modifiersMaster" :key="mod.id">
                        <button
                            type="button"
                            @click="toggleModifier(mod)"
                            :class="isModifierSelected(mod.id) ? 'border-brand-primary bg-brand-tint text-brand-primary' : 'border-border bg-surface text-ink-primary'"
                            class="p-3 rounded-control border text-left transition-colors flex items-center justify-between cursor-pointer"
                        >
                            <span class="text-xs font-semibold" x-text="mod.name"></span>
                            <span class="font-mono text-xs font-bold" x-text="'+' + formatRp(mod.price)"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Catatan Dapur -->
            <div>
                <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Catatan Dapur (Opsional)</label>
                <input
                    type="text"
                    x-model="modifierModal.notes"
                    placeholder="Contoh: pedas sedang, kuah sedikit, telur setengah matang"
                    class="w-full h-11 px-3 rounded-control border border-border-strong text-sm"
                >
            </div>

            <!-- Jumlah Porsi -->
            <div class="flex items-center justify-between pt-1">
                <span class="text-sm font-semibold">Jumlah Porsi</span>
                <div class="inline-flex items-center border border-border-strong rounded-control overflow-hidden">
                    <button type="button" @click="if(modifierModal.qty > 1) modifierModal.qty--" class="w-11 h-11 bg-subtle font-bold text-lg cursor-pointer">-</button>
                    <span class="w-12 text-center font-mono font-bold text-base" x-text="modifierModal.qty"></span>
                    <button type="button" @click="modifierModal.qty++" class="w-11 h-11 bg-subtle font-bold text-lg cursor-pointer">+</button>
                </div>
            </div>

            <button
                type="button"
                @click="confirmAddToCart()"
                class="w-full h-14 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-bold text-base transition-colors cursor-pointer"
            >
                Tambah ke Pesanan • <span class="font-mono" x-text="formatRp(modifierModalTotal())"></span>
            </button>
        </div>
    </div>

    <!-- MODAL 2: Modal Pembayaran (Tunai & QRIS) -->
    <div x-show="checkoutModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="if(!checkoutModal.submitting) checkoutModal.open = false" class="w-full max-w-md bg-surface border border-border rounded-modal shadow-floating p-6 space-y-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-ink-secondary">Pembayaran Kasir</span>
                <button type="button" @click="checkoutModal.open = false" class="text-xs font-semibold text-ink-secondary hover:text-ink-primary cursor-pointer">Tutup (Esc)</button>
            </div>

            <!-- Total Tagihan Besar -->
            <div class="p-4 rounded-control bg-subtle border border-border text-center">
                <span class="text-xs font-semibold text-ink-secondary block">TOTAL YANG HARUS DIBAYAR</span>
                <span class="font-mono tabular-nums text-3xl font-bold text-ink-primary mt-1 block" x-text="formatRp(cartTotal())"></span>
            </div>

            <!-- Metode Pembayaran -->
            <div role="radiogroup" aria-label="Metode Pembayaran" class="grid grid-cols-2 gap-2 p-1 rounded-control bg-subtle">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="checkoutModal.paymentMethod === 'TUNAI'"
                    @click="checkoutModal.paymentMethod = 'TUNAI'"
                    :class="checkoutModal.paymentMethod === 'TUNAI' ? 'bg-surface text-ink-primary shadow-xs' : 'text-ink-secondary'"
                    class="h-11 rounded-badge text-sm font-bold transition-colors cursor-pointer"
                >
                    TUNAI
                </button>
                <button
                    type="button"
                    role="radio"
                    :aria-checked="checkoutModal.paymentMethod === 'QRIS'"
                    @click="checkoutModal.paymentMethod = 'QRIS'"
                    :class="checkoutModal.paymentMethod === 'QRIS' ? 'bg-semantic-infoBg text-semantic-infoText shadow-xs' : 'text-ink-secondary'"
                    class="h-11 rounded-badge text-sm font-bold transition-colors cursor-pointer"
                >
                    QRIS
                </button>
            </div>

            <!-- Panel Input TUNAI -->
            <div x-show="checkoutModal.paymentMethod === 'TUNAI'" class="space-y-3">
                <div>
                    <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Uang Diterima dari Pelanggan (Rp)</label>
                    <input
                        x-ref="cashInput"
                        type="number"
                        step="500"
                        min="0"
                        x-model.number="checkoutModal.cashReceived"
                        @keydown.enter.prevent="if(canSubmitCheckout()) submitCheckout()"
                        placeholder="0"
                        class="w-full h-14 px-4 rounded-control border border-border-strong font-mono tabular-nums text-2xl font-bold text-right"
                    >
                </div>

                <!-- Tombol Pecahan Cepat -->
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="checkoutModal.cashReceived = cartTotal()"
                            class="h-11 rounded-control border border-brand-primary bg-brand-tint text-brand-primary font-mono text-xs font-bold cursor-pointer">
                        Uang Pas
                    </button>
                    <button type="button" @click="checkoutModal.cashReceived = 10000"
                            class="h-11 rounded-control border border-border-strong bg-surface hover:bg-subtle font-mono text-xs font-bold cursor-pointer">
                        Rp 10.000
                    </button>
                    <button type="button" @click="checkoutModal.cashReceived = 20000"
                            class="h-11 rounded-control border border-border-strong bg-surface hover:bg-subtle font-mono text-xs font-bold cursor-pointer">
                        Rp 20.000
                    </button>
                    <button type="button" @click="checkoutModal.cashReceived = 50000"
                            class="h-11 rounded-control border border-border-strong bg-surface hover:bg-subtle font-mono text-xs font-bold cursor-pointer">
                        Rp 50.000
                    </button>
                    <button type="button" @click="checkoutModal.cashReceived = 100000"
                            class="h-11 rounded-control border border-border-strong bg-surface hover:bg-subtle font-mono text-xs font-bold cursor-pointer col-span-2">
                        Rp 100.000
                    </button>
                </div>

                <!-- Sorotan Kembalian -->
                <div
                    aria-live="polite"
                    :class="checkoutModal.cashReceived >= cartTotal() ? 'bg-semantic-successBg text-semantic-successText' : 'bg-semantic-dangerBg text-semantic-dangerText'"
                    class="p-3.5 rounded-control flex items-center justify-between"
                >
                    <span class="text-xs font-bold uppercase" x-text="checkoutModal.cashReceived >= cartTotal() ? 'KEMBALIAN' : 'KURANG BAYAR'"></span>
                    <span class="font-mono tabular-nums text-xl font-bold"
                          x-text="formatRp(Math.abs(checkoutModal.cashReceived - cartTotal()))"></span>
                </div>
            </div>

            <!-- Panel Konfirmasi QRIS -->
            <div x-show="checkoutModal.paymentMethod === 'QRIS'" class="p-4 rounded-control bg-semantic-infoBg/60 border border-semantic-infoText/20 space-y-2 text-sm text-ink-primary">
                <p class="font-bold text-semantic-infoText">Verifikasi QRIS Statis Warung</p>
                <p class="text-xs text-ink-secondary leading-relaxed">
                    Pastikan pelanggan telah memindai stand QRIS Warmindo Permata dan menunjukkan layar bukti transfer berhasil sebesar
                    <strong class="font-mono text-ink-primary" x-text="formatRp(cartTotal())"></strong>.
                </p>
            </div>

            <div x-show="checkoutModal.errorMessage" role="alert" class="p-3 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-xs font-semibold" x-text="checkoutModal.errorMessage"></div>

            <button
                type="button"
                :disabled="!canSubmitCheckout() || checkoutModal.submitting"
                @click="submitCheckout()"
                :class="(!canSubmitCheckout() || checkoutModal.submitting) ? 'bg-border-strong text-white cursor-not-allowed' : 'bg-brand-primary hover:bg-brand-hover text-white cursor-pointer'"
                class="w-full h-14 rounded-control font-bold text-base transition-colors"
            >
                <span x-show="!checkoutModal.submitting">Selesaikan Transaksi</span>
                <span x-show="checkoutModal.submitting">Menyimpan...</span>
            </button>
        </div>
    </div>

    <!-- MODAL 3: Struk Sukses Transaksi + Cetak Bluetooth -->
    <div x-show="receiptModal.open" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-surface border border-border rounded-modal shadow-floating p-6 space-y-4">
            <div
                :class="receiptModal.isOffline ? 'bg-semantic-warningBg text-semantic-warningText' : 'bg-semantic-successBg text-semantic-successText'"
                class="p-3 rounded-control text-center"
            >
                <p class="text-sm font-bold" x-text="receiptModal.isOffline ? 'Tersimpan Offline (Menunggu Sync)' : 'Transaksi Berhasil Disimpan'"></p>
                <p x-show="receiptModal.data?.payment_method === 'TUNAI'" class="font-mono text-lg font-bold mt-0.5">
                    Kembalian: <span x-text="formatRp(receiptModal.data?.change_amount || 0)"></span>
                </p>
            </div>

            <!-- Alert Gagal Cetak Printer (Tidak menduplikasi transaksi) -->
            <div x-show="receiptModal.printError" role="alert" class="p-3 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-xs font-semibold">
                <p x-text="receiptModal.printError"></p>
            </div>

            <!-- Pratinjau Struk Thermal -->
            <div class="p-4 rounded-control bg-canvas border border-dashed border-border-strong font-mono text-xs space-y-2 text-ink-primary">
                <div class="text-center border-b border-dashed border-border-strong pb-2">
                    <p class="font-bold text-sm">WARMINDO PERMATA</p>
                    <p class="text-[11px] text-ink-secondary" x-text="receiptModal.data?.invoice_number + ' • ' + receiptModal.data?.created_at"></p>
                    <p class="text-[11px] text-ink-secondary" x-text="'Kasir: ' + receiptModal.data?.cashier_name + ' (' + receiptModal.data?.order_type + ')'"></p>
                </div>

                <div class="space-y-1.5 py-1">
                    <template x-for="(it, i) in (receiptModal.data?.items || [])" :key="i">
                        <div>
                            <div class="flex justify-between">
                                <span x-text="it.quantity + 'x ' + it.name"></span>
                                <span x-text="formatRp(it.line_total)"></span>
                            </div>
                            <p x-show="it.modifiers.length > 0" class="text-[11px] text-ink-secondary pl-3" x-text="'+ ' + it.modifiers.join(', ')"></p>
                            <p x-show="it.notes" class="text-[11px] text-ink-secondary pl-3" x-text="'* ' + it.notes"></p>
                        </div>
                    </template>
                </div>

                <div class="border-t border-dashed border-border-strong pt-2 space-y-1">
                    <div class="flex justify-between font-bold">
                        <span>TOTAL (<span x-text="receiptModal.data?.payment_method"></span>)</span>
                        <span x-text="formatRp(receiptModal.data?.total_amount || 0)"></span>
                    </div>
                    <div class="flex justify-between text-ink-secondary">
                        <span>BAYAR</span>
                        <span x-text="formatRp(receiptModal.data?.cash_received || 0)"></span>
                    </div>
                    <div class="flex justify-between text-ink-secondary">
                        <span>KEMBALI</span>
                        <span x-text="formatRp(receiptModal.data?.change_amount || 0)"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    @click="reprintThermal()"
                    class="h-12 rounded-control border border-border-strong bg-subtle hover:bg-border text-ink-primary font-bold text-xs cursor-pointer"
                >
                    Cetak Struk Bluetooth
                </button>
                <button
                    type="button"
                    @click="receiptModal.open = false"
                    class="h-12 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-bold text-xs cursor-pointer"
                >
                    Transaksi Berikutnya
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function posApp() {
    return {
        productsMaster: {{ Js::from($products) }},
        modifiersMaster: {{ Js::from($modifiers) }},
        searchQuery: '',
        selectedCategory: 'ALL',
        orderType: 'DINE_IN',
        cart: [],
        mobileCartDrawer: false,

        modifierModal: {
            open: false,
            product: null,
            selectedModifiers: [],
            notes: '',
            qty: 1,
        },

        checkoutModal: {
            open: false,
            paymentMethod: 'TUNAI',
            cashReceived: 0,
            submitting: false,
            errorMessage: '',
        },

        receiptModal: {
            open: false,
            data: null,
            isOffline: false,
            printError: '',
        },

        async initPos() {
            const saved = localStorage.getItem('warmindo_active_cart');
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    this.cart = parsed.cart || [];
                    this.orderType = parsed.orderType || 'DINE_IN';
                } catch (e) {
                    localStorage.removeItem('warmindo_active_cart');
                }
            }

            if (window.WarmindoOffline) {
                if (this.productsMaster.length > 0) {
                    await window.WarmindoOffline.saveCatalogCache(this.productsMaster, this.modifiersMaster);
                } else {
                    const cached = await window.WarmindoOffline.getCatalogCache();
                    if (cached) {
                        this.productsMaster = cached.products || [];
                        this.modifiersMaster = cached.modifiers || [];
                    }
                }
            }
        },

        saveCartState() {
            localStorage.setItem('warmindo_active_cart', JSON.stringify({
                cart: this.cart,
                orderType: this.orderType,
            }));
        },

        handleGlobalKey(e) {
            if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                e.preventDefault();
                this.$refs.searchInput?.focus();
            } else if (e.key === 'Escape') {
                this.modifierModal.open = false;
                if (!this.checkoutModal.submitting) {
                    this.checkoutModal.open = false;
                }
                this.receiptModal.open = false;
            }
        },

        filteredProducts() {
            return this.productsMaster.filter(p => {
                const matchCat = this.selectedCategory === 'ALL' || p.category_id === this.selectedCategory;
                const matchQuery = !this.searchQuery.trim() || p.name.toLowerCase().includes(this.searchQuery.toLowerCase());
                return matchCat && matchQuery;
            });
        },

        getProductQtyInCart(productId) {
            return this.cart
                .filter(i => i.product_id === productId)
                .reduce((sum, i) => sum + i.quantity, 0);
        },

        openModifierSheet(product) {
            if (this.modifiersMaster.length === 0) {
                this.addDirectToCart(product, [], '', 1);
                return;
            }
            this.modifierModal = {
                open: true,
                product: product,
                selectedModifiers: [],
                notes: '',
                qty: 1,
            };
        },

        toggleModifier(mod) {
            const idx = this.modifierModal.selectedModifiers.findIndex(m => m.id === mod.id);
            if (idx > -1) {
                this.modifierModal.selectedModifiers.splice(idx, 1);
            } else {
                this.modifierModal.selectedModifiers.push(mod);
            }
        },

        isModifierSelected(modId) {
            return this.modifierModal.selectedModifiers.some(m => m.id === modId);
        },

        modifierModalTotal() {
            if (!this.modifierModal.product) return 0;
            const modSum = this.modifierModal.selectedModifiers.reduce((s, m) => s + m.price, 0);
            return (this.modifierModal.product.price + modSum) * this.modifierModal.qty;
        },

        confirmAddToCart() {
            this.addDirectToCart(
                this.modifierModal.product,
                [...this.modifierModal.selectedModifiers],
                this.modifierModal.notes.trim(),
                this.modifierModal.qty
            );
            this.modifierModal.open = false;
        },

        addDirectToCart(product, modifiers, notes, qty) {
            const sortedModIds = modifiers.map(m => m.id).sort().join(',');
            const signature = `${product.id}|${sortedModIds}|${notes}`;

            const existingIdx = this.cart.findIndex(i => i.uid === signature);
            const unitPrice = product.price + modifiers.reduce((s, m) => s + m.price, 0);

            if (existingIdx > -1) {
                this.cart[existingIdx].quantity += qty;
            } else {
                this.cart.push({
                    uid: signature,
                    product_id: product.id,
                    name: product.name,
                    unitPrice: unitPrice,
                    quantity: qty,
                    modifiers: modifiers,
                    notes: notes,
                });
            }
            this.saveCartState();
        },

        incrementItem(idx) {
            this.cart[idx].quantity++;
            this.saveCartState();
        },

        decrementItem(idx) {
            if (this.cart[idx].quantity > 1) {
                this.cart[idx].quantity--;
            } else {
                this.cart.splice(idx, 1);
            }
            this.saveCartState();
        },

        removeItem(idx) {
            this.cart.splice(idx, 1);
            this.saveCartState();
        },

        clearCart() {
            this.cart = [];
            this.saveCartState();
        },

        totalItemsCount() {
            return this.cart.reduce((s, i) => s + i.quantity, 0);
        },

        cartTotal() {
            return this.cart.reduce((s, i) => s + (i.unitPrice * i.quantity), 0);
        },

        openCheckoutModal() {
            if (this.cart.length === 0) return;
            this.checkoutModal = {
                open: true,
                paymentMethod: 'TUNAI',
                cashReceived: this.cartTotal(),
                submitting: false,
                errorMessage: '',
            };
            this.$nextTick(() => this.$refs.cashInput?.select());
        },

        canSubmitCheckout() {
            if (this.cart.length === 0) return false;
            if (this.checkoutModal.paymentMethod === 'QRIS') return true;
            return Number(this.checkoutModal.cashReceived) >= this.cartTotal();
        },

        async submitCheckout() {
            if (!this.canSubmitCheckout() || this.checkoutModal.submitting) return;

            this.checkoutModal.submitting = true;
            this.checkoutModal.errorMessage = '';

            const txUuid = window.WarmindoUUID ? window.WarmindoUUID() : null;
            const totalAmount = this.cartTotal();
            const cashReceived = this.checkoutModal.paymentMethod === 'TUNAI'
                ? Number(this.checkoutModal.cashReceived)
                : totalAmount;

            const payload = {
                id: txUuid,
                order_type: this.orderType,
                payment_method: this.checkoutModal.paymentMethod,
                cash_received: cashReceived,
                created_at: new Date().toISOString(),
                items: this.cart.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    modifier_ids: item.modifiers.map(m => m.id),
                    notes: item.notes || null,
                })),
            };

            const localReceiptPreview = {
                id: txUuid,
                invoice_number: 'OFF-' + new Date().toTimeString().slice(0, 8).replace(/:/g, ''),
                order_type: this.orderType,
                payment_method: this.checkoutModal.paymentMethod,
                subtotal: totalAmount,
                total_amount: totalAmount,
                cash_received: cashReceived,
                change_amount: Math.max(0, cashReceived - totalAmount),
                cashier_name: {{ Js::from(auth()->user()->name) }},
                created_at: new Date().toLocaleString('id-ID'),
                items: this.cart.map(item => ({
                    name: item.name,
                    quantity: item.quantity,
                    unit_price: item.unitPrice,
                    line_total: item.unitPrice * item.quantity,
                    modifiers: item.modifiers.map(m => m.name),
                    notes: item.notes,
                })),
            };

            let finalReceipt = null;
            let savedOffline = false;

            try {
                if (!navigator.onLine) {
                    throw new Error('OFFLINE_MODE');
                }

                // Batas waktu tunggu jaringan maksimal 4 detik sebelum fallback ke antrean lokal
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 4000);

                const response = await fetch('{{ route("pos.checkout") }}', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });

                clearTimeout(timeoutId);
                const data = await response.json();

                if (!response.ok) {
                    if (response.status === 422) {
                        this.checkoutModal.errorMessage = data.message || 'Data pembayaran tidak valid.';
                        this.checkoutModal.submitting = false;
                        return;
                    }
                    throw new Error('SERVER_UNREACHABLE');
                }

                finalReceipt = data.transaction;
            } catch (err) {
                if (window.WarmindoOffline) {
                    await window.WarmindoOffline.enqueueTransaction(payload, localReceiptPreview);
                    finalReceipt = localReceiptPreview;
                    savedOffline = true;
                } else {
                    this.checkoutModal.errorMessage = 'Koneksi terputus dan penyimpanan lokal tidak tersedia.';
                    this.checkoutModal.submitting = false;
                    return;
                }
            }

            this.checkoutModal.open = false;
            this.clearCart();

            let printError = '';
            if (window.WarmindoPrinter && window.WarmindoPrinter.isConnected()) {
                try {
                    await window.WarmindoPrinter.printReceipt(finalReceipt);
                } catch (pErr) {
                    printError = 'Transaksi tersimpan, printer gagal mencetak: ' + (pErr.message || 'Periksa Bluetooth/kertas.');
                }
            }

            this.receiptModal = {
                open: true,
                data: finalReceipt,
                isOffline: savedOffline,
                printError: printError,
            };
            this.checkoutModal.submitting = false;
        },

        async reprintThermal() {
            if (!this.receiptModal.data || !window.WarmindoPrinter) return;
            this.receiptModal.printError = '';
            try {
                if (!window.WarmindoPrinter.isConnected()) {
                    await window.WarmindoPrinter.connect();
                }
                await window.WarmindoPrinter.printReceipt(this.receiptModal.data);
            } catch (err) {
                this.receiptModal.printError = err.message || 'Gagal menghubungkan atau mencetak ke printer Bluetooth.';
            }
        },

        formatRp(val) {
            return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
        }
    };
}
</script>
@endsection