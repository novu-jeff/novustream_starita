<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientService {

    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return User::where('id', $id)
                ->first() ?? null;
        }

        return User::all();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            User::create([
                'name' => $payload['name'],
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'property_type' => $payload['property_type'],
                'rate_code' => $payload['rate_code'],
                'status' => $payload['status'],
                'sc_no' => $payload['sc_no'],
                'meter_brand' => $payload['meter_brand'],
                'meter_serial_no' => $payload['meter_serial_no'],
                'date_connected' => $payload['date_connected'],
                'sequence_no' => $payload['sequence_no'],
                'email' => $payload['email'],
                'password' => Hash::make($payload['password'])
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Client ' . $payload['name'] . ' added.'
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
                'address' => $payload['address'],
                'contact_no' => $payload['contact_no'],
                'property_type' => $payload['property_type'],
                'rate_code' => $payload['rate_code'],
                'status' => $payload['status'],
                'sc_no' => $payload['sc_no'],
                'meter_brand' => $payload['meter_brand'],
                'meter_serial_no' => $payload['meter_serial_no'],
                'date_connected' => $payload['date_connected'],
                'sequence_no' => $payload['sequence_no'],
                'email' => $payload['email']
            ];

            if(isset($payload['password'])) {
                $updateData['password'] = Hash::make($payload['password']);
            }

            User::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Client ' . $payload['name'] . ' updated.'
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