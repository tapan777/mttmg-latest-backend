<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names=[
            '1 month',
            '3 months',
            '6 months',
            '12 months',
            'PT + 1month',
            'admission + monthly',
        ];
        
        foreach($names as $name){
            $amount= rand(600,3000);
            Package::create([
                'name' => $name,
                'duration' => 28,
                'package_amount' => $amount,
                'admission_value' => $amount+500
            ]);
        }
    }
}
