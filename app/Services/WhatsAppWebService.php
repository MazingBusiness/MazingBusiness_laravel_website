<?php 


namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WhatsAppWebService
{
    protected $apiUrl;
    protected $accessToken;
    protected $phoneNumberId;

    public function __construct()
    {
        $this->phoneNumberId = '147572895113819'; // Set your phone number ID here
        $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
        $this->accessToken = 'EAAPUmpU2NucBO8we70yBZAOMH2J8dW1ZCKMZBFCOVey6muS6seQuoRg4BZAtZCcqqIddK1MUmZC62G0xbFmXA2tDvobSuhV3StEcPK1PbLGQQE8kpJwnZBj5uFDLELlwBli7MBwlrSkD3Vbyn6VNEAQ0PpcItd7qDhO1opb45uAhmZADqjEC3aAeaaSZC0a0knyxb';
    }

    public function sendTemplateMessage($to, $templateData)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->accessToken}",
        ])->post($this->apiUrl, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'biz_opaque_callback_data'=>$templateData['name'],
            'template' => [
                'name' => $templateData['name'],
                'language' => [
                    'code' => $templateData['language'],
                ],
                 // 'components' => "",
                 'components' => $templateData['components'] ?? [],
            ],
        ]);

        if ($response->failed()) {
            return ['error' => 'HTTP Error: ' . $response];
        }

        return $response->json();
    }


    public function uploadMedia($fileUrl)
    {
        $fileUrl = str_replace(' ', '%20', $fileUrl);
        // Fetch the file content from the external URL
        try {
            $fileContents = file_get_contents($fileUrl);
            if ($fileContents === false) {
                return ['error' => 'Failed to retrieve file content from the URL.'];
            }
        } catch (\Exception $e) {
            return ['error' => 'An error occurred while fetching the file: ' . $e->getMessage()];
        }

        // Extract the file name from the URL
        $fileName = basename($fileUrl);
         //  New Section: Determine the file extension and MIME type
         $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
         $mimeTypes = [
            'pdf'  => 'application/pdf',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif'
            
        ];
        $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';

        // Use Laravel's HTTP client to upload the file
        $response = Http::attach(
            'file', $fileContents, $fileName
        )->withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
        ])->post("https://graph.facebook.com/v18.0/{$this->phoneNumberId}/media", [
            'messaging_product' => 'whatsapp',
            'type' => $mimeType,
        ]);

        // Handle the response
        if ($response->successful()) {
            $responseData = $response->json();
            return ['media_id' => $responseData['id']];
        } else {
            return ['error' => 'Upload failed. Response: ' . $response->body()];
        }
    }
}
