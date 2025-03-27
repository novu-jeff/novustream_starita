<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileService {


    public static function getData() {

        $data = Auth::user();

        if ($data && isset($data->user_type)) {
            $data->user_type = $data->user_type;
        } else {
            $data->user_type = 'concesionnaire';
        }
    
        return $data;
    }

    public static function update(int $id, array $payload) {

        DB::beginTransaction();

        try {
        
            $updateData = [
                'name' => $payload['name'],
                'email' => $payload['email'],
            ];
                
            
            if(isset($payload['password'])) {
                $updateData['password'] = Hash::make($payload['password']);
            }

            if($payload['user_type'] == 'client') {
                User::where('id', $id)->update($updateData);
            } else {
                Admin::where('id', $id)->update($updateData);
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Profile ' . ' updated.'
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