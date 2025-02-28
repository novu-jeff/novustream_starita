<?php

namespace App\Http\Controllers;

use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{

    public $roleTypeService;

    public function __construct(RoleService $roleTypeService) {

        $this->middleware(function ($request, $next) {
            
            if (!Gate::any(['admin'])) {
                abort(403, 'Unauthorized');
            }
    
            return $next($request);
        });

        $this->roleTypeService = $roleTypeService;
    }

    public function index() {

        $data = $this->roleTypeService::getData();

        if(request()->ajax()) {
            return $this->datatable($data);
        }

        return view('roles.index', $data);
    }

    // public function create() {
    //     return view('roles.form');
    // }

    // public function store(Request $request) {

    //     $payload = $request->all();

    //     $validator = Validator::make($payload, [
    //         'name' => 'required|unique:roles,name',
    //         'description' => 'nullable'
    //     ]);

    //     if($validator->fails()) {
    //         return redirect()->back()
    //             ->withErrors($validator)
    //             ->withInput();
    //     }

    //     $response = $this->roleTypeService::create($payload);

    //     if ($response['status'] === 'success') {
    //         return redirect()->back()->with('alert', [
    //             'status' => 'success',
    //             'message' => $response['message']
    //         ]);
    //     } else {
    //         return redirect()->back()->withInput()->with('alert', [
    //             'status' => 'error',
    //             'message' => $response['message']
    //         ]);
    //     }
    // }

    // public function edit(int $id) {

    //     $data = $this->roleTypeService::getData($id);
        
    //     return view('roles.form', compact('data'));
    // }

    // public function update(int $id, Request $request) {

    //     $payload = $request->all();

    //     $validator = Validator::make($payload, [
    //         'name' => 'required|unique:roles,name,' . $id,
    //         'description' => 'nullable'
    //     ]);

    //     if($validator->fails()) {
    //         return redirect()->back()
    //             ->withErrors($validator)
    //             ->withInput();
    //     }

    //     $response = $this->roleTypeService::update($id, $payload);

    //     if ($response['status'] === 'success') {
    //         return redirect()->back()->with('alert', [
    //             'status' => 'success',
    //             'message' => $response['message']
    //         ]);
    //     } else {
    //         return redirect()->back()->withInput()->with('alert', [
    //             'status' => 'error',
    //             'message' => $response['message']
    //         ]);
    //     }

    // }

    // public function destroy(int $id) {

    //     $response = $this->roleTypeService::delete($id);

    //     if ($response['status'] === 'success') {
            
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => $response['message']
    //         ]);
            
    //     } else {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $response['message']
    //         ]);
    //     }

    // }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->make(true);
    }
    
}
