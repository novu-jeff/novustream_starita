<?php

namespace App\Services;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class DashboardService {

    public static function getAllUsers() {

        $users = User::count();
        $admins = Admin::where('user_type', '!=', 'technician')->count();
        $technician = Admin::where('user_type', 'technician')->count();

        return [
            'admins' => $admins,
            'technicians' => $technician,
            'concessionaires' => $users,
        ];

    }

}