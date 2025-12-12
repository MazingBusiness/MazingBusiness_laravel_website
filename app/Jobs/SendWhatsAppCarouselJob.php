<?php

namespace App\Jobs;

use App\Services\WhatsappCarouselService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SendWhatsAppCarouselJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string|null */
    public $groupId;

    /**
     * @param  string|null  $groupId
     */
    public function __construct($groupId = null)
    {
        $this->groupId = $groupId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batchSize           = 200;   // process N at a time
        $uploadLinksToIds    = true; // convert header image/video link → cloud media ID
        $headerDefaultFormat = 'image'; // 'image' or 'video' fallback if not obvious

        $svc = new WhatsappCarouselService();

        do {
            DB::beginTransaction();

            $q = DB::table('wa_sales_queue')
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate();

            // If you use the flag column to avoid double-pick, keep it:
            if (DB::getSchemaBuilder()->hasColumn('wa_sales_queue', 'flag')) {
                $q->where('flag', 0);
            }

            if (!empty($this->groupId)) {
                $q->where('group_id', $this->groupId);
            }

            $rows = $q->get();

            if ($rows->isEmpty()) {
                DB::rollBack();
                break;
            }

            $ids = $rows->pluck('id')->all();

            DB::table('wa_sales_queue')
                ->whereIn('id', $ids)
                ->update([
                    'status'     => 'processing',
                    'updated_at' => now(),
                ]);

            DB::commit();

            foreach ($rows as $row) {
                try {
                    // content holds templateData posted earlier
                    $templateData = json_decode($row->content ?? '{}', true) ?: [];

                    // ---- Build base payload (keep exactly what Cloud API expects) ----
                    $payload = [
                        'messaging_product' => 'whatsapp',
                         'to'                => $row->to_number,
                        //'to'                => '7044300330',
                        'type'              => 'template',
                        // optional opaque callback for your internal tracking:
                        'biz_opaque_callback_data' => $row->callback_data ?: ($templateData['name'] ?? null),
                        'template'          => [
                            'name'       => (string)($templateData['name'] ?? ''),
                            'language'   => ['code' => (string)($templateData['language'] ?? 'en')],
                            'components' => (array)($templateData['components'] ?? []),
                        ],
                    ];

                    // ---- (Optional) convert header media link -> ID for each card ----
                    if ($uploadLinksToIds) {
                        $components = $payload['template']['components'];
                        foreach ($components as $ci => $comp) {
                            if (strcasecmp($comp['type'] ?? '', 'carousel') !== 0) continue;
                            $cards = (array)($comp['cards'] ?? []);
                            foreach ($cards as $k => $card) {
                                $cardComps = (array)($card['components'] ?? []);
                                foreach ($cardComps as $cj => $c) {
                                    if (strcasecmp($c['type'] ?? '', 'header') !== 0) continue;

                                    // header.parameters[0] => ['type' => 'image|video', 'image' => ['link' => ...]]
                                    $params = (array)($c['parameters'] ?? []);
                                    if (empty($params)) continue;

                                    $p0 = $params[0] ?? [];
                                    $ptype = strtolower((string)($p0['type'] ?? $headerDefaultFormat));
                                    $media = (array)($p0[$ptype] ?? []);

                                    // If has link, upload -> get id, then replace
                                    $link = (string)($media['link'] ?? '');
                                    if ($link !== '') {
                                        try {
                                            $ext  = pathinfo(parse_url($link, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);
                                            $ext  = $ext ? '.' . strtolower($ext) : ($ptype === 'video' ? '.mp4' : '.jpg');
                                            $mid  = $svc->makeMediaIdFromLink($link, 'card_' . ($k + 1) . $ext);

                                            // Replace link with id
                                            $payload['template']['components'][$ci]['cards'][$k]['components'][$cj]['parameters'][0] = [
                                                'type' => $ptype,
                                                $ptype => ['id' => $mid],
                                            ];
                                        } catch (Exception $ee) {
                                            // If upload fails, keep the link (Cloud API can still send by link)
                                            Log::warning('Media upload failed, sending by link', [
                                                'row_id' => $row->id,
                                                'card'   => $k,
                                                'error'  => $ee->getMessage(),
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // ---- Send ----
                    $resp = $svc->sendWithPayload($payload);

                    // Extract message id / status from response
                    $result = $resp['result'] ?? [];
                    $msgId  = $result['messages'][0]['id'] ?? '';
                    $mstat  = $result['messages'][0]['message_status'] ?? 'unknown';
                    $status = $msgId ? 'sent' : 'failed';

                    DB::table('wa_sales_queue')
                        ->where('id', $row->id)
                        ->update([
                            'status'     => $status,
                            'response'   => json_encode($resp),
                            'msg_id'     => $msgId,
                            'msg_status' => $mstat,
                            // keep the flag logic if present
                            'flag'       => DB::getSchemaBuilder()->hasColumn('wa_sales_queue', 'flag') ? 1 : DB::raw('flag'),
                            'updated_at' => now(),
                        ]);

                } catch (Exception $e) {
                    Log::error('Carousel send failed', ['row_id' => $row->id, 'error' => $e->getMessage()]);
                    DB::table('wa_sales_queue')
                        ->where('id', $row->id)
                        ->update([
                            'status'     => 'failed',
                            'response'   => json_encode(['exception' => $e->getMessage()]),
                            'updated_at' => now(),
                        ]);
                }
            }

            // gentle throttle (60 sec)
            sleep(120);

        } while (!$rows->isEmpty());
    }
}
