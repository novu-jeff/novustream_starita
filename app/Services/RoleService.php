<?php

namespace App\Services;

use App\Models\Roles;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleService {

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return Roles::where('id', $id)
                ->first() ?? null;
        }

        return Roles::all();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            Roles::create([
                'name' => $payload['name'],
                'description' => $payload['description'],
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Role ' . $payload['name'] . ' added.'
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
                'description' => $payload['description'],
            ];

            Roles::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Role ' . $payload['name'] . ' updated.'
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
            
            $data = Roles::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Role ' . $data['name']. ' deleted.'
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