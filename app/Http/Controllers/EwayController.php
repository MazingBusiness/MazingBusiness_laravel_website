<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ZohoSetting;
use App\Models\ZohoPaymentToken;

class EwayController extends Controller
{
    /**
     * Step 1: Redirect to Zoho Payments OAuth
     */
    public function redirectToZohoPayments()
    {
        $settings = ZohoSetting::where('status', 2)->firstOrFail(); // status 2 = Zoho Payments

        $accountId = '60043647184'; // Example: 843xxxxxxx (without zohopay.)
        
        $authUrl = "https://accounts.zoho.in/oauth/v2/org/auth?" . http_build_query([
            'scope'         => 'ZohoPay.payments.CREATE',
            'client_id'     => $settings->client_id,
            'soid'          => 'zohopay.' . $accountId,
            'state'         => 'generate_payment_token',
            'response_type' => 'code',
            'redirect_uri'  => $settings->redirect_uri,
            'access_type'   => 'offline'
        ]);

        return redirect()->away($authUrl);
    }

    /**
     * Step 2: Handle Callback and Store Tokens
     */
    public function handleZohoPaymentsCallback(Request $request)
    {
        if (!$request->has('code')) {
            return response()->json(['error' => 'Authorization code missing']);
        }

        $code = $request->input('code');
        $settings = ZohoSetting::where('status', 2)->firstOrFail();

        $response = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => $settings->client_id,
            'client_secret' => $settings->client_secret,
            'redirect_uri'  => $settings->redirect_uri,
            'code'          => $code,
        ])->json();

        if (isset($response['access_token'])) {
            ZohoPaymentToken::truncate(); // store only latest

            ZohoPaymentToken::create([
                'access_token'  => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'expires_at'    => now()->addSeconds($response['expires_in']),
            ]);

            return response()->json(['message' => 'Zoho Payment token stored successfully']);
        }

        return response()->json(['error' => 'Failed to get Zoho Payment token', 'response' => $response]);
    }

    /**
     * Step 3: Get Auth Headers for Zoho Payment APIs (Auto Refresh)
     */
    private function getZohoPaymentAuthHeaders()
    {
        $token = ZohoPaymentToken::first();
        if (!$token) {
            abort(403, 'Zoho Payment token not found.');
        }

        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $settings = ZohoSetting::where('status', 2)->firstOrFail();

            $refresh = Http::asForm()->post('https://accounts.zoho.in/oauth/v2/token', [
                'grant_type'    => 'refresh_token',
                'client_id'     => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'refresh_token' => $token->refresh_token,
            ])->json();

            if (isset($refresh['access_token'])) {
                $token->update([
                    'access_token' => $refresh['access_token'],
                    'expires_at'   => now()->addSeconds($refresh['expires_in']),
                ]);
            } else {
                abort(403, 'Failed to refresh Zoho Payment token.');
            }
        }

        return [
            'Authorization' => 'Zoho-oauthtoken ' . $token->access_token,
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Example: Call Zoho Payment API
     */
    public function listZohoPayments()
    {
        $headers = $this->getZohoPaymentAuthHeaders();
        $accountId = '60043647184'; // e.g., 843xxxxxxx

        $url = "https://payments.zoho.in/api/v1/accounts/zohopay.{$accountId}/payments";

        $response = Http::withHeaders($headers)->get($url);

        return $response->json();
    }
}
