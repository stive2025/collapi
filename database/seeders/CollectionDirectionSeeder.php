<?php
namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CollectionDirectionSeeder extends Seeder
{
    private array $locations = [
        'Pichincha' => ['Quito', 'Cayambe', 'Mejía', 'Pedro Moncayo', 'Rumiñahui', 'San Miguel de los Bancos'],
        'Guayas' => ['Guayaquil', 'Durán', 'Samborondón', 'Daule', 'Milagro', 'Naranjal'],
        'Azuay' => ['Cuenca', 'Gualaceo', 'Paute', 'Sigsig', 'Santa Isabel'],
        'Manabí' => ['Portoviejo', 'Manta', 'Chone', 'Jipijapa', 'Montecristi'],
        'El Oro' => ['Machala', 'Pasaje', 'Huaquillas', 'Santa Rosa', 'El Guabo'],
        'Tungurahua' => ['Ambato', 'Baños', 'Pelileo', 'Píllaro'],
        'Los Ríos' => ['Babahoyo', 'Quevedo', 'Ventanas', 'Vinces'],
        'Imbabura' => ['Ibarra', 'Otavalo', 'Cotacachi', 'Atuntaqui'],
    ];

    private array $neighborhoods = [
        'Centro Histórico', 'Norte', 'Sur', 'La Mariscal', 'La Carolina',
        'Kennedy', 'Urdesa', 'Alborada', 'Garzota', 'Cdla. del Maestro',
        'Los Ceibos', 'San Marino', 'Villa España', 'Socio Vivienda',
        'El Condado', 'Cotocollao', 'San Rafael', 'La Magdalena',
        'Carapungo', 'Calderón', 'El Inca', 'Quitumbe'
    ];

    private array $directionTypes = ['DOMICILIO', 'TRABAJO', 'REFERENCIA'];
    
    private array $streetPrefixes = [
        'Av.', 'Calle', 'Pasaje', 'Callejón', 'Jr.', 'Transversal'
    ];

    private array $streetNames = [
        '10 de Agosto', 'América', 'Amazonas', 'Colón', 'Patria',
        'Los Shyris', 'Naciones Unidas', 'República', 'Eloy Alfaro',
        '6 de Diciembre', 'Occidental', 'Mariscal Sucre', 'Simón Bolívar',
        'García Moreno', 'Juan León Mera', 'Reina Victoria', 'Cordero'
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Iniciando creación de direcciones...');

        $clients = Client::all();

        if ($clients->isEmpty()) {
            $this->command->warn('No hay clientes disponibles. Ejecuta ClientSeeder primero.');
            return;
        }

        $directions = [];
        $batchSize = 500;
        $totalDirections = 0;

        foreach ($clients as $client) {
            $numDirections = rand(1, 3);
            $province = array_rand($this->locations);
            $canton = $this->locations[$province][array_rand($this->locations[$province])];

            for ($i = 0; $i < $numDirections; $i++) {
                $type = $this->directionTypes[$i % count($this->directionTypes)];
                
                $latitude = number_format(rand(-500, 200) / 100, 6, '.', '');
                $longitude = number_format(rand(-8100, -7500) / 100, 6, '.', '');

                $directions[] = [
                    'client_id' => $client->id,
                    'direction' => $this->generateAddress(),
                    'type' => $type,
                    'province' => $province,
                    'canton' => $canton,
                    'parish' => $this->generateParish($canton),
                    'neighborhood' => $this->neighborhoods[array_rand($this->neighborhoods)],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($directions) >= $batchSize) {
                    DB::table('collection_directions')->insert($directions);
                    $totalDirections += count($directions);
                    $this->command->info("  ✓ {$totalDirections} direcciones creadas...");
                    $directions = [];
                }
            }
        }

        if (!empty($directions)) {
            DB::table('collection_directions')->insert($directions);
            $totalDirections += count($directions);
        }

        $this->command->info("✓ Total: {$totalDirections} direcciones creadas exitosamente.");
        $this->command->info("✓ Promedio: " . round($totalDirections / $clients->count(), 1) . " direcciones por cliente.");
    }

    /**
     * Genera una dirección aleatoria realista
     */
    private function generateAddress(): string
    {
        $prefix = $this->streetPrefixes[array_rand($this->streetPrefixes)];
        $street = $this->streetNames[array_rand($this->streetNames)];
        $number = rand(100, 9999);
        $complement = ['', ' y ' . $this->streetNames[array_rand($this->streetNames)], ' Oe' . rand(1, 99)][rand(0, 2)];
        
        return "{$prefix} {$street} {$number}{$complement}";
    }

    /**
     * Genera una parroquia basada en el cantón
     */
    private function generateParish(string $canton): string
    {
        $parishes = [
            'Quito' => ['Iñaquito', 'Rumipamba', 'Kennedy', 'Cotocollao', 'Comité del Pueblo', 'Carcelén'],
            'Guayaquil' => ['Tarqui', 'Ximena', 'Rocafuerte', 'Olmedo', 'Febres Cordero'],
            'Cuenca' => ['El Sagrario', 'El Batán', 'Yanuncay', 'Bellavista', 'Totoracocha'],
        ];

        if (isset($parishes[$canton])) {
            return $parishes[$canton][array_rand($parishes[$canton])];
        }

        return 'Centro';
    }
}