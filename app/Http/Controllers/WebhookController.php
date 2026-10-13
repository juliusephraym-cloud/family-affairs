<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function nextsms(Request $request, NotificationService $notifications): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING);
        if (! is_array($payload)) {
            $payload = $request->all();
        }
        $result = $notifications->handleWebhook($payload);

        // 200 even when ignored so the provider does not retry forever; 401 for a bad token.
        return response()->json(['result' => $result], $result === 'rejected' ? 401 : 200);
    }
}
