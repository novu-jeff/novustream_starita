<?php

namespace App\Services;

use App\Models\PropertyTypes;
use Illuminate\Support\Facades\DB;

class PropertyTypesService {

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return PropertyTypes::where('id', $id)
                ->first() ?? null;
        }

        return PropertyTypes::get();

    }

    public static function create(array $payload) {

        DB::beginTransaction();

        try {
            
            PropertyTypes::create($payload);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Property Type ' . $payload['name'] . ' added.'
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
            
            PropertyTypes::where('id', $id)->update([
                'rate_code' => $payload['rate_code'],
                'name' => $payload['name'],
                'description' => $payload['description']
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Property Type ' . $payload['name'] . ' updated.'
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
            
            
            $data = PropertyTypes::where('id', $id)->first();
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Property Type ' . $data['name'] . ' deleted.'
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