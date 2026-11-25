<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'POS') — Warmindo Permata</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    x-data="{
        isOnline: navigator.onLine,
        pendingSyncCount: 0,
        printerConnected: false,
        printerName: localStorage.getItem('warmindo_printer_name') || '',
        syncToast: '',
        init() {
            window.addEventListener('online', () => this.isOnline = true);
            window.addEventListener('offline', () => this.isOnline = false);
            window.addEventListener('warmindo:sync-updated', (e) => {
                this.pendingSyncCount = e.detail.count;
            });
            window.addEventListener('warmindo:printer-updated', (e) => {
                this.printerConnected = e.detail.connected;
                this.printerName = e.detail.name;
            });
            window.addEventListener('warmindo:sync-completed', (e) => {
                this.syncToast = e.detail.syncedCount + ' transaksi offline berhasil disinkronkan ke server.';
                setTimeout(() => this.syncToast = '', 4000);
            });
        }
    }"
    class="min-h-screen bg-canvas text-ink-primary flex flex-col lg:flex-row">

    <!-- Desktop Left Sidebar (min-width: 1024px) -->
    <aside class="hidden lg:flex lg:flex-col lg:w-60 lg:fixed lg:inset-y-0 bg-surface border-r border-border z-30">
        <div class="h-14 px-5 flex items-center justify-between border-b border-border">
            <div>
                <span class="font-bold text-base tracking-tight text-ink-primary block leading-none">Warmindo Permata</span>
                <span class="text-[11px] font-semibold text-ink-muted uppercase tracking-wider">{{ auth()->user()->role }}</span>
            </div>
        </div>

        <nav aria-label="Navigasi Utama" class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @if (auth()->user()->isOwner())
            <a href="{{ route('dashboard.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('dashboard.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Dashboard</span>
            </a>
            @endif

            <a href="{{ route('pos.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('pos.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Kasir (POS)</span>
            </a>

            <a href="{{ route('transactions.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('transactions.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Riwayat Nota</span>
            </a>

            <a href="{{ route('shifts.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('shifts.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Tutup Kasir</span>
            </a>

            <a href="{{ route('inventory.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('inventory.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Stok & Restock</span>
            </a>

            @if (auth()->user()->isOwner())
            <a href="{{ route('catalog.index') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('catalog.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Menu & Resep</span>
            </a>
            @endif

            <a href="{{ route('settings.printer') }}"
                class="flex items-center justify-between px-3.5 h-11 rounded-control text-sm font-semibold transition-colors {{ request()->routeIs('settings.*') ? 'bg-brand-tint text-brand-primary' : 'text-ink-secondary hover:bg-subtle hover:text-ink-primary' }}">
                <span>Printer & Sync</span>
                <span x-show="pendingSyncCount > 0" x-cloak x-text="pendingSyncCount"
                    class="px-2 py-0.5 rounded-full font-mono text-[11px] font-bold bg-semantic-warningBg text-semantic-warningText"></span>
            </a>
        </nav>

        <div class="p-3 border-t border-border">
            <div class="px-3 py-2 mb-2 rounded-control bg-subtle">
                <p class="text-xs font-semibold text-ink-primary truncate">{{ auth()->user()->name }}</p>
                <p class="text-[11px] text-ink-secondary font-mono">{{ auth()->user()->username }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full h-10 px-3 rounded-control border border-border text-xs font-semibold text-ink-secondary hover:text-semantic-dangerText hover:border-semantic-dangerText transition-colors cursor-pointer">
                    Keluar Akun
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Wrapper -->
    <div class="flex-1 lg:pl-60 flex flex-col min-h-screen pb-16 lg:pb-0">
        <!-- TopStatusBar -->
        <header class="sticky top-0 z-20 h-13 bg-surface border-b border-border px-4 lg:px-6 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 lg:hidden">
                <span class="font-bold text-sm text-ink-primary">Warmindo Permata</span>
                <span class="px-1.5 py-0.5 text-[10px] font-bold rounded-badge bg-subtle text-ink-secondary">{{ auth()->user()->role }}</span>
            </div>

            <div class="hidden lg:block">
                <h1 class="text-base font-bold text-ink-primary">@yield('page_title', 'Operasional')</h1>
            </div>

            <div class="flex items-center gap-2">
                <!-- Printer Status Pill -->
                <a href="{{ route('settings.printer') }}"
                    :class="printerConnected ? 'bg-semantic-successBg text-semantic-successText' : 'bg-subtle text-ink-secondary hover:text-ink-primary'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-colors">
                    <span :class="printerConnected ? 'bg-semantic-successText' : 'bg-ink-muted'" class="w-2 h-2 rounded-full"></span>
                    <span x-text="printerConnected ? ('Printer: ' + printerName) : 'Printer'"></span>
                </a>

                <!-- Connection & Sync Status Pill -->
                <span
                    :class="(isOnline && pendingSyncCount === 0) ? 'bg-semantic-successBg text-semantic-successText' : 'bg-semantic-warningBg text-semantic-warningText'"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold"
                    role="status">
                    <span :class="(isOnline && pendingSyncCount === 0) ? 'bg-semantic-successText' : 'bg-semantic-warningText'" class="w-2 h-2 rounded-full"></span>
                    <span x-text="!isOnline ? ('Offline • ' + pendingSyncCount + ' Antrean') : (pendingSyncCount > 0 ? ('Syncing (' + pendingSyncCount + ')...') : 'Online')"></span>
                </span>

                <!-- Mobile Logout -->
                <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                    @csrf
                    <button type="submit" class="px-2.5 py-1 rounded-badge border border-border text-xs font-semibold text-ink-secondary">
                        Keluar
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash & Auto-Sync Notifications -->
        @if (session('success'))
        <div role="status" class="mx-4 lg:mx-6 mt-4 p-3.5 rounded-control bg-semantic-successBg text-semantic-successText text-sm font-semibold flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
        @endif

        <div x-show="syncToast" x-cloak role="status"
            class="mx-4 lg:mx-6 mt-4 p-3.5 rounded-control bg-semantic-successBg text-semantic-successText text-sm font-semibold">
            <span x-text="syncToast"></span>
        </div>

        @if ($errors->any())
        <div role="alert" class="mx-4 lg:mx-6 mt-4 p-3.5 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-sm font-semibold">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-6">
            @yield('content')
        </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (< 1024px) -->
    <nav aria-label="Navigasi Bawah" class="lg:hidden fixed bottom-0 inset-x-0 h-15 bg-surface border-t border-border z-30 grid {{ auth()->user()->isOwner() ? 'grid-cols-6' : 'grid-cols-5' }}">
        <a href="{{ route('pos.index') }}"
            class="flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('pos.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Kasir</span>
        </a>
        <a href="{{ route('transactions.index') }}"
            class="flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('transactions.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Nota</span>
        </a>
        <a href="{{ route('shifts.index') }}"
            class="flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('shifts.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Shift</span>
        </a>
        <a href="{{ route('inventory.index') }}"
            class="flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('inventory.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Stok</span>
        </a>
        @if (auth()->user()->isOwner())
        <a href="{{ route('catalog.index') }}"
            class="flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('catalog.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Resep</span>
        </a>
        @endif
        <a href="{{ route('settings.printer') }}"
            class="relative flex flex-col items-center justify-center text-[11px] font-semibold {{ request()->routeIs('settings.*') ? 'text-brand-primary bg-brand-tint/50' : 'text-ink-secondary' }}">
            <span>Printer</span>
            <span x-show="pendingSyncCount > 0" x-cloak x-text="pendingSyncCount"
                class="absolute top-1.5 right-2 px-1.5 rounded-full font-mono text-[10px] font-bold bg-semantic-warningBg text-semantic-warningText"></span>
        </a>
    </nav>
</body>

</html>