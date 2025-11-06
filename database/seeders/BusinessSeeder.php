<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Business::create([
            'name'=>'SEFIL_1',
            'state'=>'ACTIVE',
            'prelation_order'=>'[]'
        ]);

        Business::create([
            'name'=>'SEFIL_2',
            'state'=>'ACTIVE',
            'prelation_order'=>'[]'
        ]);

        Business::create([
            'name'=>'FACES',
            'state'=>'ACTIVE',
            'prelation_order'=>'[]'
        ]);
    }
}
