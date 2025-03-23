<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Zxing\QrReader;

class ReprintController extends Controller
{
    
    public $meterService;

    public function __construct(MeterService $meterService)  {
        $this->meterService = $meterService;
    }

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
        return $this->meterService::getBills($toSearch, true);
    }

    public function view(string $reference_no) {
        return $this->meterService::getBill($reference_no);
    }

}
