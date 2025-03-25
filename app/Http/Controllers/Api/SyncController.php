<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeterService;

class SyncController extends Controller
{

    public $meterService;

    public function __construct(MeterService $meterService) {
        $this->meterService = $meterService;
    }

    public function sync() {
        
        $data = $this->meterService::getBills() ?? [];

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
        
    }
}
