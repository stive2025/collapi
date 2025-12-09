<?php

namespace Database\Seeders;

use App\Models\Credit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name'=>'Administrador',
            'username'=>'steven_cesen',
            'extension'=>"SIP/110",
            'permission'=>"[]",
            'password'=>Hash::make('Sefil2025@'),
            'role'=>'admin'
        ]);

        User::create([
            'name'=>'EN ESPERA',
            'username'=>'en_espera',
            'extension'=>"N/D",
            'permission'=>"[]",
            'password'=>Hash::make('Sefil2025@'),
            'role'=>'user'
        ]);

        $this->call([
            BusinessSeeder::class,
            CreditSeeder::class,
            CollectionDirectionSeeder::class,
        ]);
    }
}
