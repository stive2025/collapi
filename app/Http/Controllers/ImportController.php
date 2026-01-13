<?php

namespace App\Http\Controllers;

use App\Imports\ClientsImport;
use App\Imports\ContactsImport;
use App\Imports\PaymentsImport;
use Illuminate\Http\Request;
use App\Imports\CreditsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    /**
     * Importación de créditos desde archivo Excel.
     */
    public function importCredits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación del archivo.',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            Excel::import(new CreditsImport, $request->file('file'));

            return response()->json([
                'message' => 'Importación completada correctamente.'
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'message' => 'Errores en la validación de datos.',
                'failures' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado durante la importación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importación de créditos desde archivo Excel.
     */
    public function importClients(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación del archivo.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Excel::import(new ClientsImport, $request->file('file'));

            return response()->json([
                'message' => 'Importación completada correctamente.'
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'message' => 'Errores en la validación de datos.',
                'failures' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado durante la importación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importación de contactos desde archivo Excel.
     */
    public function importContacts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación del archivo.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            Excel::import(new ContactsImport, $request->file('file'));

            return response()->json([
                'message' => 'Importación completada correctamente.'
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'message' => 'Errores en la validación de datos.',
                'failures' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado durante la importación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Importación de pagos desde archivo Excel.
     */
    public function importPayments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'business_id' => 'required|integer|exists:businesses,id',
            'campain_id' => 'nullable|integer|exists:campains,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación del archivo.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $businessId = $request->business_id;
            $campainId = $request->campain_id;

            $import = new PaymentsImport($businessId, $campainId);
            Excel::import($import, $request->file('file'));

            $skippedDetails = $import->getSkippedDetails();
            $errorSumPayments = $import->getErrorSumPayments();
            $message = 'Importación completada correctamente.';

            if (!empty($skippedDetails)) {
                $message .= ' Se omitieron ' . count($skippedDetails) . ' pago(s).';
            }

            if (!empty($errorSumPayments)) {
                $message .= ' Se marcaron ' . count($errorSumPayments) . ' pago(s) con ERROR_SUM.';
            }

            return response()->json([
                'message' => $message,
                'imported' => $import->getImportedCount(),
                'skipped' => $import->getSkippedCount(),
                'total' => $import->getImportedCount() + $import->getSkippedCount(),
                'skipped_details' => $skippedDetails,
                'error_sum_count' => count($errorSumPayments),
                'error_sum_payments' => $errorSumPayments
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            return response()->json([
                'message' => 'Errores en la validación de datos.',
                'failures' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado durante la importación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}