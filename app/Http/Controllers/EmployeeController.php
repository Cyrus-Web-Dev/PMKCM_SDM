<?php

namespace App\Http\Controllers;

use App\Support\Sql;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ImageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    /** Kolom form yang BUKAN kolom tabel employees (diproses terpisah). */
    private const ACCOUNT_FIELDS = ['create_account', 'account_email', 'account_password', 'account_role', 'photo'];

    public function __construct()
    {
        $this->middleware('permission:employees.view')->only(['index', 'show', 'photo']);
        $this->middleware('permission:employees.manage')->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = Employee::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', Sql::like(), "%{$search}%")
                    ->orWhere('nip', Sql::like(), "%{$search}%");
            });
        }

        if ($team = $request->input('team')) {
            $query->where('team', Sql::like(), Sql::contains($team));
        }

        if ($status = $request->input('employment_status')) {
            $query->where('employment_status', $status);
        }

        $employees = $query->orderBy('full_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $e) => [
                'id' => $e->id,
                'nip' => $e->nip,
                'full_name' => $e->full_name,
                'photo_url' => $e->photo_url,
                'team' => $e->team,
                'employment_type' => $e->employment_type_label,
                'employment_status' => $e->employment_status,
                'employment_status_label' => $e->employment_status_label,
                'join_date' => $e->join_date?->format('d/m/Y'),
            ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'team', 'employment_status']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            'supervisors' => Employee::where('employment_status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
            'canCreateAccount' => $request->user()->can('users.manage'),
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();
        $wantsAccount = (bool) ($data['create_account'] ?? false);
        abort_if($wantsAccount && ! $request->user()->can('users.manage'), 403, 'Hanya Owner yang boleh membuat akun login.');

        $data = Arr::except($data, self::ACCOUNT_FIELDS);

        DB::transaction(function () use ($request, $data, $wantsAccount) {
            if ($wantsAccount) {
                $data['user_id'] = $this->makeAccount($request, $request->input('full_name'))->id;
            }

            if ($request->hasFile('photo')) {
                $data['photo_path'] = $this->storePhoto($request);
            }

            $employee = Employee::create($data);

            ActivityLogger::log('employee.created', "Menambahkan karyawan baru: {$employee->full_name} ({$employee->nip})", $employee, auth()->id());
        });

        return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil ditambahkan.'.($wantsAccount ? ' Akun login dibuat; karyawan wajib mengganti password saat login pertama.' : ''));
    }

    public function show(Employee $employee)
    {
        $user = auth()->user();
        $employee->load(['supervisor']);

        // KEAMANAN: catatan internal & tautan akun HANYA untuk yang punya employees.manage.
        if (! $user->can('employees.manage')) {
            $employee->makeHidden(['notes', 'user_id']);
        }

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Request $request, Employee $employee)
    {
        return Inertia::render('Employees/Form', [
            'employee' => $employee,
            'supervisors' => Employee::where('employment_status', 'active')->where('id', '!=', $employee->id)->orderBy('full_name')->get(['id', 'full_name']),
            'canCreateAccount' => $request->user()->can('users.manage'),
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();
        $wantsAccount = (bool) ($data['create_account'] ?? false);
        abort_if($wantsAccount && ! $request->user()->can('users.manage'), 403, 'Hanya Owner yang boleh membuat akun login.');
        abort_if($wantsAccount && $employee->user_id, 422, 'Karyawan ini sudah punya akun login.');

        $data = Arr::except($data, self::ACCOUNT_FIELDS);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->storePhoto($request);

            if ($employee->photo_path) {
                Storage::disk('local')->delete($employee->photo_path);
            }
        }

        DB::transaction(function () use ($request, $employee, $data, $wantsAccount) {
            if ($wantsAccount) {
                $data['user_id'] = $this->makeAccount($request, $employee->full_name)->id;
            }

            $employee->update($data);
        });

        $changes = ActivityLogger::changes($employee, ['npwp', 'bank_account_number', 'bank_account_name', 'bpjs_kesehatan_number', 'bpjs_tk_number', 'base_salary']);
        ActivityLogger::log('employee.updated', "Memperbarui data karyawan: {$employee->full_name} ({$employee->nip})", $employee, auth()->id(), $changes ?: null);

        return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil diperbarui.'.($wantsAccount ? ' Akun login dibuat; karyawan wajib mengganti password saat login pertama.' : ''));
    }

    /** Soft delete - data tidak hilang permanen, bisa dipulihkan lewat database bila diperlukan. */
    public function destroy(Employee $employee)
    {
        $name = $employee->full_name;
        $employee->delete();

        ActivityLogger::log('employee.deleted', "Menghapus (arsip) karyawan: {$name}", null, auth()->id());

        return back()->with('success', 'Data karyawan diarsipkan.');
    }

    /** Menyajikan foto lewat aplikasi (private disk, bukan symlink publik). */
    public function photo(Employee $employee)
    {
        abort_unless($employee->photo_path && Storage::disk('local')->exists($employee->photo_path), 404);

        return Storage::disk('local')->response($employee->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function export()
    {
        abort_unless(auth()->user()->can('employees.export'), 403);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\EmployeesExport, 'data-karyawan-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Buat akun login (HANYA Owner, dicek pemanggil). Password awal diketik Owner dan sengaja sederhana;
     * must_change_password = true memaksa karyawan menggantinya saat login pertama.
     */
    private function makeAccount(Request $request, string $name): User
    {
        $actor = $request->user();
        $role = $request->input('account_role') ?: 'karyawan';
        abort_if($role === 'super-admin' && ! $actor->hasRole('super-admin'), 403, 'Hanya Owner yang boleh membuat akun Owner.');

        $user = User::create([
            'name' => $name,
            'email' => Str::lower($request->input('account_email')),
            'password' => $request->input('account_password'),
            'status' => 'active',
            'must_change_password' => true,
        ]);
        $user->assignRole($role);

        ActivityLogger::log('user.created', "Membuat akun login: {$user->email} (role: {$role})", $user, $actor->id);

        return $user;
    }

    /** @return array<int,string> */
    private function assignableRoles(User $actor): array
    {
        return $actor->hasRole('super-admin')
            ? Role::orderBy('name')->pluck('name')->all()
            : Role::where('name', '!=', 'super-admin')->orderBy('name')->pluck('name')->all();
    }

    private function storePhoto(Request $request): string
    {
        $binary = ImageProcessor::squareJpeg($request->file('photo')->getRealPath())
            ?? file_get_contents($request->file('photo')->getRealPath());
        $path = 'employee-photos/'.Str::random(20).'.jpg';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
