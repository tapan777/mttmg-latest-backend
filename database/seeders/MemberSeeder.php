<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i <= 15; $i++) {
            Member::create([
                'membership_number' => $faker->unique()->randomNumber(5),
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'alternate_phone' => $faker->phoneNumber,
                'image' => $faker->imageUrl($width = 200, $height = 200),
                'height' => $faker->randomDigit(),
                'weight' => $faker->randomDigit(),
                'sex' => $faker->titleMale($i % 2 == 0 ? 'male' : 'female'),
                'dob' => $faker->date($format = 'Y-m-d', $max = '2000-01-01'),
                'occupation' => $faker->optional()->jobTitle,
                'address' => $faker->address,
                'status' => 1,
                'identification_type' => "adhar",
                'identification_id' => "123456789874",

            ]);
        }
    }
}
