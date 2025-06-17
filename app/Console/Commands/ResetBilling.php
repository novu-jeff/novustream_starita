<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Reading;

class ResetBilling extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset-billing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        DB::table('bill_breakdown')->delete(); 
        DB::table('bill_discount')->delete(); 
        DB::table('bill')->delete();          
        DB::table('readings')->delete();     
    }
}
