<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Warmindo Permata POS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-surface border border-border rounded-modal p-6 sm:p-8">
        <div class="mb-6">
            <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-badge bg-brand-tint text-brand-primary mb-2">
                SISTEM KASIR & STOK
            </span>
            <h1 class="text-2xl font-bold text-ink-primary tracking-tight">Warmindo Permata</h1>
            <p class="text-sm text-ink-secondary mt-1">Masuk menggunakan akun Kasir atau Pemilik untuk memulai operasional.</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-4 p-3 rounded-control bg-semantic-dangerBg text-semantic-dangerText text-sm font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="username" class="block text-sm font-semibold text-ink-primary mb-1.5">Username</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    value="{{ old('username') }}"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="contoh: kasir atau owner"
                    class="w-full h-12 px-3.5 rounded-control border border-border-strong bg-surface text-ink-primary text-base focus:border-brand-primary focus:ring-0"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-ink-primary mb-1.5">Kata Sandi</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full h-12 px-3.5 rounded-control border border-border-strong bg-surface text-ink-primary text-base focus:border-brand-primary focus:ring-0"
                >
            </div>

            <button
                type="submit"
                class="w-full h-14 mt-2 rounded-control bg-brand-primary hover:bg-brand-hover text-white font-semibold text-base transition-colors cursor-pointer"
            >
                Masuk ke Sistem
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-border text-xs text-ink-muted flex justify-between">
            <span>Akun Demo Kasir: <strong class="font-mono text-ink-secondary">kasir / kasir123</strong></span>
            <span>Owner: <strong class="font-mono text-ink-secondary">owner / permata123</strong></span>
        </div>
    </main>
</body>
</html>