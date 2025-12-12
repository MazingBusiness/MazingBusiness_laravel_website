<?php

namespace App\Jobs;

use App\Models\OrderLogistic;
use App\Http\Controllers\ZohoController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkUploadInvoiceAttachmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Agar future me group/filter chahiye ho to yahan variables pass kar sakte ho
    public function __construct()
    {
        // abhi blank
    }

    public function handle(): void
    {
        $batchSize = 50; // ✅ 50 records per batch

        /** @var ZohoController $zoho */
        $zoho = app(\App\Http\Controllers\ZohoController::class);

        do {

            // STOP flag (env se control)
            if (env('BULK_ZOHO_STOP', false)) {
                Log::info('Bulk Zoho job: STOP flag detected via env, exiting job.');
                break;
            }

            // 🔍 1) 50 pending logistics pick karo
            // Condition:
            //  - attachment not null / not empty
            //  - zoho_attachment_upload = 0 (ya null) → abhi tak upload nahi hua
            //  - invoice linked ho + invoice_cancel_status = 0 + zoho_invoice_id not null
            $logistics = OrderLogistic::with(['invoice'])
                ->whereNotNull('attachment')
                ->where('attachment', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('zoho_attachment_upload')
                      ->orWhere('zoho_attachment_upload', 0);
                })
                ->whereHas('invoice', function ($q) {
                    $q->where('invoice_cancel_status', 0)
                      ->whereNotNull('zoho_invoice_id');
                    // ->where('invoice_no', 'MUM/2929/25-26'); // test ke time use kar sakte ho
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($logistics->isEmpty()) {
                Log::info('Bulk Zoho attachment job: no more pending logistics, exiting.');
                break;
            }

            Log::info('Bulk Zoho attachment job: processing batch of ' . $logistics->count());

            foreach ($logistics as $logistic) {
                $invoice = $logistic->invoice; // relation se aya

                if (! $invoice) {
                    Log::warning('Bulk Zoho: logistic has no linked invoice', [
                        'order_logistic_id' => $logistic->id,
                        'invoice_no'        => $logistic->invoice_no,
                    ]);

                    // is logistic ko skip mark kar do
                    $logistic->update(['zoho_attachment_upload' => 0]);
                    continue;
                }

                $zohoInvoiceId = $invoice->zoho_invoice_id;
                $invoiceNo     = $invoice->invoice_no;

                if (empty($zohoInvoiceId)) {
                    Log::warning('Bulk Zoho: invoice has no zoho_invoice_id', [
                        'order_logistic_id' => $logistic->id,
                        'invoice_no'        => $invoiceNo,
                    ]);

                    $logistic->update(['zoho_attachment_upload' => 0]);
                    continue;
                }

                // 🔗 Comma-separated attachment URLs -> first use karenge
                $attachments = explode(',', (string) $logistic->attachment);
                $firstUrl    = trim($attachments[0] ?? '');

                if (empty($firstUrl)) {
                    Log::warning('Bulk Zoho: empty attachment URL', [
                        'order_logistic_id' => $logistic->id,
                        'invoice_no'        => $invoiceNo,
                    ]);

                    $logistic->update(['zoho_attachment_upload' => 0]);
                    continue;
                }

                // ⬇️ Yahi value direct Zoho ko pass hogi
                $filePath = $firstUrl;

                Log::info('Bulk Zoho: trying upload', [
                    'order_logistic_id' => $logistic->id,
                    'invoice_no'        => $invoiceNo,
                    'zoho_invoice_id'   => $zohoInvoiceId,
                    'attachment_url'    => $filePath,
                ]);

                // ZohoController::uploadInvoiceAttachmentToZoho URL handle karega
                $result = $zoho->uploadInvoiceAttachmentToZoho($zohoInvoiceId, $filePath);

                if ($result !== false && is_array($result) && isset($result['code']) && (int) $result['code'] === 0) {
                    // ✅ success
                    $logistic->update(['zoho_attachment_upload' => 1]);

                    Log::info('Bulk Zoho: upload success', [
                        'order_logistic_id' => $logistic->id,
                        'invoice_no'        => $invoiceNo,
                    ]);
                } else {
                    // ❌ failed
                    $logistic->update(['zoho_attachment_upload' => 0]);

                    Log::warning('Bulk Zoho: upload failed or unexpected response', [
                        'order_logistic_id' => $logistic->id,
                        'invoice_no'        => $invoiceNo,
                        'response'          => $result,
                    ]);
                }
            }

            // ⏳ 50 hone ke baad 1 min ka gap
            sleep(60); // 60 seconds = 1 minute

        } while (true);
    }
}
