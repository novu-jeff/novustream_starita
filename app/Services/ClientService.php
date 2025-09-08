<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAccounts;
use App\Models\StatusCode;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\PropertyTypes;

class ClientService {

    public static function getData($id = null, $zone = null, $search = null)
    {

        if (!is_null($id)) {
            return User::with(['accounts.sc_discount', 'accounts.property_types'])
                ->where('id', $id)
                ->first();
        }

        $isAllZone = $zone === 'all';

        return User::with(['accounts.property_types'])
            ->withCount('accounts')
            ->where('isActive', true)
            ->when(!empty($zone) && !$isAllZone, function ($query) use ($zone) {
                $query->whereHas('accounts', function ($subQuery) use ($zone) {
                    $subQuery->where('zone', 'like', '%' . $zone . '%');
                });
            })
            ->when(!empty($search['value']) && !empty($search['parameter']), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    foreach ($search['parameter'] as $param) {
                        if (in_array($param, ['name'])) {
                            $q->orWhere($param, 'like', '%' . $search['value'] . '%');
                        } elseif (in_array($param, ['account_no', 'address'])) {
                            $q->orWhereHas('accounts', function ($subQ) use ($param, $search) {
                                $subQ->where($param, 'like', '%' . $search['value'] . '%');
                            });
                        }
                    }
                });
            })
            ->get();
    }

    public static function getStatusCode()
    {
        return StatusCode::select('name', 'code')->get();
    }

    public static function create(array $payload) {
    $accounts = $payload['accounts'];
    $user = User::create([
        'name'       => $payload['name'],
        'email'      => $payload['email'],
        'contact_no' => $payload['contact_no'],
        'password'   => Hash::make($payload['password'])
    ]);

    foreach ($accounts as $account) {
        $propertyTypeName = null;
        if (!empty($account['property_type'])) {
            $propertyTypeName = PropertyTypes::where('id', $account['property_type'])
                                ->value('name');
        }
        $zone = $account['zone'] ?? self::getZoneFromAddress(strtoupper(trim($account['address'])));
        UserAccounts::updateOrCreate(
            ['id' => $account['id'] ?? null],
            [
                'user_id'        => $user->id,
                'zone'           => $zone,
                'account_no'     => $account['account_no'],
                'address'        => $account['address'],
                'property_type'  => $propertyTypeName,
                'rate_code'      => $account['rate_code'],
                'status'         => $account['status'],
                'sc_no'          => $account['sc_no'],
                'meter_brand'    => $account['meter_brand'],
                'meter_serial_no'=> $account['meter_serial_no'],
                'date_connected' => $account['date_connected'],
                'sequence_no'    => $account['sequence_no'],
                'meter_type'     => $account['meter_type'] ?? null,
                'meter_wire'     => $account['meter_wire'] ?? null,
                'meter_form'     => $account['meter_form'] ?? null,
                'meter_class'    => $account['meter_class'] ?? null,
                'lat_long'       => $account['lat_long'] ?? null,
                'isErcSealed'    => $account['isErcSealed'] ?? 0,
            ]
        );
    }
    return $user;
    }


        public static function update(array $payload, int $id) {
        $accounts = $payload['accounts'];
        $user = User::with('accounts')->where('isActive', true)->findOrFail($id);
        $updateData = [
            'name'       => $payload['name'],
            'email'      => $payload['email'],
            'contact_no' => $payload['contact_no'],
        ];

        if (!empty($payload['password'])) {
            $updateData['password'] = Hash::make($payload['password']);
        }

        $user->update($updateData);

        foreach ($accounts as $account) {
            $propertyTypeName = null;
            if (!empty($account['property_type'])) {
                $propertyTypeName = PropertyTypes::where('id', $account['property_type'])
                                    ->value('name');
            }

            $zone = $account['zone'] ?? self::getZoneFromAddress(strtoupper(trim($account['address'])));

            UserAccounts::updateOrCreate(
                ['id' => $account['id'] ?? null],
                [
                    'user_id'        => $user->id,
                    'zone'           => $zone,
                    'account_no'     => $account['account_no'],
                    'address'        => $account['address'],
                    'property_type'  => $propertyTypeName,
                    'rate_code'      => $account['rate_code'],
                    'status'         => $account['status'],
                    'sc_no'          => $account['sc_no'],
                    'meter_brand'    => $account['meter_brand'],
                    'meter_serial_no'=> $account['meter_serial_no'],
                    'date_connected' => $account['date_connected'],
                    'sequence_no'    => $account['sequence_no'],
                    'meter_type'     => $account['meter_type'] ?? null,
                    'meter_wire'     => $account['meter_wire'] ?? null,
                    'meter_form'     => $account['meter_form'] ?? null,
                    'meter_class'    => $account['meter_class'] ?? null,
                    'lat_long'       => $account['lat_long'] ?? null,
                    'isErcSealed'    => $account['isErcSealed'] ?? 0,
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

    private static function getZoneFromAddress($address)
    {
        $zones = [
            ['zone' => '101', 'area' => 'CABAMBANGAN'],
            ['zone' => '201', 'area' => 'SAN VICENTE'],
            ['zone' => '301', 'area' => 'STA. INES'],
            ['zone' => '302', 'area' => 'SAN GUILLELRMO'],
            ['zone' => '303', 'area' => 'TINAJERO'],
            ['zone' => '401', 'area' => 'CABETICAN'],
            ['zone' => '501', 'area' => 'STA. BARBARA'],
            ['zone' => '601', 'area' => 'CABALANTIAN'],
            ['zone' => '602', 'area' => 'WESTVILLE'],
            ['zone' => '701', 'area' => 'SAN ISIDRO'],
            ['zone' => '702', 'area' => 'SOLANDA'],
            ['zone' => '801', 'area' => 'BANLIC'],
            ['zone' => '802', 'area' => 'SPLENDEROSA-LA HACIENDA'],
            ['zone' => '803', 'area' => 'LA TIERRA'],
            ['zone' => '804', 'area' => 'MACABACLE'],
            ['zone' => '805', 'area' => 'PARULOG'],
            ['zone' => '806', 'area' => 'SAN ANTONIO'],
        ];

        foreach ($zones as $zone) {
            if (stripos($address, $zone['area']) !== false) {
                return $zone['zone'];
            }
        }

        return null;
    }



}