<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminService {

    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return Admin::where('id', $id)
                ->first() ?? null;
        }
        return Admin::all();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            Admin::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'user_type' => $payload['role'],
                'password' => $payload['password']
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
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }

    public static function update(int $id, array $payload) {

        DB::beginTransaction();

        try {
            
            $updateData = [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'user_type' => $payload['role'],
            ];

            if(isset($payload['password'])) {
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
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }

    public static function delete(int $id) {

        DB::beginTransaction();

        try {
            
            $data = Admin::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Admin ' . $data['name'] . ' deleted.'
            ];

        } catch (\Exception $e) {
            
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }


}