<?php

namespace App\Imports;

use App\Models\Credit;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Validation\Rule;

class CreditsImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Credit::create([
                'sync_id'                           =>  $row['sync_id'],
                'due_date'                          =>  $row['due_date'],
                'days_past_due'                     =>  (int) $row['days_past_due'],
                'total_fees'                        =>  (float) $row['total_fees'],
                'paid_fees'                         =>  (int) $row['paid_fees'],
                'payment_date'                      =>  $this->parseDate($row['payment_date']),
                'pending_fees'                      =>  (int) $row['pending_fees'],
                'monthly_fee_amount'                =>  (float) $row['monthly_fee_amount'],
                'total_amount'                      =>  (float) $row['total_amount'],
                'capital'                           =>  (float) $row['capital'],
                'interest'                          =>  (float) $row['interest'],
                'mora'                              =>  (float) $row['mora'],
                'safe'                              =>  (float) $row['safe'],
                'management_collection_expenses'    =>  (float) $row['management_collection_expenses'],
                'collection_expenses'               =>  (float) $row['collection_expenses'],
                'legal_expenses'                    =>  (float) $row['legal_expenses'],
                'other_values'                      =>  (float) $row['other_values'],
                'sync_status'                       =>  $row['sync_status'],
                'last_sync_date'                    =>  $this->parseDate($row['last_sync_date']),
                'management_status'                 =>  $row['management_status'],
                'management_tray'                   =>  $row['management_tray'],
                'management_promise'                =>  $this->parseDate($row['management_promise']),
                'date_offer'                        =>  $row['date_offer'],
                'date_promise'                      =>  $row['date_promise'],
                'date_notification'                 =>  $row['date_notification'],
                'user_id'                           =>  (int) $row['user_id'],
                'business_id'                       =>  (int) $row['business_id'],
                'agency'                            =>  $row['agency'],
                'frequency'                         =>  $row['frequency'],
                'award_date'                        =>  $row['award_date'],
                'collection_state'                 =>  $row['collection_state'],
            ]);
        }
    }

    /**
     * Reglas de validación por campo
     */
    public function rules(): array
    {
        return [
            'sync_id'                        => ['required', 'string'],
            'due_date'                       => ['required', 'date'],
            'days_past_due'                  => ['required', 'integer'],
            'total_fees'                     => ['required', 'numeric'],
            'paid_fees'                      => ['required', 'integer'],
            'payment_date'                   => ['nullable', 'date'],
            'pending_fees'                   => ['required', 'integer'],
            // 'monthly_fee_amount'             => ['required', 'numeric'],
            // 'total_amount'                   => ['required', 'numeric'],
            // 'capital'                        => ['required', 'numeric'],
            // 'interest'                       => ['required', 'numeric'],
            // 'mora'                           => ['required', 'numeric'],
            // 'safe'                           => ['required', 'numeric'],
            // 'management_collection_expenses' => ['required', 'numeric'],
            // 'collection_expenses'            => ['required', 'numeric'],
            // 'legal_expenses'                 => ['required', 'numeric'],
            // 'other_values'                   => ['required', 'numeric'],
            'sync_status'                    => ['required', 'string'],
            'last_sync_date'                 => ['nullable', 'date'],
            'management_status'              => ['nullable', 'string'],
            'management_tray'                => ['nullable', 'string'],
            'management_promise'             => ['nullable', 'date'],
            'date_offer'                     => ['nullable', 'string'],
            'date_promise'                   => ['nullable', 'string'],
            'date_notification'              => ['nullable', 'string'],
            'user_id'                        => ['required', 'integer', 'exists:users,id'],
            'business_id'                    => ['required', 'integer', 'exists:businesses,id'],
        ];
    }

    /**
     * Personaliza los mensajes de error
     */
    public function customValidationMessages()
    {
        return [
            'sync_id.required' => 'El campo sync_id es obligatorio.',
            'due_date.date' => 'El campo due_date debe ser una fecha válida.',
            'payment_date.date' => 'El campo due_date debe ser una fecha válida.',
            'user_id.exists' => 'El usuario no existe.',
            'business_id.exists' => 'El negocio no existe.'
        ];
    }

    /**
     * Convierte fechas en formato Excel a Y-m-d
     */
    private function parseDate($value)
    {
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        if ($value) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}