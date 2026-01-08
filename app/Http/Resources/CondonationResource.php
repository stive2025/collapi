<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CondonationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prevDates = json_decode($this->prev_dates, true);
        $postDates = json_decode($this->post_dates, true);

        return [
            'id' => $this->id,
            'credit_id' => $this->credit_id,

            // Información del crédito
            'sync_id' => $this->credit?->sync_id,

            // Monto total condonado
            'amount' => $this->amount,

            // Desglose de valores condonados
            'capital' => ($prevDates['capital'] ?? 0) - ($postDates['capital'] ?? 0),
            'interest' => ($prevDates['interest'] ?? 0) - ($postDates['interest'] ?? 0),
            'mora' => ($prevDates['mora'] ?? 0) - ($postDates['mora'] ?? 0),
            'safe' => ($prevDates['safe'] ?? 0) - ($postDates['safe'] ?? 0),
            'management_collection_expenses' => ($prevDates['management_collection_expenses'] ?? 0) - ($postDates['management_collection_expenses'] ?? 0),
            'collection_expenses' => ($prevDates['collection_expenses'] ?? 0) - ($postDates['collection_expenses'] ?? 0),
            'legal_expenses' => ($prevDates['legal_expenses'] ?? 0) - ($postDates['legal_expenses'] ?? 0),
            'other_values' => ($prevDates['other_values'] ?? 0) - ($postDates['other_values'] ?? 0),

            // Estado
            'status' => $this->status,

            // Usuario que creó la condonación
            'created_by' => $this->creator?->name ?? null,
            'created_by_id' => $this->created_by,

            // Información del cliente (TITULAR o primer cliente)
            'client_name' => $this->getClientName(),
            'client_ci' => $this->getClientCi(),

            // Fechas
            'created_at' => $this->created_at?->format('Y/m/d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y/m/d H:i:s'),
            'reverted_at' => $this->reverted_at?->format('Y/m/d H:i:s'),
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
