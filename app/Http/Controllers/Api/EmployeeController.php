<?php

namespace App\Http\Controllers\Api;

use App\Support\Sql;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('employees.view'), 403);

        $query = Employee::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', Sql::like(), "%{$search}%")->orWhere('nip', Sql::like(), "%{$search}%");
            });
        }

        $employees = $query->orderBy('full_name')->paginate(min($request->integer('per_page', 20), 100));

        return EmployeeResource::collection($employees);
    }

    public function show(Request $request, Employee $employee)
    {
        abort_unless($request->user()->can('employees.view'), 403);

        $employee->load('supervisor');

        return new EmployeeResource($employee);
    }
}
