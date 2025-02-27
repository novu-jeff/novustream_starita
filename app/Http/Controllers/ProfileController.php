<?php

namespace App\Http\Controllers;

use App\Services\ProfileService;
use App\Services\PropertyTypesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
   
    public $profileService;
    public $propertyTypesService;

    public function __construct(ProfileService $profileService, PropertyTypesService $propertyTypesService) {
        $this->profileService = $profileService;
        $this->propertyTypesService = $propertyTypesService;
    }

    public function index() {

        $user_id = Auth::user()->id;

        $property_types = $this->propertyTypesService::getData();
        $data = $this->profileService::getData($user_id);

        return view('profile.index', compact('data', 'property_types'));
    
    }

    public function update(int $id, Request $request) {

        $payload = $request->all();

        $data = $this->profileService::getData($id);

        $user_type = $data->user_type;

        $origin = request()->route()->getName() == 'profile.update' ? 'profile' : '';

        if($user_type != 'client') {
            $validator = Validator::make($payload, [
                'firstname' => 'required',
                'lastname' => 'required',
                'middlename' => 'nullable',
                'address' => 'required',
                'contact_no' => 'required',
                'email' => [
                    'required',
                        Rule::unique('users')->ignore($id),
                    ],
                'password' => 'nullable|min:8|required_with:confirm_password',
                'confirm_password' => 'nullable|same:password|required_with:password',
            ]);
        } else {            

            $validator = Validator::make($payload, [
                'firstname' => 'required',
                'lastname' => 'required',
                'middlename' => 'nullable',
                'address' => 'required',
                'contact_no' => 'required',
                'email' => [
                    'required',
                    Rule::unique('users', 'email')->ignore($id),
                ],
                'password' => 'nullable|min:8|required_with:confirm_password',
                'confirm_password' => 'nullable|same:password|required_with:password',
            ]);

        }

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $payload['origin'] = $origin;

        $response = $this->profileService::update($id, $payload);

        if ($response['status'] === 'success') {
            return redirect()->back()->with('alert', [
                'status' => 'success',
                'message' => $response['message']
            ]);
        } else {
            return redirect()->back()->withInput()->with('alert', [
                'status' => 'error',
                'message' => $response['message']
            ]);
        }

    }
    
}
