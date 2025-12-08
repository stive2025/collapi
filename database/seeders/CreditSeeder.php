<?php
// filepath: c:\xampp\htdocs\collapi\database\seeders\CreditSeeder.php

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
        $this->command->info('✓ Creando pool inicial de clientes.');
        
        $poolSize = 1000;
        $clients = collect();
        
        for ($i = 0; $i < $poolSize; $i++) {
            $clients->push(Client::factory()->create());
        }

        $this->command->info("✓ {$poolSize} clientes creados en el pool inicial.");
        $this->command->info('✓ Iniciando asociación de créditos con clientes.');

        $credits = Credit::all();
        $relations = [];
        $processedCount = 0;
        $newClientsCreated = 0;

        foreach ($credits as $credit) {
            if (rand(1, 100) <= 70 && $clients->count() > 0) {
                $titular = $clients->random();
            } else {
                $titular = Client::factory()->create();
                $clients->push($titular);
                $newClientsCreated++;
            }
            
            $relations[] = [
                'client_id' => $titular->id,
                'credit_id' => $credit->id,
                'type' => 'TITULAR',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (rand(1, 100) <= 50) {
                $numGarantes = rand(1, 2);
                
                for ($i = 0; $i < $numGarantes; $i++) {
                    if (rand(1, 100) <= 80 && $clients->count() > 1) {
                        $availableClients = $clients->where('id', '!=', $titular->id);
                        
                        if ($availableClients->count() > 0) {
                            $garante = $availableClients->random();
                        } else {
                            $garante = Client::factory()->create();
                            $clients->push($garante);
                            $newClientsCreated++;
                        }
                    } else {
                        $garante = Client::factory()->create();
                        $clients->push($garante);
                        $newClientsCreated++;
                    }
                    
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
                $processedCount += count($relations);
                $this->command->info("  ✓ Procesadas {$processedCount} relaciones | Pool de {$clients->count()} clientes...");
                $relations = [];
            }
        }

        if (!empty($relations)) {
            DB::table('client_credit')->insert($relations);
            $processedCount += count($relations);
        }

        $totalClients = $poolSize + $newClientsCreated;
        $this->command->info("✓ Total de {$totalClients} clientes ({$poolSize} pool inicial + {$newClientsCreated} nuevos).");
        $this->command->info("✓ Total de {$processedCount} relaciones (TITULAR/GARANTE) creadas.");
        $this->command->info('✓ Los clientes pueden tener múltiples roles en diferentes créditos.');
        $this->command->info('✓ Proceso completado exitosamente.');
    }
}