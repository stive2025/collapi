<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResponseBase;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SmsController extends Controller
{
    private SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Verifica si ya se envió un SMS a un cliente en una campaña y crédito específicos
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSms(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client_id' => 'required|integer',
                'credit_id' => 'required|integer',
                'campain_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return ResponseBase::validationError($validator->errors()->toArray());
            }

            $clientId = $request->input('client_id');
            $creditId = $request->input('credit_id');
            $campainId = $request->input('campain_id');

            $messageExists = DB::table('management')
                ->where('client_id', $clientId)
                ->where('credit_id', $creditId)
                ->where('campain_id', $campainId)
                ->where('substate', 'MENSAJE DE TEXTO')
                ->exists();

            if ($messageExists) {
                return ResponseBase::error(
                    'Ya se envió un mensaje de texto dentro de esta campaña a este cliente en este crédito',
                    ['puede_enviar' => false],
                    400
                );
            }

            return ResponseBase::success(
                ['puede_enviar' => true],
                'No se ha enviado ningún mensaje. Puede proceder',
                200
            );

        } catch (\Exception $e) {
            Log::error('Error verificando SMS', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al verificar el envío de SMS',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Envía un SMS
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSms(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string',
                'cod_sms' => 'required|integer|in:43334,43335,48392',
                'client_id' => 'required|integer',
                'credit_id' => 'required|integer',
                'campain_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return ResponseBase::validationError($validator->errors()->toArray());
            }

            $clientId = $request->input('client_id');
            $creditId = $request->input('credit_id');
            $campainId = $request->input('campain_id');

            // Verificar si ya se envió un SMS a este cliente en esta campaña
            $messageExists = DB::table('management')
                ->where('client_id', $clientId)
                ->where('credit_id', $creditId)
                ->where('campain_id', $campainId)
                ->where('substate', 'MENSAJE DE TEXTO')
                ->exists();

            if ($messageExists) {
                return ResponseBase::error(
                    'Ya se envió un mensaje de texto dentro de esta campaña a este cliente en este crédito',
                    ['puede_enviar' => false],
                    400
                );
            }

            $codSms = (int) $request->input('cod_sms');
            $phone = $request->input('phone');

            // Preparar los datos según el código del mensaje
            $data = $this->prepareMessageData($codSms, $request);

            // Enviar el SMS
            $result = $this->smsService->sendSms($phone, $codSms, $data);

            if ($result['success']) {
                return ResponseBase::success(
                    $result['response'],
                    'SMS enviado correctamente',
                    200
                );
            } else {
                return ResponseBase::error(
                    $result['error'],
                    null,
                    $result['code']
                );
            }

        } catch (\Exception $e) {
            Log::error('Error enviando SMS', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ResponseBase::error(
                'Error al enviar el SMS',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * Prepara los datos del mensaje según el código
     *
     * @param int $codSms
     * @param Request $request
     * @return array
     */
    private function prepareMessageData(int $codSms, Request $request): array
    {
        switch ($codSms) {
            case 43334:
                // Mensaje de confirmación de pago
                return [
                    'name' => $request->input('name'),
                    'payment_date' => $request->input('payment_date'),
                    'total_amount' => $request->input('total_amount')
                ];

            case 43335:
                // Mensaje de recordatorio de mora
                return [
                    'name' => $request->input('name'),
                    'days_past_due' => $request->input('days_past_due'),
                    'total_amount' => $request->input('total_amount')
                ];

            case 48392:
                // Mensaje de información de contacto
                return [
                    'name' => $request->input('name'),
                    'total_amount' => $request->input('total_amount'),
                    'phone_contact' => $request->input('phone_contact')
                ];

            default:
                return [];
        }
    }
}
