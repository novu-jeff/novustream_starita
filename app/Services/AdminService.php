<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminService {

    public static function getData(?int $id = null) {
        if(!is_null($id)) {
            return Admin::find($id);
        }
        return Admin::all();
    }

    public static function create(array $payload) {

        // Validate input
        $validator = Validator::make($payload, [
            'name' => 'required|string',
            'email' => 'required|email|unique:admins,email',
            'role' => 'required|string',
            'password' => 'required|string|min:8',
            'confirm_password' => 'required|string|same:password',
            'zone_assigned' => 'nullable|array',
            'zone_assigned.*' => 'exists:zones,id', // each item must exist
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        DB::beginTransaction();

        try {
            $zoneAssigned = isset($payload['zone_assigned']) ? implode(',', $payload['zone_assigned']) : null;

            Admin::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'user_type' => $payload['role'],
                'zone_assigned' => $zoneAssigned,
                'password' => Hash::make($payload['password'])
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Admin ' . $payload['name'] . ' added.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Error occurred: ' . $e->getMessage()
            ];
        }
    }

    public static function update(int $id, array $payload) {

        // Validate input
        $validator = Validator::make($payload, [
            'name' => 'required|string',
            'email' => ['required','email', Rule::unique('admins','email')->ignore($id)],
            'role' => 'required|string',
            'password' => 'nullable|string|min:8|required_with:confirm_password',
            'confirm_password' => 'nullable|string|same:password|required_with:password',
            'zone_assigned' => 'nullable|array',
            'zone_assigned.*' => 'exists:zones,id',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ];
        }

        DB::beginTransaction();

        try {
            $zoneAssigned = isset($payload['zone_assigned']) ? implode(',', $payload['zone_assigned']) : null;

            $updateData = [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'user_type' => $payload['role'],
                'zone_assigned' => $zoneAssigned,
            ];

            if(!empty($payload['password'])) {
                $updateData['password'] = Hash::make($payload['password']);
            }

            Admin::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Admin ' . $payload['name'] . ' updated.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Error occurred: ' . $e->getMessage()
            ];
        }
    }

    public static function delete(int $id) {
        DB::beginTransaction();

        try {
            $admin = Admin::findOrFail($id);
            $admin->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Admin ' . $admin->name . ' deleted.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'status' => 'error',
                'message' => 'Error occurred: ' . $e->getMessage()
            ];
        }
    }

}
