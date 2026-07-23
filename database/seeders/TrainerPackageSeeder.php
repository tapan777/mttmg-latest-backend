<?php

namespace Database\Seeders;

use App\Models\TrainerPackage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TrainerPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $names=[
            'Dibya Sir',
            'Hitesh Sir',
            'Guddu Sir',
            'Bbaul Sir'
        ];
        
        foreach($names as $name){
            $amount= rand(600,3000);
            TrainerPackage::create([
                'name' => $name,
                'duration' => 28,
                'package_amount' => $amount
            ]);
        }
    }
}
