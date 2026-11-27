@extends('layouts.app')

@section('title', 'Manajemen User')
@section('page_title', 'Manajemen User & Hak Akses Menu')

@section('content')
<div
    x-data="userManagementApp()"
    class="max-w-7xl mx-auto space-y-6"
>
    <!-- Header Ringkasan Role -->
    <div class="bg-surface border border-border rounded-control p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-lg font-bold text-ink-primary">Daftar Pengguna & Kontrol Menu</h2>
            <p class="text-xs text-ink-secondary mt-0.5">
                Atur peran (Administrator, Admin Utama, Admin, Crew) dan pilih menu spesifik yang muncul pada setiap akun.
            </p>
        </div>
        <button
            type="button"
            @click="openCreateModal()"
            class="h-11 px-5 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-sm font-bold cursor-pointer self-start sm:self-auto"
        >
            + Tambah User Baru
        </button>
    </div>

    <!-- Tabel Daftar User -->
    <div class="bg-surface border border-border rounded-control overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-subtle text-[11px] font-bold uppercase text-ink-secondary">
                        <th class="py-3.5 px-4">Nama & Username</th>
                        <th class="py-3.5 px-4">Peran (Role)</th>
                        <th class="py-3.5 px-4">Menu yang Ditampilkan</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($users as $u)
                        @php
                            $userMenus = $u->effectiveMenus();
                            $roleBadgeClass = match ($u->role) {
                                'ADMINISTRATOR' => 'bg-ink-primary text-white',
                                'ADMIN_UTAMA'   => 'bg-brand-tint text-brand-primary',
                                'ADMIN'         => 'bg-semantic-infoBg text-semantic-infoText',
                                default         => 'bg-subtle text-ink-secondary',
                            };
                        @endphp
                        <tr class="hover:bg-subtle/40">
                            <td class="py-3.5 px-4">
                                <p class="font-bold text-ink-primary">
                                    {{ $u->name }}
                                    @if (auth()->id() === $u->id)
                                        <span class="ml-1 px-1.5 py-0.5 text-[10px] rounded-badge bg-semantic-successBg text-semantic-successText">Anda</span>
                                    @endif
                                </p>
                                <p class="font-mono text-xs text-ink-secondary">{{ $u->username }}</p>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-badge text-xs font-bold {{ $roleBadgeClass }}">
                                    {{ $u->roleLabel() }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1.5 max-w-md">
                                    @forelse ($userMenus as $mKey)
                                        <span class="px-2 py-0.5 rounded-badge bg-subtle border border-border text-[11px] font-semibold text-ink-primary">
                                            {{ $availableMenus[$mKey]['label'] ?? $mKey }}
                                        </span>
                                    @empty
                                        <span class="text-xs italic text-semantic-dangerText">Tidak ada menu aktif</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="py-3.5 px-4 whitespace-nowrap">
                                @if ($u->is_active)
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-successBg text-semantic-successText">
                                        Aktif
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-semantic-dangerBg text-semantic-dangerText">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-right whitespace-nowrap space-x-2">
                                <button
                                    type="button"
                                    @click='openEditModal({
                                        id: @json($u->id),
                                        name: @json($u->name),
                                        username: @json($u->username),
                                        role: @json($u->role),
                                        menus: @json($userMenus),
                                        is_active: {{ $u->is_active ? "true" : "false" }},
                                        is_self: {{ auth()->id() === $u->id ? "true" : "false" }}
                                    })'
                                    class="h-9 px-3 rounded-control border border-border-strong bg-surface hover:bg-subtle text-xs font-bold text-ink-primary cursor-pointer"
                                >
                                    Atur Role & Menu
                                </button>

                                @if (auth()->id() !== $u->id)
                                    <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline"
                                          onsubmit="return confirm('Hapus akun {{ $u->name }} secara permanen?')">
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
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL TAMBAH / EDIT USER & HAK AKSES MENU -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 bg-ink-primary/60 flex items-center justify-center p-4">
        <div @click.away="modalOpen = false" class="w-full max-w-2xl bg-surface border border-border rounded-modal shadow-floating p-6 space-y-5 max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="text-lg font-bold text-ink-primary" x-text="isEdit ? ('Atur Akun & Menu: ' + form.name) : 'Tambah User Baru'"></h3>
                    <p class="text-xs text-ink-secondary">Pilih peran terlebih dahulu, lalu sesuaikan centang menu sesuai kebutuhan user.</p>
                </div>
                <button type="button" @click="modalOpen = false" class="px-2.5 py-1 rounded-badge bg-subtle text-xs font-semibold cursor-pointer">
                    Tutup
                </button>
            </div>

            <form method="POST" :action="isEdit ? ('/users/' + form.id) : '{{ route('users.store') }}'" class="space-y-5">
                @csrf
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Nama Lengkap</label>
                        <input type="text" name="name" x-model="form.name" required
                               placeholder="Contoh: Budi Santoso"
                               class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Username Login</label>
                        <input type="text" name="username" x-model="form.username" required
                               placeholder="Contoh: budi_crew"
                               class="w-full h-11 px-3 rounded-control border border-border-strong font-mono text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">Peran (Role)</label>
                        <select name="role" x-model="form.role" @change="applyRolePreset()" :disabled="form.is_self"
                                class="w-full h-11 px-3 rounded-control border border-border-strong text-sm font-semibold bg-surface">
                            @foreach ($roles as $rKey => $rLabel)
                                <option value="{{ $rKey }}">{{ $rLabel }}</option>
                            @endforeach
                        </select>
                        <template x-if="form.is_self">
                            <input type="hidden" name="role" value="ADMINISTRATOR">
                        </template>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-secondary mb-1.5">
                            Password <span x-show="isEdit" class="font-normal lowercase text-ink-muted">(kosongkan jika tetap)</span>
                        </label>
                        <input type="password" name="password" x-model="form.password" :required="!isEdit"
                               placeholder="Minimal 6 karakter"
                               class="w-full h-11 px-3 rounded-control border border-border-strong text-sm">
                    </div>
                </div>

                <!-- Pilihan Kustomisasi Menu -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase text-ink-secondary">Menu yang Tampil & Dapat Diakses</label>
                        <div class="flex items-center gap-2 text-xs">
                            <button type="button" @click="applyRolePreset()" class="font-semibold text-brand-primary hover:underline cursor-pointer">
                                Reset ke Default Role
                            </button>
                            <span class="text-ink-muted">|</span>
                            <button type="button" @click="selectAllMenus()" class="font-semibold text-ink-secondary hover:text-ink-primary cursor-pointer">
                                Pilih Semua
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @foreach ($availableMenus as $mKey => $mMeta)
                            <label
                                :class="form.menus.includes('{{ $mKey }}') ? 'border-brand-primary bg-brand-tint/40' : 'border-border bg-surface'"
                                class="p-3 rounded-control border flex items-start gap-3 cursor-pointer transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    name="allowed_menus[]"
                                    value="{{ $mKey }}"
                                    x-model="form.menus"
                                    :disabled="form.role === 'ADMINISTRATOR' && '{{ $mKey }}' === 'users'"
                                    class="mt-0.5 rounded border-border-strong text-brand-primary focus:ring-0"
                                >
                                <div class="min-w-0">
                                    <span class="text-xs font-bold text-ink-primary block">{{ $mMeta['label'] }}</span>
                                    <span class="text-[11px] text-ink-secondary leading-snug block mt-0.5">{{ $mMeta['description'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Status Aktif Akun -->
                <div class="flex items-center justify-between pt-2 border-t border-border">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink-primary cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" :disabled="form.is_self"
                               class="rounded border-border-strong text-brand-primary">
                        <span>Akun Aktif (Diizinkan Login)</span>
                    </label>
                    <template x-if="form.is_self">
                        <input type="hidden" name="is_active" value="1">
                    </template>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="modalOpen = false"
                                class="h-11 px-4 rounded-control border border-border-strong text-xs font-bold text-ink-secondary cursor-pointer">
                            Batal
                        </button>
                        <button type="submit"
                                class="h-11 px-6 rounded-control bg-brand-primary hover:bg-brand-hover text-white text-xs font-bold cursor-pointer">
                            Simpan Pengaturan User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function userManagementApp() {
    const defaultPresets = {{ Js::from($defaultRoleMenus) }};
    const allMenuKeys = {{ Js::from(array_keys($availableMenus)) }};

    return {
        modalOpen: false,
        isEdit: false,
        form: {
            id: null,
            name: '',
            username: '',
            password: '',
            role: 'CREW',
            menus: [...defaultPresets['CREW']],
            is_active: true,
            is_self: false,
        },

        openCreateModal() {
            this.isEdit = false;
            this.form = {
                id: null,
                name: '',
                username: '',
                password: '',
                role: 'CREW',
                menus: [...defaultPresets['CREW']],
                is_active: true,
                is_self: false,
            };
            this.modalOpen = true;
        },

        openEditModal(userData) {
            this.isEdit = true;
            this.form = {
                id: userData.id,
                name: userData.name,
                username: userData.username,
                password: '',
                role: userData.role,
                menus: [...userData.menus],
                is_active: userData.is_active,
                is_self: userData.is_self,
            };
            this.modalOpen = true;
        },

        applyRolePreset() {
            const preset = defaultPresets[this.form.role] || [];
            this.form.menus = [...preset];
        },

        selectAllMenus() {
            this.form.menus = [...allMenuKeys];
        },
    };
}
</script>
@endsection