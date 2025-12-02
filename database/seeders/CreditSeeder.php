<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Credit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Inicio proceso de creación de créditos.');

        Credit::factory()
            ->count(2000)
            ->create();

        $this->command->info('✓ 2000 créditos creados exitosamente.');
        $this->command->info('✓ Iniciando asociación de créditos con clientes.');

        $clients = Client::all();

        if ($clients->isEmpty()) {
            $this->command->warn('No hay clientes disponibles.');
            return;
        }

        $credits = Credit::all();
        $relations = [];

        foreach ($credits as $credit) {
            $titular = $clients->random();
            $relations[] = [
                'client_id' => $titular->id,
                'credit_id' => $credit->id,
                'type' => 'TITULAR',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (rand(1, 100) <= 50) {
                $availableClients = $clients->where('id', '!=', $titular->id);
                $numGarantes = rand(1, 2);
                $garantes = $availableClients->random(min($numGarantes, $availableClients->count()));

                foreach ($garantes as $garante) {
                    $relations[] = [
                        'client_id' => $garante->id,
                        'credit_id' => $credit->id,
                        'type' => 'GARANTE',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (count($relations) >= 500) {
                DB::table('client_credit')->insert($relations);
                $relations = [];
            }
        }

        if (!empty($relations)) {
            DB::table('client_credit')->insert($relations);
        }

        $this->command->info('✓ Créditos asociados exitosamente con clientes (TITULAR/GARANTE).');
    }
}