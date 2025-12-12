<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WhatsAppWebhookController extends Controller
{
    protected $verificationToken;

    // Constructor to initialize the hub verification token
    public function __construct()
    {
        $this->verificationToken = 'my-verification-token'; // Your custom verification token
    }

    // Handle the webhook verification and the actual data reception
    public function webhook(Request $request)
    {
        // Facebook/WhatsApp webhook verification process
        if ($request->has('hub_verify_token') && $request->input('hub_verify_token') === $this->verificationToken) {
            return response($request->input('hub_challenge'), 200);
        }

        // Log the webhook data for debugging
        Log::info('WhatsApp Webhook Data: ' . json_encode($request->all()));

        try {
            // Extract relevant data from the webhook
            $messageId = $request->input('entry.0.changes.0.value.statuses.0.id');
            $status = $request->input('entry.0.changes.0.value.statuses.0.status');
            $timestamp = $request->input('entry.0.changes.0.value.statuses.0.timestamp');
            $callbackData = $request->input('entry.0.changes.0.value.statuses.0.biz_opaque_callback_data', null);
            $recipientId = $request->input('entry.0.changes.0.value.statuses.0.recipient_id');
            $dump = json_encode($request->all());

            // Store the webhook data in the database
            DB::table('cloud_response')->insert([
                'msg_id' => $messageId,
                'status' => $status,
                'timestamp' => $timestamp,
                'callback_data' => $callbackData,
                'recipient_id' => $recipientId,
                'dump' => $dump,
                'errors' => null,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

            return response('Webhook received', 200);

        } catch (\Exception $e) {
            // Log the error and store the error details in the database
            Log::error('Error processing webhook: ' . $e->getMessage());

            DB::table('cloud_response')->insert([
                'msg_id' => null,
                'status' => 'failed',
                'timestamp' => null,
                'callback_data' => null,
                'recipient_id' => null,
                'dump' => json_encode($request->all()), // Store the full webhook data
                'errors' => $e->getMessage(), // Store the error message
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);

            return response('Error processing webhook', 500);
        }
    }
}
