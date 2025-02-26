<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService {

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return User::where('user_type', '!=', 'client')
                ->where('id', $id)
                ->first() ?? null;
        }

        return User::where('user_type', '!=', 'client')->get();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            User::create([
                'user_type' => $payload['role'],
                'firstname' => $payload['firstname'],
                'lastname' => $payload['lastname'],
                'middlename' => $payload['middlename'],
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'email' => $payload['email'],
                'password' => $payload['password']
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'User ' . $payload['firstname'] . ' ' . $payload['lastname'] . ' added.'
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
                'user_type' => $payload['role'],
                'firstname' => $payload['firstname'],
                'lastname' => $payload['lastname'],
                'middlename' => $payload['middlename'],
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'email' => $payload['email'],
                'password' => $payload['password']
            ];

            if(isset($payload['password'])) {
                $updateData['password'] = Hash::make($payload['password']);
            }

            User::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'User ' . $payload['firstname'] . ' ' . $payload['lastname'] . ' updated.'
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
            
            $data = User::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'User ' . $data['firstname'] . ' ' . $data['lastname'] . ' deleted.'
            ];

        } catch (\Exception $e) {
            
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Erro occured: ' . $e->getMessage()
            ];
        }

    }


}