<?php

namespace Database\Factories;

use App\Models\Credit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credit>
 */
class CreditFactory extends Factory
{
    protected $model = Credit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = $this->faker->randomFloat(2, 5000, 50000);
        $capital = $totalAmount * 0.7;
        $interest = $totalAmount * 0.2;
        $totalFees = $this->faker->numberBetween(12, 60);
        $paidFees = $this->faker->numberBetween(0, $totalFees - 1);
        $pendingFees = $totalFees - $paidFees;
        $monthlyFeeAmount = $totalAmount / $totalFees;
        $awardDate = $this->faker->dateTimeBetween('-2 years', '-6 months');
        $dueDate = (clone $awardDate)->modify('+' . $totalFees . ' months');
        $daysPastDue = max(0, now()->diffInDays($dueDate, false));

        return [
            'sync_id' => $this->faker->unique()->numerify('SYNC-########'),
            'agency' => $this->faker->randomElement(['Agencia Centro', 'Agencia Norte', 'Agencia Sur', 'Agencia Este']),
            'collection_state' => $this->faker->randomElement(['vigente', 'vencido', 'judicial', 'castigado']),
            'frequency' => $this->faker->randomElement(['mensual', 'quincenal', 'semanal']),
            'payment_date' => $this->faker->numberBetween(1, 28),
            'award_date' => $awardDate,
            'due_date' => $dueDate,
            'days_past_due' => $daysPastDue,
            'total_fees' => $totalFees,
            'paid_fees' => $paidFees,
            'pending_fees' => $pendingFees,
            'monthly_fee_amount' => round($monthlyFeeAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'capital' => round($capital, 2),
            'interest' => round($interest, 2),
            'mora' => $this->faker->randomFloat(2, 0, 500),
            'safe' => $this->faker->randomFloat(2, 50, 200),
            'management_collection_expenses' => $this->faker->randomFloat(2, 0, 300),
            'collection_expenses' => $this->faker->randomFloat(2, 0, 500),
            'legal_expenses' => $this->faker->randomFloat(2, 0, 1000),
            'other_values' => $this->faker->randomFloat(2, 0, 200),
            'sync_status' => $this->faker->randomElement(['ACTIVE', 'INACTIVE']),
            'last_sync_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'management_status' => $this->faker->randomElement(['NO CONTESTA', 'MENSAJE DE WHATSAPP', 'MENSAJE DE TEXTO', 'OFERTA DE PAGO', 'YA PAGO']),
            'management_tray' => $this->faker->randomElement(['PENDIENTE', 'EN PROCESO', 'GESTIONADO']),
            'management_promise' => $this->faker->optional(0.3)->dateTimeBetween('now', '+30 days'),
            'date_offer' => $this->faker->optional(0.4)->dateTimeBetween('-15 days', 'now'),
            'date_promise' => $this->faker->optional(0.3)->dateTimeBetween('now', '+30 days'),
            'date_notification' => $this->faker->optional(0.5)->dateTimeBetween('-60 days', 'now'),
            'user_id' => 1,
            'business_id' => $this->faker->randomElement([1, 2, 3]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
