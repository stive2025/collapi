<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'ci' => $this->faker->unique()->numerify('##########'),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'civil_status' => $this->faker->randomElement(['soltero', 'casado', 'divorciado', 'viudo', 'union_libre']),
            'economic_activity' => $this->faker->randomElement([
                'Empleado publico',
                'Empleado privado',
                'Independiente',
                'Comerciante',
                'Profesional',
                'Agricultor',
                'Estudiante',
                'Ama de casa',
                'Jubilado'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
