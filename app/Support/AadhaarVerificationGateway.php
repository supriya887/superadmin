<?php

namespace App\Support;

use App\Services\SandboxAadhaarService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AadhaarVerificationGateway
{
    /**
     * @return array{ok: bool, message: string, masked: string, ref_no: string, mode: string}
     */
    public function init(string $aadhaarNumber, string $contextKey): array
    {
        $aadhaar = preg_replace('/\D/', '', $aadhaarNumber) ?? '';
        if (strlen($aadhaar) !== 12) {
            return $this->failInit('Aadhaar number must be 12 digits.');
        }

        if ($this->isTestMode()) {
            $refNo = 'AADH-' . strtoupper(Str::random(8));
            $sessionId = $this->sessionIdFromContext($contextKey);
            if ($sessionId > 0) {
                Cache::put('aadhaar_ref_' . $sessionId, $refNo, now()->addMinutes(30));
                Cache::put('aadhaar_number_' . $sessionId, $aadhaar, now()->addMinutes(30));
            }

            return [
                'ok' => true,
                'message' => 'Aadhaar init successful (test mode). Use any 6-digit OTP e.g. 123456.',
                'masked' => 'XXXXXXXX' . substr($aadhaar, -4),
                'ref_no' => $refNo,
                'mode' => 'test',
            ];
        }

        $sessionId = $this->sessionIdFromContext($contextKey);
        if ($sessionId <= 0) {
            return $this->failInit('Invalid verification session.');
        }

        $sandbox = app(SandboxAadhaarService::class);
        $result = $sandbox->generateOtp($aadhaar, $sessionId);
        if (empty($result['success'])) {
            return $this->failInit((string) ($result['message'] ?? 'Failed to send OTP.'));
        }

        $refNo = (string) ($result['reference_id'] ?? Cache::get('aadhaar_ref_' . $sessionId) ?? '');
        if ($refNo === '') {
            return $this->failInit((string) ($result['message'] ?? 'OTP provider did not return a reference id. Please try again.'));
        }

        return [
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'OTP sent successfully.'),
            'masked' => $sandbox->maskAadhaar($aadhaar),
            'ref_no' => $refNo,
            'mode' => (string) ($result['mode'] ?? $sandbox->getMode()),
        ];
    }

    /**
     * @return array{ok: bool, message: string, mode: string}
     */
    public function verifyOtp(string $aadhaarNumber, string $otp, string $contextKey, string $refNo = ''): array
    {
        $otp = trim($otp);
        if ($otp === '' || strlen($otp) !== 6 || !ctype_digit($otp)) {
            return $this->failVerify('Please enter a valid 6-digit OTP.');
        }

        if ($this->isTestMode()) {
            $otp = preg_replace('/\D/', '', $otp) ?? '';
            if (strlen($otp) !== 6) {
                $otp = (string) (config('services.aadhaar.mock_otps')[0] ?? '123456');
            }

            return [
                'ok' => true,
                'message' => 'Aadhaar verified successfully (test mode).',
                'mode' => 'test',
            ];
        }

        $sessionId = $this->sessionIdFromContext($contextKey);
        if ($sessionId <= 0) {
            return $this->failVerify('Invalid verification session.');
        }

        if (!Cache::has('aadhaar_ref_' . $sessionId) && $refNo === '') {
            return $this->failVerify('Session expired. Please send OTP again.');
        }

        $sandbox = app(SandboxAadhaarService::class);
        $result = $sandbox->verifyOtp($sessionId, $otp, 1, $refNo);
        if (empty($result['success'])) {
            return $this->failVerify((string) ($result['message'] ?? 'OTP verification failed.'));
        }

        return [
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'Aadhaar verified successfully.'),
            'mode' => (string) ($sandbox->getMode()),
        ];
    }

    public function isTestMode(): bool
    {
        return strtolower(trim((string) config('services.aadhaar.mode', 'live'))) !== 'live';
    }

    private function sessionIdFromContext(string $contextKey): int
    {
        if (preg_match('/:(\d+)$/', $contextKey, $matches)) {
            return (int) $matches[1];
        }

        return (int) $contextKey;
    }

    /**
     * @return array{ok: bool, message: string, masked: string, ref_no: string, mode: string}
     */
    private function failInit(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'masked' => '',
            'ref_no' => '',
            'mode' => $this->isTestMode() ? 'test' : 'live',
        ];
    }

    /**
     * @return array{ok: bool, message: string, mode: string}
     */
    private function failVerify(string $message): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'mode' => $this->isTestMode() ? 'test' : 'live',
        ];
    }
}
