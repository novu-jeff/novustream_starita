<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService {


    public static function getAllUsers() {

        $users = User::select('user_type', DB::raw('count(*) as count'))
            ->groupBy('user_type')
            ->pluck('count', 'user_type');
        
        return $users;

    }


}