<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlutterwaveWebhookController extends Controller
{
    public function __construct(
        private FlutterwaveService $flutterwaveService
    ) {}

    public function handle(Request $request)
    {
        // Get webhook signature from header
        $signature = $request->header('verif-hash');
        
        if (!$signature) {
            Log::warning('Flutterwave webhook received without signature');
            return response()->json(['error' => 'Signature missing'], 400);
        }
        
        // Get raw payload
        $payload = $request->getContent();
        
        // Verify signature
        if (!$this->flutterwaveService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Flutterwave webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        // Process webhook
        $result = $this->flutterwaveService->processWebhook($request->json()->all());
        
        if ($result['success']) {
            Log::info('Flutterwave webhook processed successfully', [
                'event_type' => $request->input('event'),
                'result' => $result,
            ]);
            
            return response()->json(['status' => 'success'], 200);
        } else {
            Log::error('Flutterwave webhook processing failed', [
                'event_type' => $request->input('event'),
                'result' => $result,
            ]);
            
            return response()->json(['error' => $result['message']], 500);
        }
    }
}