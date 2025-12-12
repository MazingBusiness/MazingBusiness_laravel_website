<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappCarouselService
{
    /** Graph API version */
    protected string $graphVersion = 'v23.0';

    /** Static creds (as provided) */
    protected string $accessToken   = 'EAAHwWe0o5RgBO5VJLczQudXQxhDdQT2Et7aKRkbrnkXQUgIPUtdId4QFK0k64CXI4ZA2D1hvnV5j3snqB22peZCZAttCdjByDpP5ud1hXifvUSRnjb99n8dh1uDdj0gnHZCHDglV1jiVGiRpGX31E9lQfoiM91qfWD96wZCR5yHAm93pQ3zG6ZAzYxTqxaBb7PYQZDZD';
    protected string $phoneNumberId = '423866407487094';
    protected string $appId         = '545743998346520';
    protected string $wabaId        = '530229950165776';
    protected string $bussinessId = '295006418829715';

    /* =====================================================================
     | PUBLIC: DIRECT-PAYLOAD METHODS
     * ===================================================================== */

    /**
     * Post your pre-built CREATE payload to Business Management API.
     * Expects a valid template create payload (with category, components, etc.)
     */
    public function createTemplateWithPayload(array $payload): array
    {
        try {
            // (Optional) lightweight sanity checks — can be removed if you prefer raw post
            if (empty($payload['name'] ?? null)) {
                return $this->failArray('payload.name is required');
            }
            if (empty($payload['components'] ?? null)) {
                return $this->failArray('payload.components is required');
            }

            $resp = Http::withToken($this->accessToken)
                ->post($this->graphUrl($this->wabaId . '/message_templates'), $payload);

            if ($resp->failed()) {
                return ['ok' => false, 'error' => $resp->json(), 'payload' => $payload];
            }
            return ['ok' => true, 'result' => $resp->json()];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['message' => $e->getMessage()], 'payload' => $payload];
        }
    }

    /**
     * Post your pre-built SEND payload to Cloud API.
     * Expects a valid /messages template+carousel payload.
     */
    public function sendWithPayload(array $payload): array
    {
        try {
            // sanity checks
            if (($payload['type'] ?? '') !== 'template') {
                return $this->failArray('payload.type must be "template"');
            }
            if (empty($payload['to'] ?? null)) {
                return $this->failArray('payload.to (E.164) is required');
            }

            // 🔹 Auto-set biz_opaque_callback_data from payload.template.name (if not already provided)
            $tplName = (string) data_get($payload, 'template.name', '');
            if ($tplName !== '' && empty($payload['biz_opaque_callback_data'])) {
                // keep it short/safe; webhook echoes this back in statuses[]
                $payload['biz_opaque_callback_data'] = mb_substr($tplName, 0, 512);
            }

            $resp = Http::withToken($this->accessToken)
                ->post($this->graphUrl($this->phoneNumberId . '/messages'), $payload);

            // $resp = Http::withToken($this->accessToken)
            //     ->post($this->graphUrl($this->phoneNumberId . '/marketing_messages'), $payload);
            

            if ($resp->failed()) {
                return ['ok' => false, 'error' => $resp->json(), 'payload' => $payload];
            }
            return ['ok' => true, 'result' => $resp->json()];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['message' => $e->getMessage()], 'payload' => $payload];
        }
    }


    /* =====================================================================
     | PUBLIC: OPTIONAL HELPERS (use when building payload yourself)
     * ===================================================================== */

    /**
     * Get a 4::... header handle via Resumable Upload.
     * Use this in template payload at components.CAROUSEL.cards[*].components[HEADER].example.header_handle[0]
     */
    public function makeHeaderHandleFromLink(string $url): string
    {
        $bin = Http::get($url)->body();
        if (!$bin) {
            throw new \RuntimeException('Failed to download media for handle.');
        }
        $len      = strlen($bin);
        $filename = basename(parse_url($url, PHP_URL_PATH) ?: 'asset');
        $mime     = $this->guessMimeFromBytes($bin) ?: 'image/jpeg';

        // START
        $start = Http::withToken($this->accessToken)->post(
            $this->graphUrl($this->appId . '/uploads'),
            [
                'file_length'  => (string)$len,
                'file_type'    => $mime,
                'file_name'    => $filename,
                'upload_phase' => 'start',
            ]
        );
        if ($start->failed()) {
            throw new \RuntimeException('Resumable start failed: ' . json_encode($start->json()));
        }
        $sessionId   = data_get($start->json(), 'id') ?? data_get($start->json(), 'upload_session_id');
        $startOffset = (string)(data_get($start->json(), 'start_offset') ?? '0');

        // TRANSFER
        $transfer = Http::withToken($this->accessToken)
            ->attach('file_chunk', $bin, $filename)
            ->post($this->graphUrl($sessionId), [
                'upload_phase' => 'transfer',
                'start_offset' => $startOffset,
            ]);
        if ($transfer->failed()) {
            throw new \RuntimeException('Resumable transfer failed: ' . json_encode($transfer->json()));
        }

        // FINISH
        $finish = Http::withToken($this->accessToken)->post(
            $this->graphUrl($sessionId),
            ['upload_phase' => 'finish']
        );
        if ($finish->failed()) {
            throw new \RuntimeException('Resumable finish failed: ' . json_encode($finish->json()));
        }

        $handle = data_get($finish->json(), 'h') ?? data_get($finish->json(), 'handle') ?? '';
        if (!$handle || (strpos($handle, '4:') !== 0 && strpos($handle, '4::') !== 0)) {
            throw new \RuntimeException('Invalid header handle from upload.');
        }
        return $handle;
    }

    /**
     * Upload to /media and get an ID (useful for sending by id).
     * Then, in send payload: image: { id: "..."} or video: { id: "..." }
     */
    public function makeMediaIdFromLink(string $url, string $filename = 'media.bin'): string
    {
        $bin = Http::get($url)->body();
        if (!$bin) {
            throw new \RuntimeException('Failed to download media.');
        }

        $resp = Http::withToken($this->accessToken)
            ->attach('file', $bin, $filename)
            ->post($this->graphUrl($this->phoneNumberId . '/media'), [
                'messaging_product' => 'whatsapp',
            ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Cloud media upload failed: ' . json_encode($resp->json()));
        }
        $id = data_get($resp->json(), 'id');
        if (!$id) {
            throw new \RuntimeException('Media ID missing.');
        }
        return $id;
    }

    public function listTemplates(int $limit = 50, ?string $name = null): array
    {

       //  List message templates from your WABA.
        try {
            // clamp limit 1..50
            $limit = max(1, min(50, (int)$limit));

            $resp = Http::withToken($this->accessToken)
                ->get($this->graphUrl($this->wabaId . '/message_templates'), ['limit' => $limit]);

            if ($resp->failed()) {
                return ['ok' => false, 'error' => $resp->json()];
            }

            $data = $resp->json();

            // optional exact-name filter (like your controller)
            $n = trim((string)($name ?? ''));
            if ($n !== '') {
                $items = $data['data'] ?? [];
                $filtered = [];
                foreach ($items as $row) {
                    if (($row['name'] ?? null) === $n) {
                        $filtered[] = $row;
                    }
                }
                $data['data'] = array_values($filtered);
            }

            return ['ok' => true, 'data' => $data];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['message' => $e->getMessage()]];
        }
    }


     //Delete Funtionalities
    public function deleteTemplate(?string $name = null, ?string $hsmId = null): array
    {
        try {
            if ($name === null && $hsmId === null) {
                return $this->failArray('Either name or hsm_id is required.');
            }

            $query = [];
            if ($name !== null && $name !== '')  { $query['name']   = $name; }
            if ($hsmId !== null && $hsmId !== ''){ $query['hsm_id'] = $hsmId; }

            $resp = \Illuminate\Support\Facades\Http::withToken($this->accessToken)
                ->send('DELETE', $this->graphUrl($this->wabaId . '/message_templates'), [
                    'query' => $query, // send as query-string on DELETE
                ]);

            if ($resp->failed()) {
                return ['ok' => false, 'error' => $resp->json()];
            }

            return ['ok' => true, 'result' => $resp->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => ['message' => $e->getMessage()]];
        }
    }

    public function deleteTemplateByName(string $name): array
    {
        return $this->deleteTemplate($name, null);
    }

    public function deleteTemplateById(string $hsmId, ?string $nameForSafety = null): array
    {
        // Some implementations require both hsm_id and name; pass both when available.
        return $this->deleteTemplate($nameForSafety, $hsmId);
    }

    /* =====================================================================
     | OPTIONAL: BUILDERS (use only if you want me to build payload for you)
     * ===================================================================== */

    /**
     * Build a CREATE payload (if you prefer me to assemble it).
     * - $headerHandles is an array of 4::... strings (one per card)
     */
    public function buildCreatePayload(
        string $name,
        string $language,
        string $bodyText,
        ?array $topBodyExample,
        string $headerFormat,
        array $cardsInput,
        array $headerHandles
    ): array {
        $cards = [];
        foreach ($cardsInput as $i => $ci) {
            $cardBodyText = (string)($ci['body_text'] ?? '');
            $cardComp     = ['type' => 'BODY', 'text' => $cardBodyText];

            // add example for card BODY if you pass it:
            if (!empty($ci['body_example'])) {
                $cardComp['example'] = ['body_text' => [ (array)$ci['body_example'] ]];
            }

            $components = [
                [
                    'type'   => 'HEADER',
                    'format' => strtoupper($headerFormat) === 'VIDEO' ? 'VIDEO' : 'IMAGE',
                    'example' => ['header_handle' => [ (string)($headerHandles[$i] ?? '') ]],
                ],
                ($cardBodyText !== '' ? $cardComp : null),
                [
                    'type'    => 'BUTTONS',
                    'buttons' => [
                        [
                            'type'    => 'URL',
                            'text'    => 'View',
                            'url'     => 'https://mazingbusiness.com/?p={{1}}',
                            'example' => (string)($ci['url_btn_example'] ?? 'https://mazingbusiness.com/?p=WP-100'),
                        ],
                        [
                            'type' => 'QUICK_REPLY',
                            'text' => (string)($ci['quick_reply_text'] ?? 'Interested'),
                        ],
                    ],
                ],
            ];

            $cards[] = ['components' => array_values(array_filter($components))];
        }

        $topBody = ['type' => 'BODY', 'text' => $bodyText];
        if (!empty($topBodyExample)) {
            $topBody['example'] = ['body_text' => [ $topBodyExample ]];
        }

        return [
            'name'                  => $name,
            'category'              => 'MARKETING',
            'allow_category_change' => true,
            'language'              => $language,
            'components'            => [
                $topBody,
                ['type' => 'CAROUSEL', 'cards' => $cards],
            ],
        ];
    }




    /**
     * Build a SEND payload (if you prefer me to assemble it).
     * Pass $cardsInput with 'components' already prepared, or with shorthand keys.
     */
    public function buildSendPayload(
        string $toE164,
        string $templateName,
        string $language,
        array $bodyParams,
        array $cardsInput
    ): array {
        // Ensure card_index exists per card and is 0..n-1
        $cardsOut = [];
        foreach (array_values($cardsInput) as $i => $card) {
            $cardsOut[] = [
                'card_index' => $i,
                'components' => $card['components'], // Expect a correct components array per card
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to'                => $toE164,
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(
                            fn($t)=>['type'=>'text','text'=>(string)$t],
                            $bodyParams
                        ),
                    ],
                    [
                        'type'  => 'carousel',
                        'cards' => $cardsOut,
                    ],
                ],
            ],
        ];
    }




    /* =====================================================================
     | INTERNAL UTILS
     * ===================================================================== */

    protected function graphUrl(string $path): string
    {
        return "https://graph.facebook.com/{$this->graphVersion}/{$path}";
    }

    protected function guessMimeFromBytes(string $bytes): ?string
    {
        $info = @getimagesizefromstring($bytes);
        return !empty($info['mime']) ? $info['mime'] : null;
    }

    protected function failArray(string $message, int $code = 422): array
    {
        return ['ok'=>false, 'error'=>['code'=>$code, 'message'=>$message]];
    }


    // Optional function (not requierd)

     public function buildEmbeddedSignupUrl(): string
    {
      $base = 'https://business.facebook.com/whatsapp_business/es';
      $q = http_build_query([
        'app_id'              => $this->appId,
        'business_id'         => $this->businessId,
        'features_enabled[0]' => 'marketing_messages_lite',
        'feature_type'        => 'whatsapp',
      ]);
      return $base.'?'.$q;
    }

    // Fallback (older path)
    public function buildEmbeddedSignupUrlFallback(): string
    {
      $base = 'https://business.facebook.com/whatsapp_business/embedded_signup/';
      $q = http_build_query([
        'app_id'              => $this->appId,
        'business_id'         => $this->businessId,
        'features_enabled[0]' => 'marketing_messages_lite',
        'feature_type'        => 'whatsapp',
      ]);
      return $base.'?'.$q;
    }



    // ================== ADD THESE METHODS INSIDE WhatsappCarouselService ==================

    /** GET a template by its Graph object ID */
    public function getTemplateById(string $id): array
    {
        try {
            $r = Http::withToken($this->accessToken)->get($this->graphUrl($id));
            return $r->failed() ? ['ok'=>false,'error'=>$r->json()] : ['ok'=>true,'data'=>$r->json()];
        } catch (\Throwable $e) {
            return ['ok'=>false,'error'=>['message'=>$e->getMessage()]];
        }
    }

    /** GET by name+language (language may be 'en' or 'en_US') */
    public function getTemplateByNameAndLanguage(string $name, string $language): array
    {
        $res = $this->listTemplates(50, $name);
        if (!($res['ok'] ?? false)) return $res;

        $langNeed = strtolower($language);
        foreach ((array) data_get($res, 'data.data', []) as $row) {
            $lang = strtolower((string)($row['language'] ?? ''));
            // Meta sometimes returns en or en_US — accept both
            if ($row['name'] === $name && (strpos($lang, $langNeed) === 0)) {
                return ['ok'=>true,'data'=>$row];
            }
        }
        return ['ok'=>false,'error'=>['message'=>'Template not found for given name+language']];
    }

    /**
     * Convert Meta template JSON to your form's prefill shape.
     * Note: Header original URL cannot be recovered from 4:: handles, so header_link is left blank.
     */
    public function parseTemplateForEdit(array $tpl): array
    {
        $out = [
            'id'            => (string) data_get($tpl, 'id', ''),
            'name'          => (string) data_get($tpl, 'name', ''),
            'language'      => (string) data_get($tpl, 'language', 'en'),
            'header_format' => 'IMAGE',
            'body'          => '',
            'body_example'  => [],
            'cards'         => [],
        ];

        $components = (array) ($tpl['components'] ?? []);
        $carousel = null;

        foreach ($components as $comp) {
            $t = strtoupper((string)($comp['type'] ?? ''));
            if ($t === 'BODY') {
                $out['body'] = (string) ($comp['text'] ?? '');
                $ex = data_get($comp, 'example.body_text.0', []);
                if (is_string($ex)) $ex = [$ex];
                $out['body_example'] = array_values((array)$ex);
            } elseif ($t === 'CAROUSEL') {
                $carousel = $comp;
            }
        }

        // deduce header format from first card header component if exists
        if ($carousel && !empty($carousel['cards'][0]['components'])) {
            foreach ((array) $carousel['cards'][0]['components'] as $cc) {
                if (strtoupper((string)($cc['type'] ?? '')) === 'HEADER') {
                    $out['header_format'] = strtoupper((string)($cc['format'] ?? 'IMAGE')) === 'VIDEO' ? 'VIDEO' : 'IMAGE';
                    break;
                }
            }
        }

        // build cards (we can’t recover media links, only text/examples/buttons)
        foreach ((array) data_get($carousel, 'cards', []) as $card) {
            $one = [
                'header_link'      => '', // user must paste a new URL if they want to change media
                'body_text'        => '',
                'body_example'     => [],
                'url_btn_example'  => '',
                'quick_reply_text' => 'Interested',
            ];

            $hdr = null;
            foreach ((array) ($card['components'] ?? []) as $cc) {
                $ct = strtoupper((string)($cc['type'] ?? ''));
                if ($ct === 'HEADER' && !$hdr) $hdr = $cc;

                if ($ct === 'BODY') {
                    $one['body_text'] = (string) ($cc['text'] ?? '');
                    $bx = data_get($cc, 'example.body_text.0', []);
                    if (is_string($bx)) $bx = [$bx];
                    $one['body_example'] = array_values((array)$bx);
                }

                if ($ct === 'BUTTONS') {
                    foreach ((array) ($cc['buttons'] ?? []) as $btn) {
                        $bt = strtoupper((string)($btn['type'] ?? ''));
                        if ($bt === 'URL') {
                            $ex = data_get($btn, 'example', '');
                            // example may be array or string
                            if (is_array($ex)) { $ex = (string) ($ex[0] ?? ''); }
                            $one['url_btn_example'] = (string) $ex;
                        }
                        if ($bt === 'QUICK_REPLY') {
                            $one['quick_reply_text'] = (string) ($btn['text'] ?? 'Interested');
                        }
                    }
                }
            }
            $out['cards'][] = $one;
        }

        return $out;
    }

    /**
     * Update an existing template by ID (same template name).
     * Payload shape = full template shape (name, language, category, components[]).
     */
    public function updateTemplateWithPayload(string $templateId, array $payload): array
    {
        try {
            $r = Http::withToken($this->accessToken)
                ->post($this->graphUrl($templateId), $payload);

            if ($r->failed()) {
                return ['ok'=>false,'error'=>$r->json(), 'payload'=>$payload];
            }
            return ['ok'=>true,'result'=>$r->json()];
        } catch (\Throwable $e) {
            return ['ok'=>false,'error'=>['message'=>$e->getMessage()], 'payload'=>$payload];
        }
    }
}