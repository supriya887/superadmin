<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class WhatsAppDemoController extends Controller
{
    /**
     * List WhatsApp templates from JMD (for Postman / local checks).
     *
     * GET|POST /api/v1/whatsapp/templates
     */
    public function listTemplates(WhatsAppService $whatsApp): JsonResponse
    {
        $result = $whatsApp->listTemplates();

        $templates = is_array($result['message'] ?? null) ? $result['message'] : [];

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'error' => !(bool) ($result['ok'] ?? false),
            'message' => ($result['ok'] ?? false)
                ? 'WhatsApp templates fetched successfully.'
                : (is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Failed to fetch WhatsApp templates.'),
            'data' => [
                'phone_number_id' => config('services.jmd_whatsapp.phone_number_id'),
                'configured_templates' => config('services.jmd_whatsapp.templates'),
                'templates' => $templates,
                'jmd_status' => $result['status'] ?? null,
            ],
        ], ($result['ok'] ?? false) ? 200 : 502);
    }
}
