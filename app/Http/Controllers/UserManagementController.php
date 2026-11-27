<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::orderByRaw("FIELD(role, 'ADMINISTRATOR', 'ADMIN_UTAMA', 'ADMIN', 'CREW')")
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users'            => $users,
            'roles'            => User::ROLES,
            'availableMenus'   => User::AVAILABLE_MENUS,
            'defaultRoleMenus' => User::DEFAULT_ROLE_MENUS,
        ]);
    }

    public function store(Request $request)
    {
        $validMenuKeys = array_keys(User::AVAILABLE_MENUS);
        $validRoles    = array_keys(User::ROLES);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'username'      => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password'      => ['required', 'string', 'min:6'],
            'role'          => ['required', Rule::in($validRoles)],
            'allowed_menus' => ['nullable', 'array'],
            'allowed_menus.*' => [Rule::in($validMenuKeys)],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $menus = array_values(array_unique($validated['allowed_menus'] ?? []));
        if ($validated['role'] === User::ROLE_ADMINISTRATOR && ! in_array('users', $menus, true)) {
            $menus[] = 'users';
        }

        User::create([
            'name'          => $validated['name'],
            'username'      => strtolower($validated['username']),
            'password'      => Hash::make($validated['password']),
            'role'          => $validated['role'],
            'allowed_menus' => $menus,
            'is_active'     => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Akun {$validated['name']} berhasil dibuat dengan hak akses menu yang disesuaikan.");
    }

    public function update(Request $request, User $user)
    {
        $validMenuKeys = array_keys(User::AVAILABLE_MENUS);
        $validRoles    = array_keys(User::ROLES);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'username'      => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'password'      => ['nullable', 'string', 'min:6'],
            'role'          => ['required', Rule::in($validRoles)],
            'allowed_menus' => ['nullable', 'array'],
            'allowed_menus.*' => [Rule::in($validMenuKeys)],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $isSelf = $request->user()->id === $user->id;
        $newRole = $isSelf ? User::ROLE_ADMINISTRATOR : $validated['role'];
        $isActive = $isSelf ? true : $request->boolean('is_active', false);

        $menus = array_values(array_unique($validated['allowed_menus'] ?? []));
        if ($newRole === User::ROLE_ADMINISTRATOR && ! in_array('users', $menus, true)) {
            $menus[] = 'users';
        }

        $updateData = [
            'name'          => $validated['name'],
            'username'      => strtolower($validated['username']),
            'role'          => $newRole,
            'allowed_menus' => $menus,
            'is_active'     => $isActive,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return back()->with('success', "Perubahan role & daftar menu untuk {$user->name} berhasil disimpan.");
    }

   public function destroy(Request $request, User $user)
    {
        if ((string) $user->id === (string) $request->user()->id) {
            return back()->withErrors([
                'user' => 'Anda tidak dapat menghapus atau menonaktifkan akun yang sedang digunakan login.',
            ]);
        }

        $hasHistory = false;
        foreach (['cash_shifts', 'transactions', 'stock_movements'] as $relatedTable) {
            if (Schema::hasTable($relatedTable) && Schema::hasColumn($relatedTable, 'user_id')) {
                if (DB::table($relatedTable)->where('user_id', $user->id)->exists()) {
                    $hasHistory = true;
                    break;
                }
            }
        }

        if ($hasHistory) {
            return $this->deactivateOrRejectUser($user);
        }

        try {
            $name = $user->name;
            $user->delete();

            return back()->with('success', "Akun {$name} berhasil dihapus permanen.");
        } catch (QueryException $e) {
            return $this->deactivateOrRejectUser($user);
        }
    }

    private function deactivateOrRejectUser(User $user)
    {
        if (Schema::hasColumn('users', 'is_active')) {
            $user->is_active = false;
            $user->save();

            return back()->with(
                'success',
                "Akun {$user->name} memiliki riwayat shift/transaksi sehingga otomatis dinonaktifkan (tidak dihapus permanen) agar data laporan tetap aman."
            );
        }

        return back()->withErrors([
            'user' => "Akun {$user->name} tidak dapat dihapus karena sudah tercatat pada riwayat shift atau transaksi.",
        ]);
    }
}