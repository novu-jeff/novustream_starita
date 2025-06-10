<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccounts;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientService {

    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return User::where('id', $id)
                ->with('accounts.sc_discount', 'accounts.property_types')
                ->first() ?? null;
        }

        return User::with('accounts.property_types')
            ->withCount('accounts')
            ->where('isActive', true)
            ->get();
    }

    public static function create(array $payload) {

        $accounts = $payload['accounts'];

        $user = User::create([
            'name' => $payload['name'],
            'pwd_no' => $payload['pwd_no'],
            'email' => $payload['email'],
            'contact_no' => $payload['contact_no'],
            'password' => Hash::make($payload['password'])
        ]);

        foreach ($accounts as $account) {

            $path = 'inspection_images/' . $account['account_no'];
                        
            if (isset($account['inspectionImage']) && $account['inspectionImage'] instanceof UploadedFile) {
                $extension = $account['inspectionImage']->getClientOriginalExtension();
                $date = Carbon::now()->timestamp;
                $fileName = "inspection_image_{$date}.{$extension}";
        
                $account['inspectionImage']->storeAs($path, $fileName, 'public');
        
                $inspectionImage = $fileName; 
            }

             UserAccounts::create([
                'user_id'  =>  $user->id,
                'account_no'  =>  $account['account_no'],
                'address' => $account['address'],
                'property_type'  =>  $account['property_type'],
                'rate_code'  =>  $account['rate_code'],
                'status'  =>  $account['status'],
                'sc_no'  =>  $account['sc_no'],
                'meter_brand'  =>  $account['meter_brand'],
                'meter_serial_no'  =>  $account['meter_serial_no'],
                'date_connected'  =>  $account['date_connected'],
                'sequence_no'  =>  $account['sequence_no'],
                'inspection_image'  => $inspectionImage ?? null,
            ]);
        }

        return $user;
    }

    public static function update(array $payload, int $id) {

        $accounts = $payload['accounts'];

        $user = User::with('accounts')->where('isActive', true)->findOrFail($id);

        $updateData = [
            'name' => $payload['name'],
            'pwd_no' => $payload['pwd_no'],
            'email' => $payload['email'],
            'contact_no' => $payload['contact_no'],
        ];

        if (!empty($payload['password'])) {
            $updateData['password'] = Hash::make($payload['password']);
        }

        $user->update($updateData);

        foreach ($accounts as $account) {

            $path = 'inspection_images/' . $account['account_no'];
            
            $savedInspection = $user->accounts->where('account_no', $account['account_no'])->first()->inspection_image ?? null;

            if (isset($account['inspectionImage']) && $account['inspectionImage'] instanceof UploadedFile) {
                $extension = $account['inspectionImage']->getClientOriginalExtension();
                $date = Carbon::now()->timestamp;
                $fileName = "inspection_image_{$date}.{$extension}";
        
                $account['inspectionImage']->storeAs($path, $fileName, 'public');
        
                $inspectionImage = $fileName; 

                UserAccounts::updateOrCreate(
                    ['id' => $account['id'] ?? null],
                    [
                        'inspection_image' => $inspectionImage        
                    ]
                );
            } 

            UserAccounts::updateOrCreate(
                ['id' => $account['id'] ?? null],
                [
                    'user_id'  => $user->id,
                    'account_no'  => $account['account_no'],
                    'address' => $account['address'],
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