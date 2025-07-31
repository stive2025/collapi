<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name'=>'STEVEN CESEN',
            'username'=>'steven_cesen',
            'extension'=>"SIP/110",
            'permission'=>"[]",
            'password'=>Hash::make('Sefil2025@'),
            'role'=>'admin'
        ]);
    }
}
