<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Calculation\Database\DVar;
use Zxing\QrReader;

class InspectionController extends Controller
{

    public function search(Request $request) {

        $validator = Validator::make($request->all(), [
            'option' => 'required|string|in:qr_code,input,upload_image',
            'content' => 'required'
        ], [
            'option.reqiorequired' => 'Option is required',
            'option.string' => 'Option must be a string',
            'option.in' => 'Invalid option selected',
        ]);

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => $validator->errors()
            ], 400);
        }

        $payloads = $request->all();

        $option = $payloads['option'];
        $content = $payloads['content'];

        switch ($option) {
            case 'qr_code':
                $toSearch = $this->decodeQrCodeFile($content);
                break;

            case 'input':
                $toSearch = $this->searchByInput($content);
                break;

            case 'upload_image':
                return response()->json([
                    'status' => 'error',
                    'message' => 'Upload image option is not yet implemented'
                ], 501);

            default:
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid option selected'
                ], 400);
        }

        return $this->find($toSearch);

    }

    private function decodeQrCodeFile($filePath) {
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            $qrcode = new QrReader($filePath);
            $decodedText = $qrcode->text();

            if (!$decodedText) {
                return response()->json(['error' => 'Unable to decode QR code'], 400);
            }

            return trim($decodedText);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while decoding QR code', 'details' => $e->getMessage()], 500);
        }
    }

    private function searchByInput($input) {
        return trim($input);
    }

    private function find($toSearch) {
        $data = User::where('account_no', $toSearch)
            ->orWhere('meter_serial_no', $toSearch)
            ->first();

        if(!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'No record found'
            ], 404);
        } 

        return response()->json([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    public function update(Request $request) {

        $validator = Validator::make($request->all(), [
            'account_no' => 'required|string',
            'meter_brand' => 'required|string',
            'meter_serial_no' => 'required|string',
            'meter_type' => 'required|string',
            'meter_wire' => 'required|string',
            'meter_form' => 'required|string',
            'meter_class' => 'required|string',
            'lat_long' => 'required|string',
            'isErcSealed' => 'required|in:true,false',
            'inspection_image' => 'required|image|mimes:jpeg,png,jpg',
        ]);

        if($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'error' => $validator->errors()
            ], 400);
        }   

        $payloads = $request->all();
        

        DB::beginTransaction();

        try {
            
            User::where('account_no', $payloads['account_no'])
                ->update([
                    'meter_brand' => $payloads['meter_brand'],
                    'meter_serial_no' => $payloads['meter_serial_no'],
                    'meter_type' => $payloads['meter_type'],
                    'meter_wire' => $payloads['meter_wire'],
                    'meter_form' => $payloads['meter_form'],
                    'meter_class' => $payloads['meter_class'],
                    'lat_long' => $payloads['lat_long'],
                    'isErcSealed' => $payloads['isErcSealed'] == 'true' ? true : false,
                    'inspection_image' => $request->file('inspection_image')->store('inspection_images', 'public'),
                ]);
            
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'record updated successfully'
            ], 200);

        } catch (\Exception $e) {
            
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }

    }
}
