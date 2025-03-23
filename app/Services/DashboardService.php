<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService {

    public static function getAllUsers() {

        $users = User::select(DB::raw('count(*) as count'))
            ->pluck('count');
        
        return $users;

    }

}