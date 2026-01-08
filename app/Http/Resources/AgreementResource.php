<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgreementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $feeDetail = json_decode($this->fee_detail, true);

        return [
            'id' => $this->id,
            'credit_id' => $this->credit_id,

            // Información del crédito
            'sync_id' => $this->credit?->sync_id,

            // Información del convenio
            'total_amount' => $this->total_amount,
            'invoice_id' => $this->invoice_id,
            'total_fees' => $this->total_fees,
            'paid_fees' => $this->paid_fees,
            'pending_fees' => $this->total_fees - $this->paid_fees,
            'fee_amount' => $this->fee_amount,
            'fee_detail' => $feeDetail,

            // Estado
            'status' => $this->status,

            // Usuarios
            'created_by' => $this->creator?->name ?? null,
            'created_by_id' => $this->created_by,
            'updated_by' => $this->updater?->name ?? null,
            'updated_by_id' => $this->updated_by,

            // Información del cliente (TITULAR o primer cliente)
            'client_name' => $this->getClientName(),
            'client_ci' => $this->getClientCi(),

            // Fechas
            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
        ];
    }

    /**
     * Obtiene el nombre del cliente titular o el primer cliente disponible
     */
    private function getClientName(): ?string
    {
        if (!$this->credit) {
            return null;
        }

        // Buscar cliente titular
        $mainClient = $this->credit->clients()->wherePivot('type', 'TITULAR')->first();

        if ($mainClient) {
            return $mainClient->name;
        }

        // Si no hay titular, obtener el primer cliente
        $firstClient = $this->credit->clients()->first();

        return $firstClient?->name;
    }

    /**
     * Obtiene la cédula del cliente titular o el primer cliente disponible
     */
    private function getClientCi(): ?string
    {
        if (!$this->credit) {
            return null;
        }

        // Buscar cliente titular
        $mainClient = $this->credit->clients()->wherePivot('type', 'TITULAR')->first();

        if ($mainClient) {
            return $mainClient->ci;
        }

        // Si no hay titular, obtener el primer cliente
        $firstClient = $this->credit->clients()->first();

        return $firstClient?->ci;
    }
}
