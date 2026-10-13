<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SmsDemoController extends Controller
{
    public function sendDemoSms(Request $request)
    {
        $mobile = "8655915295";
        $otp = (string) $request->input('otp', (string) rand(100000, 999999));
        $minutes = (int) $request->input('minutes', 5);
        $minutes = $minutes > 0 ? $minutes : 5;
        $number = $this->normalizeMobile($mobile);

        try {
            $settings = $this->getSmsGatewaySettings();
            if ($settings === []) {
                return response()->json([
                    'success' => false,
                    'error' => true,
                    'message' => 'Active SMS settings not found.',
                    'data' => null,
                ], 422);
            }

            $message = sprintf(
                'Dear Customer, your OTP to complete registration on Tizzy Group is %s. It is valid for %d minutes. Do not share this OTP with anyone. - Tizzy Group',
                $otp,
                $minutes
            );

            $query = [
                'user' => $settings['user'],
                'password' => $settings['password'],
                'senderid' => $settings['senderid'],
                'channel' => $settings['channel'],
                'DCS' => $settings['dcs'],
                'flashsms' => 0,
                'number' => $number,
                'text' => $message,
                'route' => $settings['route'],
                'PEId' => $settings['peid'],
                'DLTTemplateId' => $settings['template_id'],
            ];

            $response = Http::timeout(20)->get($settings['base_url'], $query);
            $body = (string) $response->body();
            $bodyLower = strtolower($body);
            $sent = $response->successful()
                && !str_contains($bodyLower, 'error')
                && !str_contains($bodyLower, 'invalid')
                && !str_contains($bodyLower, 'failed');

            return response()->json([
                'success' => $sent,
                'error' => !$sent,
                'message' => $sent ? 'SMS sent successfully.' : 'SMS request failed.',
                'data' => [
                    'number' => $number,
                    'otp' => $otp,
                    'gateway_response' => $body,
                ],
            ], $sent ? 200 : 502);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => true,
                'message' => 'Demo SMS sending failed.',
                'data' => $e->getMessage(),
            ], 500);
        }
    }

    private function getSmsGatewaySettings(): array
    {
        try {
            $row = SmsObject::query()
                ->where('provider', 'jmd_sms')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return [];
        }

        if (!$row) {
            return [];
        }

        return [
            'base_url' => (string) ($row->base_url ?? ''),
            'user' => (string) ($row->user ?? ''),
            'password' => (string) ($row->password ?? ''),
            'senderid' => (string) ($row->senderid ?? ''),
            'channel' => (string) ($row->channel ?? ''),
            'dcs' => (string) ($row->dcs ?? ''),
            'route' => (string) ($row->route ?? ''),
            'peid' => (string) ($row->peid ?? ''),
            'template_id' => (string) ($row->template_id ?? ''),
        ];
    }

    private function normalizeMobile(?string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10) {
            return '91' . $digits;
        }

        return $digits;
    }
}
