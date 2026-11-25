<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/**
 * Manajemen role & hak akses. HANYA Owner (super-admin). Owner bebas membuat role baru (mis. "KA Unit",
 * "KA Gudang") dan menentukan hak aksesnya lewat checklist: lihat saja, kelola penuh (CRUD), menilai, dst.
 */
class RoleController extends Controller
{
    /** Role sistem: super-admin tidak bisa diubah sama sekali; karyawan tidak bisa dihapus/diganti namanya. */
    private const PROTECTED = ['super-admin', 'karyawan'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless($request->user()?->hasRole('super-admin'), 403, 'Hanya Owner yang boleh mengelola role & hak akses.');

            return $next($request);
        });
    }

    public function index()
    {
        $counts = DB::table('model_has_roles')->where('model_type', User::class)
            ->selectRaw('role_id, count(*) as n')->groupBy('role_id')->pluck('n', 'role_id');

        $roles = Role::with('permissions:id,name')->orderBy('name')->get()->map(fn (Role $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'owner' => $r->name === 'super-admin',
            'protected' => in_array($r->name, self::PROTECTED, true),
            'users' => (int) ($counts[$r->id] ?? 0),
            'access' => $r->name === 'super-admin' ? [] : PermissionCatalog::summarize($r->permissions->pluck('name')->all()),
        ]);

        return Inertia::render('Roles/Index', ['roles' => $roles]);
    }

    public function create()
    {
        return Inertia::render('Roles/Form', ['role' => null, 'groups' => PermissionCatalog::forUi()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);

        ActivityLogger::log('role.created', "Membuat role \"{$role->name}\" dengan ".count($data['permissions']).' hak akses', null, $request->user()->id, ['hak_akses' => ['dari' => '', 'ke' => implode(', ', $data['permissions'])]]);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" dibuat.");
    }

    public function edit(Role $role)
    {
        abort_if($role->name === 'super-admin', 403, 'Role Owner tidak bisa diubah.');

        return Inertia::render('Roles/Form', [
            'role' => ['id' => $role->id, 'name' => $role->name, 'protected' => in_array($role->name, self::PROTECTED, true), 'permissions' => $role->permissions->pluck('name')->all()],
            'groups' => PermissionCatalog::forUi(),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->name === 'super-admin', 403, 'Role Owner tidak bisa diubah.');

        $data = $this->validated($request, $role);
        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $oldName = $role->name;

        if (! in_array($oldName, self::PROTECTED, true)) {
            $role->update(['name' => $data['name']]);
        }
        $role->syncPermissions($data['permissions']);

        $after = collect($data['permissions'])->sort()->values()->all();
        $changes = [];
        if ($before !== $after) {
            $changes['hak_akses'] = ['dari' => implode(', ', $before), 'ke' => implode(', ', $after)];
        }
        if ($oldName !== $role->name) {
            $changes['nama'] = ['dari' => $oldName, 'ke' => $role->name];
        }
        ActivityLogger::log('role.updated', "Mengubah role \"{$role->name}\"", null, $request->user()->id, $changes ?: null);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" diperbarui. Perubahan hak akses berlaku pada akses berikutnya.");
    }

    public function destroy(Request $request, Role $role)
    {
        abort_if(in_array($role->name, self::PROTECTED, true), 403, 'Role sistem tidak bisa dihapus.');

        $users = DB::table('model_has_roles')->where('model_type', User::class)->where('role_id', $role->id)->count();
        abort_if($users > 0, 422, "Role ini masih dipakai {$users} pengguna. Pindahkan mereka ke role lain dulu (menu Kelola Pengguna).");

        $name = $role->name;
        $role->delete();
        ActivityLogger::log('role.deleted', "Menghapus role \"{$name}\"", null, $request->user()->id);

        return redirect()->route('roles.index')->with('success', "Role \"{$name}\" dihapus.");
    }

    /** @return array{name: string, permissions: array<int,string>} */
    private function validated(Request $request, ?Role $role): array
    {
        $isProtected = $role && in_array($role->name, self::PROTECTED, true);

        $data = $request->validate([
            'name' => $isProtected ? ['nullable'] : [
                'required', 'string', 'min:2', 'max:40', 'regex:/^[\pL\pN][\pL\pN .\-]*$/u',
                Rule::unique('roles', 'name')->ignore($role?->id), Rule::notIn(['super-admin']),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ], [
            'name.regex' => 'Nama role hanya boleh huruf, angka, spasi, titik, dan tanda hubung.',
            'name.not_in' => 'Nama ini dicadangkan untuk Owner.',
            'name.unique' => 'Nama role sudah dipakai.',
        ]);

        return [
            'name' => $isProtected ? $role->name : trim($data['name']),
            'permissions' => PermissionCatalog::normalize($data['permissions'] ?? []),
        ];
    }
}
