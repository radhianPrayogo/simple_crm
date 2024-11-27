<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanyController extends BaseController
{
    protected $permission_tag;

    public function __construct()
    {
        $this->permission_tag = 'company';
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // check user permission
        if (!auth()->user()->hasPermissionTo('view.'.$this->permission_tag)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $page = $request->has('page') ? $request->page : 1;
        $per_page = $request->has('per_page') ? $request->per_page : 10;

        $offset = ($page - 1) * $per_page;
        $limit = $per_page;

        $companies = Company::select('id','name','email','phone')
                            ->offset($offset)
                            ->limit($limit)
                            ->get();
        $total = Company::count();

        $result = [
            'page' => $page,
            'total' => $total,
            'data' => $companies
        ];
        return $this->sendResponse('Get data success', $result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create(Request $request): JsonResponse
    {
        // check user permission
        if (!auth()->user()->hasPermissionTo('create.'.$this->permission_tag)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'company_email' => ['required', 'email', 'unique:companies,email'],
            'company_phone' => ['required', 'min:8', 'max:15'],
            'manager_name' => 'required',
            'manager_email' => ['required', 'email', 'unique:users,email'],
            'manager_phone' => ['required', 'unique:employees,phone', 'min:8', 'max:15'],
            'manager_password' => [
                                    'required', 
                                    Password::min(8)->letters()->mixedCase()->numbers()->symbols()
                                ]
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 400);       
        }

        // check if role is exist
        $role = Role::where('name', 'manager')->first();
        if (!$role) {
            return $this->sendError('Role Manager not set up yet', [], 401);
        }

        DB::beginTransaction();
        try {
            // save company data
            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->company_email,
                'phone' => $request->company_phone
            ]);

            // save manager account data
            $manager = User::create([
                'email' => $request->manager_email,
                'password' => Hash::make($request->manager_password)
            ]);

            // assign role to user manager
            $manager->assignRole($role);

            // create employee data
            $employee = Employee::create([
                'user_id' => $manager->id,
                'company_id' => $company->id,
                'name' => $request->manager_name,
                'phone' => $request->manager_phone,
                'address' => $request->manager_address ? $request->manager_address : ''
            ]);

            DB::commit();
            return $this->sendResponse('Save company data success', [], 201);
        } catch (\Exeption $e) {
            DB::rollback();
            return $this->SendError('Save company manager data failed', [], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        // check user permission
        if (!auth()->user()->hasPermissionTo('view.'.$this->permission_tag)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $company = Company::where('id', $id)->first();

        return $this->sendResponse('Get data success', $company);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        // check user permission
        if (!auth()->user()->hasPermissionTo('update.'.$this->permission_tag)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $validator = Validator::make($request->all(), [
            'company_name' => 'required',
            'company_email' => ['required', 'email', 'unique:companies,email,'.$id],
            'company_phone' => ['required', 'min:8', 'max:15']
        ]);
   
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(), 400);       
        }

        $company = Company::where('id', $id)->first();
        if (!$company) {
            return $this->sendError('Data not found', [], 404);
        }

        $company->name = $request->company_name;
        $company->email = $request->company_email;
        $company->phone = $request->company_phone;

        if (!$company->save()) {
            return $this->sendError('Update company data failed', [], 400);
        }

        $result = [
            'id' => $company->id,
            'name' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone
        ];
        return $this->sendResponse('Update company data success', $result, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        // check user permission
        if (!auth()->user()->hasPermissionTo('delete.'.$this->permission_tag)) {
            return $this->sendError("Sorry, you don't have access!");
        }

        $company = Company::where('id', $id)->first();
        if (!$company) {
            return $this->sendError('Data not found', [], 404);
        }

        if (!$company->delete()) {
            return $this->sendError('Delete data failed', [], 400);
        }

        return $this->sendResponse('Delete data success');
    }
}
