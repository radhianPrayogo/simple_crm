<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Employee;

class EmployeeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // check user permission
        if (!auth()->user()->hasAnyPermission(['view.employee','view.fellow'])) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $page = $request->has('page') ? $request->page : 1;
        $per_page = $request->has('per_page') ? $request->per_page : 10;

        $offset = ($page - 1) * $per_page;
        $limit = $per_page;

        // check if user have company
        $user_role = auth()->user()->getRoleNames();
        $role_name = $user_role[0];
        if (auth()->user()->employee->company) {
            $employees = Employee::select('id','name','user_id')
                                ->where('company_id', auth()->user()->employee->company->id)
                                ->whereHas('user', function($query) use ($role_name) {
                                    switch($role_name) {
                                        case 'manager';
                                            $query->whereHas('roles', function($roleQuery) {
                                                $roleQuery->whereIn('name', ['manager','employee','fellow']);
                                            });
                                            break;
                                        case 'employee':
                                            $query->whereHas('roles', function($roleQuery) {
                                                $roleQuery->whereIn('name', ['fellow']);
                                            });
                                            break;
                                        default:
                                            $query->whereNull('id');
                                            break;
                                    }
                                })
                                ->offset($offset)
                                ->limit($limit)
                                ->get();
            $total = Employee::where('company_id', auth()->user()->employee->company->id)->count();
        } else {
            $employees = [];
            $total = 0;
        }

        $result = [
            'page' => $page,
            'total' => $total,
            'data' => $employees
        ];
        return $this->sendResponse('Get data success', $result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['manager','employee','fellow'])],
            'name' => 'required',
            'phone' => ['required', 'min:8', 'max:15', 'unique:employees,phone'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->mixedCase()->numbers()->symbols()]
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 400);       
        }

        // check user permission
        if (!auth()->user()->hasPermissionTo('create.'.$request->type)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $role = Role::where('name', $request->type)->first();
        if (!$role) {
            return $this->sendError('Role '.ucfirst($request->type).' not set up yet', [], 401);
        }

        DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            // assign role to user
            $user->assignRole($role);

            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => auth()->user()->employee->company->id,
                'name' => $request->name,
                'phone' => $request->phone,
                'address' => $request->address ? $request->address : ''
            ]);

            DB::commit();
            return $this->sendResponse('Save employee data success', [], 201);
        }catch(\Exception $e) {
            DB::rollback();
            return $this->sendError('Save employee data failed', [], 400);
        }
            
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $employee = Employee::select('id','name','phone','address','user_id')->where('id', $id)->first();
        if (!$employee) {
            return $this->sendError('Data not found', [], 404);
        }

        $user = $employee->user;
        $role = $user->getRoleNames();

        // check user permission
        if (!auth()->user()->hasPermissionTo('view.'.$role[0])) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $result = [
            'id' => $employee->id,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'address' => $employee->address ? $employee->address : '',
            'email' => $user->email
        ];
        return $this->sendResponse('Get data success', $result);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        // check if employee is exist
        $employee = Employee::where('id', $id)->first();
        if (!$employee) {
            return $this->sendError('Data not found', [], 404);
        }

        // check if user employee is exist
        $user = User::where('id', $employee->user_id)->first();
        if (!$user) {
            return $this->sendError('User employee not found', [], 404);
        }

        // validate input
        $validator = Validator::make($request->all(), [
            'type' => ['required', Rule::in(['manager','employee','fellow'])],
            'name' => 'required',
            'phone' => ['required', 'min:8', 'max:15', 'unique:employees,phone,'.$employee->id],
            'email' => ['required', 'email', 'unique:users,email,'.$employee->user_id]
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 400);       
        }

        // check user permission
        if (
            !auth()->user()->hasPermissionTo('update.'.$request->type) || 
            $user->employee->company_id != auth()->user()->employee->company_id
        ) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $role = Role::where('name', $request->type)->first();
        if (!$role) {
            return $this->sendError('Role '.ucfirst($request->type).' not set up yet', [], 401);
        }

        DB::beginTransaction();
        try {
            $employee->name = $request->name;
            $employee->phone = $request->phone;
            $employee->address = $request->address ? $request->address : '';
            $employee->save();

            $user->email = $request->email;
            $user->save();
            $user->assignRole($role);

            DB::commit();
            $result = [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'address' => $employee->address,
                'position' => $employee->position
            ];
            return $this->sendResponse('Update employee data success', $result, 201);
        }catch(\Exception $e) {
            DB::rollback();
            return $this->sendError('Update employee data failed', [], 400);
        }
            
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $employee = Employee::where('id', $id)->first();
        if (!$employee) {
            return $this->sendError('Data not found', [], 404);
        }

        $user = User::where('id', $employee->user_id)->first();
        if (!$user) {
            return $this->sendError('Delete employee data failed');
        }

        $role = $user->getRoleNames();

        // check user permission
        if (!auth()->user()->hasPermissionTo('delete.'.$role[0])) {
            return $this->sendError("Sorry, you don't have access!");
        }

        if (!$employee->delete()) {
            return $this->sendError('Delete employee data failed');
        }
        return $this->sendResponse('Delete employee data success', [], 201);
    }

    /**
     * View self employee data
     */
    public function profile()
    {
        if (!auth()->user()->employee) {
            return $this->sendError('Data not found', [], 404);
        }

        $employee = auth()->user()->employee;
        $result = [
            'id' => $employee->id,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'email' => auth()->user()->email
        ];
        return $this->sendResponse('Get data success', $result);
    }

    /**
     * Update self employee data
     */
    public function updateSelfData(Request $request)
    {
        // check if user employee is exist
        $user = User::where('id', auth()->user()->id)->first();
        if (!$user) {
            return $this->sendError('User employee not found', [], 404);
        }

        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee) {
            return $this->sendError('Employee data not found', [], 404);
        }

        // validate input
        $rules = [
            'name' => 'required',
            'phone' => ['required', 'min:8', 'max:15', 'unique:employees,phone,'.$employee->id],
            'email' => ['required', 'email', 'unique:users,email,'.$employee->user_id]
        ];
        if ($request->password) {
            $rules['password'] = ['sometimes', Password::min(8)->letters()->mixedCase()->numbers()->symbols()];
        }
        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 400);       
        }

        DB::beginTransaction();
        try {
            $employee->name = $request->name;
            $employee->phone = $request->phone;
            $employee->address = $request->address ? $request->address : '';
            $employee->save();

            $user->email = $request->email;
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }

            DB::commit();

            $result = [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'address' => $employee->address,
                'email' => $user->email
            ];
            return $this->sendResponse('Update employee data success', $result);
        }catch(\Exception $e) {
            DB::rollback();
            return $this->sendError('Update employee data failed', [], 400);
        }  
    }
}
