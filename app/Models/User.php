<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasUuids, Notifiable;

    public const ROLE_ADMINISTRATOR = 'ADMINISTRATOR';
    public const ROLE_ADMIN_UTAMA  = 'ADMIN_UTAMA';
    public const ROLE_ADMIN        = 'ADMIN';
    public const ROLE_CREW         = 'CREW';

    public const ROLES = [
        self::ROLE_ADMINISTRATOR => 'Administrator',
        self::ROLE_ADMIN_UTAMA   => 'Admin Utama',
        self::ROLE_ADMIN         => 'Admin',
        self::ROLE_CREW          => 'Crew',
    ];

    public const AVAILABLE_MENUS = [
        'dashboard' => [
            'label'       => 'Dashboard',
            'description' => 'Laporan omzet, HPP, laba kotor, grafik tren & ekspor CSV',
            'route'       => 'dashboard.index',
        ],
        'pos' => [
            'label'       => 'Kasir (POS)',
            'description' => 'Transaksi penjualan utama, pilihan topping & pembayaran',
            'route'       => 'pos.index',
        ],
        'transactions' => [
            'label'       => 'Riwayat Nota',
            'description' => 'Daftar transaksi harian & pembatalan nota (Void)',
            'route'       => 'transactions.index',
        ],
        'recap' => [
            'label'       => 'Rekap Penjualan & Belanja',
            'description' => 'Rekap harian penjualan, rekap belanja & pendapatan bersih per bulan',
            'route'       => 'recap.index',
        ],
        'shifts' => [
            'label'       => 'Tutup Kasir',
            'description' => 'Buka shift modal awal & tutup laporan laci uang kasir',
            'route'       => 'shifts.index',
        ],
        'inventory' => [
            'label'       => 'Stok & Restock',
            'description' => 'Monitoring stok bahan baku, restock belanja & stock opname',
            'route'       => 'inventory.index',
        ],
        'catalog' => [
            'label'       => 'Menu & Resep',
            'description' => 'Kelola daftar menu, harga jual, topping & takaran resep BOM',
            'route'       => 'catalog.index',
        ],
        'printer' => [
            'label'       => 'Printer & Sync',
            'description' => 'Koneksi printer thermal Bluetooth & antrean transaksi offline',
            'route'       => 'settings.printer',
        ],
        'users' => [
            'label'       => 'Manajemen User',
            'description' => 'Kelola akun user, peran (role) & penyesuaian menu per user',
            'route'       => 'users.index',
        ],
        'reports' => [
            'label'       => 'Pusat Laporan',
            'description' => '6 laporan lengkap (Penjualan Harian, Performa Menu, Arus Kas, Stok, Shift & Jam Ramai)',
            'route'       => 'reports.index',
        ],
    ];

    public const DEFAULT_ROLE_MENUS = [
        self::ROLE_ADMINISTRATOR => ['dashboard', 'pos', 'transactions', 'recap', 'shifts', 'inventory', 'catalog', 'printer', 'users', 'reports'],
        self::ROLE_ADMIN_UTAMA   => ['dashboard', 'pos', 'transactions', 'recap', 'shifts', 'inventory', 'catalog', 'printer', 'reports'],
        self::ROLE_ADMIN         => ['pos', 'transactions', 'recap', 'shifts', 'inventory', 'printer'],
        self::ROLE_CREW          => ['pos', 'transactions', 'shifts', 'printer'],
    ];

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'allowed_menus',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'allowed_menus' => 'array',
            'is_active'     => 'boolean',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isAdminUtama(): bool
    {
        return $this->role === self::ROLE_ADMIN_UTAMA;
    }

   public function isOwner(): bool
    {
        return in_array($this->role, [self::ROLE_ADMINISTRATOR, self::ROLE_ADMIN_UTAMA], true);
    }

    public function canEditDelete(): bool
    {
        return in_array($this->role, [self::ROLE_ADMINISTRATOR, self::ROLE_ADMIN_UTAMA], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function effectiveMenus(): array
    {
        $validKeys = array_keys(self::AVAILABLE_MENUS);
        $menus = is_array($this->allowed_menus)
            ? array_values(array_intersect($this->allowed_menus, $validKeys))
            : (self::DEFAULT_ROLE_MENUS[$this->role] ?? []);

        if ($this->isAdministrator() && ! in_array('users', $menus, true)) {
            $menus[] = 'users';
        }

        return $menus;
    }

    public function hasMenu(string $menuKey): bool
    {
        return in_array($menuKey, $this->effectiveMenus(), true);
    }

    public function defaultRouteName(): string
    {
        $priorityOrder = ['pos', 'dashboard', 'transactions', 'shifts', 'inventory', 'catalog', 'printer', 'users'];
        foreach ($priorityOrder as $key) {
            if ($this->hasMenu($key)) {
                return self::AVAILABLE_MENUS[$key]['route'];
            }
        }

        return 'settings.printer';
    }
}
