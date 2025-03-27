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

    public function update(string $user_type, int $id, Request $request) {

        $payload = $request->all();

        $data = $this->profileService::getData($id);

        if($data->user_type == 'concessionaire') {
            $payload['user_type'] = 'concessionaire';
        } else {
            $payload['user_type'] = 'admin';
        }

        $validator = Validator::make($payload, [
            'name' => 'required',
            'email' => [
                'required',
                    Rule::unique('users')->ignore($id),
                ],
            'password' => 'nullable|min:8|required_with:confirm_password',
            'confirm_password' => 'nullable|same:password|required_with:password',
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

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
