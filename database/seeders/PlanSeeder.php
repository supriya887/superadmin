<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Plan::updateOrCreate(
            ['slug' => 'basic-plan'],
            [
                'name' => 'Basic Plan',
                'storage' => '10 GB',
                'price' => 792,
                'is_active' => true,
            ]
        );

        \App\Models\Plan::updateOrCreate(
            ['slug' => 'silver-plan'],
            [
                'name' => 'Silver Plan',
                'storage' => '30 GB',
                'price' => 1716,
                'is_active' => true,
            ]
        );

        \App\Models\Plan::updateOrCreate(
            ['slug' => 'corporate-plan'],
            [
                'name' => 'Corporate Plan',
                'storage' => '1 TB',
                'price' => 8000,
                'is_active' => true,
            ]
        );
    }
}
