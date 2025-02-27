<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientService {

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return User::where('user_type', 'client')
                ->where('id', $id)
                ->first() ?? null;
        }

        return User::where('user_type', 'client')->get();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            User::create([
                'user_type' => 'client',
                'firstname' => $payload['firstname'],
                'lastname' => $payload['lastname'],
                'middlename' => $payload['middlename'],
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'email' => $payload['email'],
                'isValidated' => $payload['isValidated'],
                'contract_no' => $payload['contract_no'],
                'contract_date' => $payload['contract_date'],
                'property_type' => $payload['property_type'],
                'meter_no' => $payload['meter_no'],
                'isValidated' =>  $payload['isValidated'] == 'true' ? true : false,
                'password' => $payload['password']
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Client ' . $payload['firstname'] . ' ' . $payload['lastname'] . ' added.'
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
                'firstname' => $payload['firstname'],
                'lastname' => $payload['lastname'],
                'middlename' => $payload['middlename'],
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'email' => $payload['email'],
                'contract_no' => $payload['contract_no'],
                'contract_date' => $payload['contract_date'],
                'property_type' => $payload['property_type'],
                'meter_no' => $payload['meter_no'],
                'isValidated' =>  $payload['isValidated'] == 'true' ? true : false
            ];

            if(isset($payload['password'])) {
                $updateData['password'] = Hash::make($payload['password']);
            }

            User::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Client ' . $payload['firstname'] . ' ' . $payload['lastname'] . ' updated.'
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
            
            $data = User::where('user_type', 'client')
                ->where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Client ' . $data['firstname'] . ' ' . $data['lastname'] . ' deleted.'
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