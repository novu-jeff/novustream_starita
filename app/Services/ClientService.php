<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientService {

    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return User::where('id', $id)->with('accounts')
                ->first() ?? null;
        }

        return User::with('accounts')->withCount('accounts')->where('isActive', true)->get();
    }

    public static function create(array $payload) {

        $accounts = $payload['accounts'];

        $user = User::create([
            'name' => $payload['name'],
            'address' => $payload['address'],
            'email' => $payload['email'],
            'contact_no' => $payload['contact_no'],
            'password' => Hash::make($payload['password'])
        ]);

        foreach ($accounts as $account) {
             UserAccounts::create([
                'user_id'  =>  $user->id,
                'account_no'  =>  $account['account_no'],
                'property_type'  =>  $account['property_type'],
                'rate_code'  =>  $account['rate_code'],
                'status'  =>  $account['status'],
                'sc_no'  =>  $account['sc_no'],
                'meter_brand'  =>  $account['meter_brand'],
                'meter_serial_no'  =>  $account['meter_serial_no'],
                'date_connected'  =>  $account['date_connected'],
                'sequence_no'  =>  $account['sequence_no'],
            ]);
        }

        return $user;
    }

    public static function update(array $payload, int $id) {

        $accounts = $payload['accounts'];

        $user = User::where('isActive', true)->findOrFail($id);

        $updateData = [
            'name' => $payload['name'],
            'address' => $payload['address'],
            'email' => $payload['email'],
            'contact_no' => $payload['contact_no'],
        ];

        if (!empty($payload['password'])) {
            $updateData['password'] = Hash::make($payload['password']);
        }

        $user->update($updateData);

        // Loop through accounts and update or create
        foreach ($accounts as $account) {
            UserAccounts::updateOrCreate(
                ['id' => $account['id'] ?? null],
                [
                    'user_id'  => $user->id,
                    'account_no'  => $account['account_no'],
                    'property_type'  => $account['property_type'],
                    'rate_code'  => $account['rate_code'],
                    'status'  => $account['status'],
                    'sc_no'  => $account['sc_no'],
                    'meter_brand'  => $account['meter_brand'],
                    'meter_serial_no'  => $account['meter_serial_no'],
                    'date_connected'  => $account['date_connected'],
                    'sequence_no'  => $account['sequence_no'],
                ]
            );
        }

        return $user->load('accounts'); 
    }

    public static function delete(int $id) {

        DB::beginTransaction();

        try {
            
            $data = User::where('id', $id)->first();
                
            $data->isActive = false;

            $data->save();

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