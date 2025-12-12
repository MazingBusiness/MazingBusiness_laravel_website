<?php
// ===============================
// Rewards/Reminders Controller
// ===============================

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

// Models
use App\Models\Address;
use App\Models\User;
use App\Models\InvoiceOrder;
use App\Models\RewardPointsOfUser;
use App\Models\PurchaseInvoice;

use App\Models\RewardRemainderEarlyPayment;
use App\Models\Warehouse;
// ZohoController
use App\Http\Controllers\ZohoController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\RewardController;

// Jobs
use App\Jobs\SendWhatsAppMessagesJob;

Use PDF;
// Services
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Schema;
use App\Services\PdfContentService;
use Carbon\Carbon;
class RewardReminderController extends Controller
{
    
    private function getManagerIdForParty(string $partyCode): int
    {
        $code = trim($partyCode);
        if ($code === '') {
            return 0;
        }

        // normalize if you have a trimmer
        if (method_exists($this, 'getTrimmedPartyCode')) {
            $code = $this->getTrimmedPartyCode($code);
        }

        // 1) Direct: users.party_code -> manager_id
        $managerId = (int) (User::where('party_code', $code)->value('manager_id') ?? 0);
        if ($managerId > 0) {
            return $managerId;
        }

        // 2) Fallback: addresses.acc_code -> user_id -> users.manager_id
        $userId = (int) (Address::where('acc_code', $code)->value('user_id') ?? 0);
        if ($userId > 0) {
            $managerId = (int) (User::where('id', $userId)->value('manager_id') ?? 0);
            if ($managerId > 0) {
                return $managerId;
            }
        }

        return 0;
    }
    public function insertEarlyPaymentRemainders()
    {
        // -------------- GLOBAL CONSTANTS / PRELOADS -----------------
        RewardRemainderEarlyPayment::truncate(); // keep if you want full rebuild each time
    
        $today        = Carbon::now('Asia/Kolkata')->startOfDay();
        $cutoffInsert = Carbon::parse('2025-09-10')->startOfDay(); // invoices before this are ignored
        $maxEarlyDays = 40;                                       // > 40 days => no early payment
        $excludeParties = [
            'OPEL0100739', 'OPEL0100740', 'OPEL0100741',
            'OPEL0100815', 'OPEL0500311', 'OPEL0600102',
        ];
    
        // invoices which already got Early Payment reward
        $invoicesWithEarlyPayment = RewardPointsOfUser::where('rewards_from', 'Early Payment')
            ->pluck('invoice_no')
            ->map(function ($v) { return trim((string) $v); })
            ->unique()
            ->values()
            ->toArray();
    
        // helper to normalise numbers from statement JSON
        $num = function ($v): float {
            if ($v === null) return 0.0;
            if (is_numeric($v)) return (float) $v;
    
            $v = (string) $v;
            // remove currency, commas, spaces
            $v = str_replace(['₹', ',', ' '], '', $v);
            // keep only digits, dot, minus
            $v = preg_replace('/[^\d.\-]/u', '', $v);
    
            return (float) $v;
        };
    
        // -----------------------------------------------------------------
        // MAIN LOOP: process all parties in chunks
        // -----------------------------------------------------------------
        Address::select('id', 'acc_code', 'statement_data', 'phone')
            ->whereNotIn('acc_code', $excludeParties)
            ->whereNotNull('statement_data')
            ->orderBy('id')
            ->chunkById(500, function ($addresses) use (
                $today,
                $cutoffInsert,
                $maxEarlyDays,
                $invoicesWithEarlyPayment,
                $num
            ) {
                foreach ($addresses as $address) {
                    $partyCode     = $address->acc_code;
                    $statementData = json_decode($address->statement_data, true);
    
                    if (!$statementData || !is_array($statementData)) {
                        \Log::warning("insertEarlyPaymentRemainders: invalid statement data for {$partyCode}");
                        continue;
                    }
    
                    // ---------------------------------------------------------
                    // 1) Build cleaned ledger (date, type, debit, credit, trn_no)
                    // ---------------------------------------------------------
                    $ledger = [];
                    $totalDebit  = 0.0;
                    $totalCredit = 0.0;
    
                    foreach ($statementData as $row) {
                        $vtRaw = $row['vouchertypebasename'] ?? '';
                        $vt    = strtoupper(trim($vtRaw));
    
                        $dateStr = $row['trn_date'] ?? null;
                        if (!$dateStr) {
                            continue;
                        }
    
                        // robust date parsing (dd/mm/yyyy, dd-mm-yyyy, or generic)
                        try {
                            $dateStr = trim($dateStr);
    
                            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateStr)) {
                                $date = Carbon::createFromFormat('d/m/Y', $dateStr);
                            } elseif (preg_match('/^\d{2}\-\d{2}\-\d{4}$/', $dateStr)) {
                                $date = Carbon::createFromFormat('d-m-Y', $dateStr);
                            } else {
                                $date = Carbon::parse($dateStr);
                            }
                        } catch (\Throwable $e) {
                            \Log::warning("insertEarlyPaymentRemainders: invalid trn_date [{$dateStr}] for party {$partyCode}");
                            continue;
                        }
    
                        $debit  = $num($row['dramount'] ?? $row['debit'] ?? 0);
                        $credit = $num($row['cramount'] ?? $row['credit'] ?? 0);
    
                        $totalDebit  += $debit;
                        $totalCredit += $credit;
    
                        $txnNo = isset($row['trn_no']) ? trim((string) $row['trn_no']) : null;
    
                        // normalised type
                        if ($vt === 'SALES') {
                            if ($debit <= 0) continue;
                            $ledger[] = [
                                'date'    => $date,
                                'type'    => 'SALE',
                                'debit'   => $debit,
                                'credit'  => 0.0,
                                'txn_no'  => $txnNo,
                            ];
                        } else {
                            // treat all non-sales that give credit to the party as CREDIT:
                            // RECEIPT, JOURNAL, CREDIT NOTE, PURCHASE, etc.
                            // If your JSON has specific type names, add them here.
                            if ($credit <= 0) continue;
                            $ledger[] = [
                                'date'    => $date,
                                'type'    => 'CREDIT',
                                'debit'   => 0.0,
                                'credit'  => $credit,
                                'txn_no'  => $txnNo,
                            ];
                        }
                    }
    
                    if (empty($ledger)) {
                        continue; // nothing to process
                    }
    
                    // ---------------------------------------------------------
                    // 2) Skip parties with overall credit / zero balance
                    // ---------------------------------------------------------
                    $closingBalance = $totalDebit - $totalCredit; // Dr - Cr
                    if ($closingBalance <= 0.0) {
                        // Clean up any existing rows for this party and skip
                        RewardRemainderEarlyPayment::where('party_code', $partyCode)->delete();
                        continue;
                    }
    
                    // ---------------------------------------------------------
                    // 3) Sort ledger by date (then by original index order)
                    // ---------------------------------------------------------
                    usort($ledger, function ($a, $b) {
                        /** @var \Carbon\Carbon $da */
                        /** @var \Carbon\Carbon $db */
                        $da = $a['date'];
                        $db = $b['date'];
    
                        if ($da->eq($db)) {
                            return 0;
                        }
                        return $da->lt($db) ? -1 : 1;
                    });
    
                    // ---------------------------------------------------------
                    // 4) FIFO allocation: compute per-invoice remaining/applied
                    // ---------------------------------------------------------
                    $openInvoices = [];   // all sales, with applied + remaining
                    foreach ($ledger as $txn) {
                        if ($txn['type'] === 'SALE') {
                            $openInvoices[] = [
                                'invoice_no' => $txn['txn_no'] ?? '',
                                'date'       => $txn['date']->copy(),
                                'amount'     => (float) $txn['debit'],
                                'applied'    => 0.0,
                                'remaining'  => (float) $txn['debit'],
                                'cleared_on' => null,
                            ];
                        } else {
                            // CREDIT
                            $credit = (float) $txn['credit'];
                            if ($credit <= 0) continue;
    
                            // apply to oldest open invoices
                            for ($i = 0; $i < count($openInvoices) && $credit > 0; $i++) {
                                if ($openInvoices[$i]['remaining'] <= 0.0) continue;
    
                                $apply = min($credit, $openInvoices[$i]['remaining']);
                                $openInvoices[$i]['applied']   += $apply;
                                $openInvoices[$i]['remaining'] -= $apply;
                                $credit -= $apply;
    
                                if ($openInvoices[$i]['remaining'] <= 0.00001 && $openInvoices[$i]['cleared_on'] === null) {
                                    $openInvoices[$i]['cleared_on'] = $txn['date']->copy();
                                }
                            }
                        }
                    }
    
                    if (empty($openInvoices)) {
                        continue;
                    }
    
                    // Map of invoices that are marked for early payment in InvoiceOrder
                    $eligibleInvoiceSet = InvoiceOrder::where('party_code', $partyCode)
                        ->where('early_payment_check', 1)
                        ->pluck('invoice_no')
                        ->map(function ($v) { return trim((string) $v); })
                        ->flip()     // keys = invoice_no
                        ->toArray();
    
                    if (empty($eligibleInvoiceSet)) {
                        // nothing for this party is early-payment-eligible
                        RewardRemainderEarlyPayment::where('party_code', $partyCode)->delete();
                        continue;
                    }
    
                    // ---------------------------------------------------------
                    // 5) Build reminder rows (only unpaid or partially paid,
                    //    within 0–40 days, and eligible for early payment)
                    // ---------------------------------------------------------
                    $stillUnpaidEligibleNos = [];
    
                    foreach ($openInvoices as $inv) {
                        $invoiceNo   = trim((string) $inv['invoice_no']);
                        $invoiceDate = $inv['date']->copy();
                        $amount      = (float) $inv['amount'];
                        $applied     = (float) $inv['applied'];
                        $remaining   = (float) $inv['remaining'];
    
                        // Skip very old invoices
                        if ($invoiceDate->lt($cutoffInsert)) {
                            continue;
                        }
    
                        // Nothing outstanding
                        if ($remaining <= 0.00001) {
                            continue;
                        }
    
                        // Only invoices that are marked early_payment_check = 1
                        if (!isset($eligibleInvoiceSet[$invoiceNo])) {
                            continue;
                        }
    
                        // Skip invoices which already got Early Payment reward
                        if (in_array($invoiceNo, $invoicesWithEarlyPayment, true)) {
                            continue;
                        }
    
                        // Age in days from invoice date till today
                        $ageDays = $invoiceDate->diffInDays($today, false);
    
                        // future invoices or older than 40 days are NOT early-payment
                        if ($ageDays <= 0 || $ageDays > $maxEarlyDays) {
                            continue;
                        }
    
                        // determine payment status
                        $status = $applied > 0 ? 'Partially Paid' : 'Unpaid';
    
                        // -----------------------------------------------------
                        // EARLY PAYMENT PERCENTAGE LOGIC
                        // -----------------------------------------------------
                        // 0–15 days (inclusive)  => 2%
                        // 16–40 days (inclusive) => 1%
                        $rewardPercentage = ($ageDays <= 20) ? 2.0 : 1.0;
    
                        // manager & warehouse info
                        $managerId   = $this->getManagerIdForParty($partyCode);
                        $warehouseId = (int) (User::where('party_code', $partyCode)->value('warehouse_id') ?? 0);
    
                        RewardRemainderEarlyPayment::updateOrCreate(
                            [
                                'party_code' => $partyCode,
                                'invoice_no' => $invoiceNo,
                            ],
                            [
                                'invoice_date'      => $invoiceDate->toDateString(),
                                'invoice_amount'    => $amount,
                                'payment_applied'   => $applied,
                                'remaining_amount'  => $remaining,
                                'payment_status'    => $status,
                                'manager_id'        => $managerId,
                                'warehouse_id'      => $warehouseId,
                                'reminder_sent'     => 0,
                                'is_processed'      => 0,
                                'reward_percentage' => $rewardPercentage,
                                'updated_at'        => now(),
                            ]
                        );
    
                        $stillUnpaidEligibleNos[] = $invoiceNo;
                    }
    
                    // ---------------------------------------------------------
                    // 6) Cleanup: remove rows for this party that are no longer valid
                    // ---------------------------------------------------------
                    if (!empty($stillUnpaidEligibleNos)) {
                        $existing = RewardRemainderEarlyPayment::where('party_code', $partyCode)
                            ->whereDate('invoice_date', '>=', $cutoffInsert->toDateString())
                            ->get(['id', 'invoice_no']);
    
                        foreach ($existing as $row) {
                            if (!in_array($row->invoice_no, $stillUnpaidEligibleNos, true)) {
                                $row->delete();
                                \Log::info("insertEarlyPaymentRemainders cleanup: deleted {$partyCode} / {$row->invoice_no}");
                            }
                        }
                    } else {
                        // no valid invoices for early-payment – clear any existing rows
                        RewardRemainderEarlyPayment::where('party_code', $partyCode)->delete();
                    }
                } // foreach address
            }); // chunkById
    
        return response()->json([
            'message' => 'Early payment reminders data inserted successfully with 15/40 day reward logic.',
        ], 200);
    }

    public function getTrimmedPartyCode($party_code)
    {
        return substr((string) $party_code, 0, 11);
    }

   


    public function sendEarlyPaymentWhatsAppOnButtonClick($party_code)
   {
        $groupId     = 'group_' . uniqid();
        $currentDate = Carbon::now('Asia/Kolkata')->toDateString();

        // 1) Unique party list (no gating)
        $partyList = RewardRemainderEarlyPayment::query()
            ->select('party_code')
            ->where('party_code',$party_code)
            ->distinct()
            ->pluck('party_code')
            ->filter()
            ->values()
            ->all();



        if (empty($partyList)) {
            \Log::warning("No parties found for reminders.");
            return response()->json(['message' => 'No parties found.'], 404);
        }

        /** @var \App\Http\Controllers\ZohoController $zoho */
        $zoho = app(ZohoController::class);

        foreach ($partyList as $partyCode) {
            // 2) Rows used by the PDF (ensure same logic as your PDF)
            if (!method_exists($this, 'buildAllRowsForParty')) {
                \Log::error("buildAllRowsForParty() not found.");
                continue;
            }
            $rows = (array) $this->buildAllRowsForParty($partyCode, $currentDate);
            if (empty($rows)) {
                continue;
            }

            // 3) Generate consolidated PDF per party
            if (!method_exists($this, 'generatePartyEarlyPaymentPdf')) {
                \Log::error("generatePartyEarlyPaymentPdf() not found.");
                continue;
            }
            $pdfUrl = (string) $this->generatePartyEarlyPaymentPdf($partyCode, $rows);

            // 4) Header filename from URL (fallback safe)
            $fileName = 'early_payment_' . preg_replace('/\W+/', '', $partyCode) . '.pdf';
            $path = parse_url($pdfUrl, PHP_URL_PATH);
            if (is_string($path)) {
                $base = basename($path);
                if ($base !== '') {
                    $fileName = stripos($base, '.pdf') !== false ? $base : ($base . '.pdf');
                }
            }

            // 5) Due amount from addresses table (this drives the payment link amount & {{2}})
            $dueAmount = (float) (Address::where('acc_code', $partyCode)->value('due_amount') ?? 0);

            // 6) Consolidated Zoho payment link (party_total mode)
            $paymentUrl = '';
            if ($dueAmount > 0) {
                $paymentUrl = (string) $zoho->generatePaymentUrl(
                    'MULTI',        // invoice marker
                    'party_total',  // scope marker
                    $dueAmount,     // consolidated amount (addresses.due_amount)
                    $partyCode
                );
            } else {
                \Log::info("Party {$partyCode}: due_amount is 0 — skipping payment link.");
            }

            // 7) Send WhatsApp (3 body placeholders template)
            $resp=$this->sendWhatsAppReminder(
                $partyCode,   // party_code
                $paymentUrl,  // consolidated party-level payment URL (may be empty if due=0)
                $pdfUrl,      // header document link
                $fileName     // header document filename
               
            );



            // 8) Extract msg_id and recipient from response
            // adjust these keys to match your WA service response
            $msgId = data_get($resp, 'messages.0.id')
                  ?? data_get($resp, 'data.messages.0.id')
                  ?? data_get($resp, '0.id')
                  ?? null;


            try {
                RewardRemainderEarlyPayment::where('party_code', $partyCode)
                    ->update([
                        'msg_id'     => $msgId,
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                // Column nahi hua to yeh silent rahega (avoid fatal)
                \Log::warning("Skipping msg_id update in reward_remainder_early_payments for {$partyCode}: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('status', 'WhatsApp reminder sent successfully.');
   }
   public function sendEarlyPaymentWhatsApp()
   {
        $groupId     = 'group_' . uniqid();
        $currentDate = Carbon::now('Asia/Kolkata')->toDateString();

        // 1) Unique party list (no gating)
        $partyList = RewardRemainderEarlyPayment::query()
            ->select('party_code')
            ->whereNotNull('party_code')
            ->distinct()
            ->pluck('party_code')
            ->filter()
            ->values()
            ->all();

        if (empty($partyList)) {
            \Log::warning("No parties found for reminders.");
            return response()->json(['message' => 'No parties found.'], 404);
        }

        /** @var \App\Http\Controllers\ZohoController $zoho */
        $zoho = app(ZohoController::class);

        foreach ($partyList as $partyCode) {
            // 2) Rows used by the PDF (ensure same logic as your PDF)
            if (!method_exists($this, 'buildAllRowsForParty')) {
                \Log::error("buildAllRowsForParty() not found.");
                continue;
            }
            $rows = (array) $this->buildAllRowsForParty($partyCode, $currentDate);
            if (empty($rows)) {
                continue;
            }

            // 3) Generate consolidated PDF per party
            if (!method_exists($this, 'generatePartyEarlyPaymentPdf')) {
                \Log::error("generatePartyEarlyPaymentPdf() not found.");
                continue;
            }
            $pdfUrl = (string) $this->generatePartyEarlyPaymentPdf($partyCode, $rows);

            // 4) Header filename from URL (fallback safe)
            $fileName = 'early_payment_' . preg_replace('/\W+/', '', $partyCode) . '.pdf';
            $path = parse_url($pdfUrl, PHP_URL_PATH);
            if (is_string($path)) {
                $base = basename($path);
                if ($base !== '') {
                    $fileName = stripos($base, '.pdf') !== false ? $base : ($base . '.pdf');
                }
            }

            // 5) Due amount from addresses table (this drives the payment link amount & {{2}})
            $dueAmount = (float) (Address::where('acc_code', $partyCode)->value('due_amount') ?? 0);

            // 6) Consolidated Zoho payment link (party_total mode)
            $paymentUrl = '';
            if ($dueAmount > 0) {
                $paymentUrl = (string) $zoho->generatePaymentUrl(
                    'MULTI',        // invoice marker
                    'party_total',  // scope marker
                    $dueAmount,     // consolidated amount (addresses.due_amount)
                    $partyCode
                );
            } else {
                \Log::info("Party {$partyCode}: due_amount is 0 — skipping payment link.");
            }

            // 7) Send WhatsApp (3 body placeholders template)
           $resp = $this->sendWhatsAppReminder(
                $partyCode,   // party_code
                $paymentUrl,  // consolidated party-level payment URL (may be empty if due=0)
                $pdfUrl,      // header document link
                $fileName     // header document filename
                
            );


           // 8) Extract msg_id and recipient from response
            // adjust these keys to match your WA service response
            $msgId = data_get($resp, 'messages.0.id')
                  ?? data_get($resp, 'data.messages.0.id')
                  ?? data_get($resp, '0.id')
                  ?? null;


            try {
                RewardRemainderEarlyPayment::where('party_code', $partyCode)
                    ->update([
                        'msg_id'     => $msgId,
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                // Column nahi hua to yeh silent rahega (avoid fatal)
                \Log::warning("Skipping msg_id update in reward_remainder_early_payments for {$partyCode}: " . $e->getMessage());
            }


           
        }

        return response()->json(['message' => 'WhatsApp reminders processed successfully.'], 200);
   }

    private function earlyPayMetaForToday(Carbon $invoiceDate, int $reminderSent, string $today): ?array
    {
        // Gates relative to invoice date
        $firstReminderDate  = $invoiceDate->copy()->addDays(15)->toDateString();
        $secondReminderDate = $invoiceDate->copy()->addDays(40)->toDateString();
        
        // First cycle (2%) — pay-by D+15
        if ($today === $firstReminderDate) {
            return [2.0, $invoiceDate->copy()->addDays(15)->toDateString(), 1];
        }

        // Second cycle (1.0%) — pay-by D+40
        if ($today === $secondReminderDate) {
            return [1.0, $invoiceDate->copy()->addDays(40)->toDateString(), 2];
        }

        return null;
    }

    private function rewardPercentFor(Carbon $invoiceDate, string $today): float
    {
        $age = $invoiceDate->diffInDays(Carbon::parse($today));
        return $age > 15 ? 1.0 : 2.0;
    }

    /** Return Pay-By date based on the chosen % (1% → D+40, 2% → D+15). */
    private function lastPayDateFor(Carbon $invoiceDate, float $percent): string
    {
        return ($percent <= 1.0)
            ? $invoiceDate->copy()->addDays(40)->toDateString()
            : $invoiceDate->copy()->addDays(15)->toDateString();
    }

    private function buildAllRowsForParty(string $partyCode, string $today): array
    {
        $items = RewardRemainderEarlyPayment::where('party_code', $partyCode)
            ->orderBy('invoice_date')
            ->get(['invoice_no', 'invoice_date', 'invoice_amount', 'remaining_amount']); // ⬅️ added

        $rows = [];
        foreach ($items as $it) {
            $txDate = Carbon::parse($it->invoice_date);
            $perc   = $this->rewardPercentFor($txDate, $today);
            $payBy  = $this->lastPayDateFor($txDate, $perc);

            $rows[] = [
                'invoice_no'        => (string)$it->invoice_no,
                'invoice_date'      => $txDate->toDateString(),
                'invoice_amount'    => (float)$it->invoice_amount,
                'remaining_amount'  => (float)$it->remaining_amount,   // ⬅️ added
                'reward_percentage' => $perc,
                'last_payment_date' => $payBy,
            ];
        }
        return $rows;
    }

     private function generatePartyEarlyPaymentPdf(string $party_code, array $rows): string
    {
        $mapped = [];
        $totalReward = 0.0;
        $totalRemaining = 0.0; // ⬅️ added (optional total)

        foreach ($rows as $r) {
            $invAmt    = (float)($r['invoice_amount']    ?? 0);
            $remainAmt = (float)($r['remaining_amount']  ?? 0); // ⬅️ added
            $perc      = (float)($r['reward_percentage'] ?? 0);
            $reward    = round(($invAmt * $perc) / 100, 2); // NOTE: remaining par chahiye to yahan change karo

            $mapped[] = [
                'invoice_no'        => (string)($r['invoice_no'] ?? ''),
                'invoice_date'      => (string)($r['invoice_date'] ?? ''),
                'invoice_amount'    => $invAmt,
                'remaining_amount'  => $remainAmt,           // ⬅️ added
                'reward_percentage' => $perc,
                'reward_amount'     => $reward,
                'last_payment_date' => (string)($r['last_payment_date'] ?? ''),
            ];

            $totalReward    += $reward;
            $totalRemaining += $remainAmt; // ⬅️ added
        }

        $rewardAmount  = round($totalReward, 2);
        $last_dr_or_cr = 'Cr';

        // party address + dues
        $address = Address::where('acc_code', $party_code)->first([
            'company_name', 'address', 'address_2', 'postal_code', 'due_amount', 'overdue_amount'
        ]);

        $dueAmount      = (float) ($address->due_amount      ?? 0);
        $overdueAmount  = (float) ($address->overdue_amount  ?? 0);

        $safeParty = preg_replace('/[^A-Za-z0-9_\-]/', '', $party_code);
        $fileName  = 'earlypayment_party_' . $safeParty . '_' . time() . '.pdf';
        $dirPath   = public_path('reward_pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dirPath);
        $filePath  = $dirPath . DIRECTORY_SEPARATOR . $fileName;
        
        $previousDue = (int)($dueAmount)-(int)($totalRemaining);

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('early_payment_reminder');

        \PDF::loadView('backend.invoices.early_payment_pdf', [
            'party_code'     => $party_code,
            'rows'           => $mapped,
            'rewardAmount'   => $rewardAmount,
            'last_dr_or_cr'  => $last_dr_or_cr,
            'address'        => $address,
            'dueAmount'      => $dueAmount,
            'overdueAmount'  => $overdueAmount,
            'totalRemaining' => $totalRemaining, // ⬅️ optional total for footer
            'previousDue' => $previousDue,
            'pdfContentBlock'  => $pdfContentBlock,
        ])->save($filePath);

        return url('public/reward_pdf/' . $fileName);
    }


    private function sendWhatsAppReminder($party_code, $payment_url, $pdfUrl = '', $fileName= '') {
        try {
            // 1) Extract URL token for the button (...) /paymentlinks/{TOKEN}
            $button_variable_encode_part = $payment_url;
            if (preg_match('~/paymentlinks/([^/?#]+)~', $payment_url, $m)) {
                $button_variable_encode_part = $m[1];
            }

            // 2) Normalize party code if you use trimmed codes anywhere
            $partyKey = $party_code;
            if (method_exists($this, 'getTrimmedPartyCode')) {
                $partyKey = $this->getTrimmedPartyCode($party_code);
            }

            // 3) Load customer (Address) — required for name & phone
            /** @var \App\Models\Address|null $customerData */
            $customerData = Address::where('acc_code', $partyKey)->first()
                ?: Address::where('acc_code', $party_code)->first();

            if (!$customerData) {
                \Log::error("WA Reminder: Address not found for party_code={$party_code}");
                return;
            }

            // 4) Format fields
            $customerName = trim((string)($customerData->company_name ?? '')) ?: 'Customer';

            // Phone sanitize → +91XXXXXXXXXX if 10 digits
            $to = (string) ($customerData->phone ?? '');
            $digits = preg_replace('/\D+/', '', $to);
            if (strlen($digits) === 10) {
                $to = '+91' . $digits;
            } elseif ($digits !== '' && $digits[0] !== '+') {
                $to = '+' . $digits;
            } else {
                $to = $digits;
            }
            if (empty($to)) {
                \Log::warning("WA Reminder: No phone for party_code={$party_code}");
                return;
            }

            // 5) Ensure header PDF filename (template expects a DOCUMENT header)
            if ($fileName === '' && $pdfUrl !== '') {
                $path = parse_url($pdfUrl, PHP_URL_PATH);
                if (is_string($path)) {
                    $base = basename($path);
                    $fileName = $base !== '' ? (stripos($base, '.pdf') !== false ? $base : ($base . '.pdf'))
                                             : 'early_payment_' . preg_replace('/\W+/', '', $party_code) . '.pdf';
                } else {
                    $fileName = 'early_payment_' . preg_replace('/\W+/', '', $party_code) . '.pdf';
                }
            }

            // 6) Party totals ({{2}} = TOTAL REMAINING, {{3}} = TOTAL REWARD)
            $todayIst = Carbon::now('Asia/Kolkata')->toDateString();

            // {{2}} = Party TOTAL remaining (prefer helper; else rows; else DB fallback)
            if (method_exists($this, 'sumTotalRemainingForParty')) {
                $partyRemainingTotal = (float) $this->sumTotalRemainingForParty($partyKey, $todayIst);
            } else if (method_exists($this, 'buildAllRowsForParty')) {
                $partyRemainingTotal = 0.0;
                $rows = (array) $this->buildAllRowsForParty($partyKey, $todayIst);
                foreach ($rows as $row) {
                    $partyRemainingTotal += (float) ($row['remaining_amount'] ?? 0);
                }
                $partyRemainingTotal = round($partyRemainingTotal, 2);
            } else {
                // Fallback: table sum for unpaid
                $partyRemainingTotal = (float) RewardRemainderEarlyPayment::query()
                    ->where('party_code', $partyKey)
                    ->where('payment_status', 'Unpaid')
                    ->sum('remaining_amount');
                $partyRemainingTotal = round($partyRemainingTotal, 2);
            }

            // {{3}} = Total reward (same as your PDF logic)
            if (method_exists($this, 'sumTotalRewardForPartyOrGroup')) {
                $totalRewardForParty = (float) $this->sumTotalRewardForPartyOrGroup($partyKey, $todayIst);
            } else {
                // Fallback via rows
                $totalRewardForParty = 0.0;
                if (method_exists($this, 'buildAllRowsForParty')) {
                    $rows = (array) $this->buildAllRowsForParty($partyKey, $todayIst);
                    foreach ($rows as $row) {
                        $invAmt = (float)($row['invoice_amount']    ?? 0);
                        $perc   = (float)($row['reward_percentage'] ?? 0);
                        $totalRewardForParty += round(($invAmt * $perc) / 100, 2);
                        // If you calculate reward on remaining instead, swap line above with:
                        // $totalRewardForParty += round(((float)($row['remaining_amount'] ?? 0) * $perc) / 100, 2);
                    }
                }
                $totalRewardForParty = round($totalRewardForParty, 2);
            }

            // 7) Build WhatsApp template payload (3 body params)
            $components = [];

            // Header: Document (only include if you have a URL; template must match)
            if ($pdfUrl !== '') {
                $components[] = [
                    'type' => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => [
                            'link'     => $pdfUrl,
                            'filename' => $fileName,
                        ],
                    ]],
                ];
            }

            // Body: {{1}} name, {{2}} TOTAL REMAINING, {{3}} total reward
            $components[] = [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],                                     // {{1}}
                    ['type' => 'text', 'text' => number_format($partyRemainingTotal, 2, '.', '')],   // {{2}}  ✅ remaining total
                    ['type' => 'text', 'text' => number_format($totalRewardForParty, 2, '.', '')],   // {{3}}
                ],
            ];

            // Button: URL with token (template requires it)
            if (!empty($button_variable_encode_part)) {
                $components[] = [
                    'type'       => 'button',
                    'sub_type'   => 'url',
                    'index'      => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $button_variable_encode_part],
                    ],
                ];
            }

            $templateData = [
                'name'       => 'early_payment_remainder_2', // <-- your approved template name
                'language'   => 'en',
                'components' => $components,
            ];

            // 8) Send
            $whatsApp = new WhatsAppWebService(); // adjust namespace if needed
            $response = $whatsApp->sendTemplateMessage($to, $templateData);


            // 9) ✅ If WhatsApp accepted (messages[0].id exists), mark party rows as processed
            $messageId = null;
            if (is_array($response)) {
                // Laravel helper safely digs into nested arrays
                $messageId = data_get($response, 'messages.0.id');
            } elseif (is_object($response)) {
                // if service returns an object
                $messageId = data_get((array) $response, 'messages.0.id');
            }

            if (is_string($messageId) && $messageId !== '') {
                // Mark only not-yet-processed rows for this party as processed
                RewardRemainderEarlyPayment::where('party_code', $partyKey)
                    ->update(['is_processed' => 1, 'updated_at' => now()]);

                
                // \Log::info("WA OK for {$partyKey}, msg={$messageId} → is_processed=1 set.");
            } else {
                \Log::warning("WA send did not return message id for party {$partyKey}. Not marking processed. Resp=" . json_encode($response));
            }

            return $response;

            // \Log::info("WA sent to {$to} for party {$partyKey}: " . json_encode($response));

        } catch (\Throwable $e) {
            \Log::error("sendWhatsAppReminder failed for party_code={$party_code}: " . $e->getMessage());
        }
    }


    private function sumTotalRemainingForParty(string $party_code, string $asOfDate = null): float
    {
        // this function return total remaining amount
        try {
            $asOfDate = $asOfDate ?: Carbon::now('Asia/Kolkata')->toDateString();

            if (method_exists($this, 'buildAllRowsForParty')) {
                // rows: same structure jo aap PDF ko dete ho
                $rows = $this->buildAllRowsForParty($party_code, $asOfDate);
            } else {
                // Fallback: table se unpaid rows
                $rows = RewardRemainderEarlyPayment::query()
                    ->where('party_code', $party_code)
                    ->where('payment_status', 'Unpaid')
                    ->get()
                    ->map(fn($r) => ['remaining_amount' => (float)$r->remaining_amount])
                    ->all();
            }

            $sum = 0.0;
            foreach ($rows as $row) {
                $sum += (float) ($row['remaining_amount'] ?? 0);
            }
            return round($sum, 2);

        } catch (\Throwable $e) {
            \Log::error("sumTotalRemainingForParty failed for {$party_code}: " . $e->getMessage());
            return 0.0;
        }
    }

    private function sumTotalRewardForPartyOrGroup(string $party_code, ?string $asOfDate = null): float
    {
        try {
            $asOfDate = $asOfDate ?: Carbon::now('Asia/Kolkata')->toDateString();

            if (method_exists($this, 'buildAllRowsForParty')) {
                $rows = $this->buildAllRowsForParty($party_code, $asOfDate);
            } else {
                // Fallback: agar builder available nahi hai
                $rows = RewardRemainderEarlyPayment::query()
                    ->where('party_code', $party_code)
                    ->where('payment_status', 'Unpaid')
                    ->get()
                    ->map(function ($r) use ($asOfDate) {
                        $percent = 0.0;
                        if (method_exists($this, 'earlyPayMetaForToday')) {
                            $txDate = Carbon::parse($r->invoice_date);
                            $meta   = $this->earlyPayMetaForToday($txDate, (int)$r->reminder_sent, $asOfDate);
                            if ($meta !== null) {
                                $percent = (float) $meta[0]; // [rewardPercent, lastDate, cycle]
                            }
                        }
                        return [
                            'invoice_amount'    => (float)$r->invoice_amount,
                            'remaining_amount'  => (float)$r->remaining_amount,
                            'reward_percentage' => (float)$percent,
                        ];
                    })
                    ->all();
            }

            $sum = 0.0;
            foreach ($rows as $row) {
                $invAmt = (float) ($row['invoice_amount'] ?? 0);
                $perc   = (float) ($row['reward_percentage'] ?? 0);

                // === PDF ki EXACT calculation ===
                $reward = round(($invAmt * $perc) / 100, 2);

                // NOTE: agar remaining par chahiye to upar waali line comment karke:
                // $reward = round((($row['remaining_amount'] ?? 0) * $perc) / 100, 2);

                $sum += $reward;
            }
            return round($sum, 2);

        } catch (\Throwable $e) {
            \Log::error("sumTotalRewardForPartyOrGroup failed for {$party_code}: " . $e->getMessage());
            return 0.0;
        }
    }


    public function claimReward(Request $request, string $partyCode)
    {
        /* This function runs when the Claim Reward button is clicked on WhatsApp */

        // 1) Customer by acc_code
        $address = Address::with('state')->where('acc_code', $partyCode)->first();
        if (!$address) {
            return redirect()->back()->with('error', "Invalid party code: {$partyCode}");
        }

        // 2) Collect ONLY pending Early Payment rows (reward_complete_status = 0)
        $pendingRows = RewardPointsOfUser::query()
            ->where('party_code', $partyCode)
            ->where(function ($w) {
                // $w->where('rewards_from', 'Early Payment')
                //   ->orWhere('rewards_from', 'like', '%Early Payment%');
                 $w->where('rewards_from', 'like', '%Early Payment%')
                  ->orWhere('rewards_from', 'like', '%Offer%')
                  ->orWhere('rewards_from', 'like', '%Manual%');
            })
            ->where('reward_complete_status', 0)   // ✅ only status=0 can be claimed
            ->get(['id', 'rewards']);

        $pendingIds   = $pendingRows->pluck('id')->all();
        $rewardTotal  = (float) $pendingRows->sum('rewards');

        if ($rewardTotal <= 0) {
            return redirect()->back()->with('error', 'No reward balance available to claim.');
        }

        // 3) Resolve warehouse — derive from users table (no request param)
        $warehouseId = 0;

        // a) Try users.warehouse_id (if column exists)
        $userId = (int) ($address->user_id ?? 0);
        if ($userId > 0 && Schema::hasColumn('users', 'warehouse_id')) {
            $warehouseId = (int) (User::where('id', $userId)->value('warehouse_id') ?? 0);
        }

        // b) If not found, find a warehouse owned by this user
        if ($warehouseId <= 0 && $userId > 0) {
            $warehouseId = (int) (Warehouse::where('user_id', $userId)->value('id') ?? 0);
        }

        // c) Final fallback: first active, else any
        if ($warehouseId <= 0) {
            $warehouseId = (int) (Warehouse::where('is_active', 1)->value('id') ?? Warehouse::value('id'));
        }

        if ($warehouseId <= 0) {
            return redirect()->back()->with('error', 'No warehouse configured for service credit notes.');
        }

        // 4) Build synthetic request for SERVICE credit note
        $fake = new Request();
        $fake->replace([
            'warehouse_id'        => $warehouseId,
            'address_id'          => $address->id,
            'seller_invoice_no'   => 'SRV-REWARD-' . $partyCode . '-' . now()->format('Ymd-His'),
            'seller_invoice_date' => now('Asia/Kolkata')->toDateString(),
            'credit_note_type'    => 'service',                 // triggers insertServiceData()
            'note'                => 'Cash Discount Reward',
            'sac_code'            => '996511',
            'rate'                => (float) $rewardTotal,      // GST-inclusive per your service flow
            'quantity'            => 1,
        ]);

        // 5) Internally call PurchaseOrderController
        /** @var PurchaseOrderController $poc */
        $poc = app(PurchaseOrderController::class);
        $creditNoteNumber = $poc->saveManualPurchaseOrderCustomerFoReward($fake);

        // 6) Mark ONLY those pending rows as claimed (status 1)
        if (!empty($pendingIds)) {
            RewardPointsOfUser::whereIn('id', $pendingIds)
                ->update([
                    'reward_complete_status' => 1,
                    'updated_at'             => now(),
                ]);

        }

        $response=$this->sendRewardClaimedWhatsApp($partyCode, $rewardTotal, $creditNoteNumber);
         

        return redirect()->to('users/login'); // redirect from PurchaseOrderController
    }

    private function sendRewardClaimedWhatsApp( $partyCode,  $amount,  $creditNoteNumber = null)
    {
        /* Reward Claimed Success Message */

        $purchaseInvoiceId = PurchaseInvoice::where('credit_note_number', $creditNoteNumber)
          ->value('id'); 
        // 1) Customer
        $addr = Address::where('acc_code', $partyCode)->first();
        $pdfUrlRewardStatement = app(\App\Http\Controllers\RewardController::class)->getRewardPdfURL($partyCode);
        $rewardFileName = basename(parse_url($pdfUrlRewardStatement, PHP_URL_PATH));

        if (!$addr) {
            \Log::warning("WA RewardClaimed: Address not found for party_code={$partyCode}");
            return;
        }

        $customerName = trim((string)($addr->company_name ?? '')) ?: 'Customer';

        // 2) Phone sanitize
        $to = (string) ($addr->phone ?? '');
        $digits = preg_replace('/\D+/', '', $to);
        if (strlen($digits) === 10)      $to = '+91' . $digits;
        elseif ($digits !== '' && $digits[0] !== '+') $to = '+' . $digits;
        else                              $to = $digits;
        if ($to === '') {
            \Log::warning("WA RewardClaimed: No phone for party_code={$partyCode}");
            return;
        }

        // 3) Build PDF link from your named route (direct URL)
        $pdfUrl = app(PurchaseOrderController::class)->getCreditNoteInvoicePDFURL($purchaseInvoiceId);
        $fileName = 'reward_claim_' . preg_replace('/\W+/', '', $partyCode) . '.pdf';

        // 4) Template payload (header: document + body params)
        $templateData = [
            'name'       => 'reward_claimed_success',
            'language'   => 'en_US',
            'components' => [
                [
                    'type' => 'header',
                    'parameters' => [[
                        'type'     => 'document',
                        'document' => [
                            'link'     => $pdfUrl,    // direct, publicly fetchable URL
                            'filename' => $fileName,
                        ],
                    ]],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $customerName],                           // {{1}}
                        ['type' => 'text', 'text' => number_format((float)$amount, 2, '.', '')], // {{2}}
                        ['type' => 'text', 'text' => (string) ($creditNoteNumber ?: 'N/A')],   // {{3}}
                    ],
                ],

                [
                        'type'       => 'button',
                        'sub_type'   => 'url',
                        'index'      => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $rewardFileName], 
                        ],
                ],
            ],
        ];

        // 5) Send
        try {
            $svc = new WhatsAppWebService(); // adjust namespace if needed
            $response=$svc->sendTemplateMessage($to, $templateData);
            
        } catch (\Throwable $e) {
            \Log::error("WA RewardClaimed send failed for {$partyCode}: " . $e->getMessage());
        }
    }


    private function RewardPointOfUserTotalRewards(string $partyCode, bool $onlyUnclaimed = true): float
    {
        $q = DB::table('reward_points_of_users')
            ->where('party_code', $partyCode)
            ->where(function ($w) {
                $w->where('rewards_from', 'Early Payment')
                  ->orWhere('rewards_from', 'like', '%Early Payment%');
            });

        if ($onlyUnclaimed) {
            $q->where(function ($w) {
                $w->whereNull('reward_complete_status')
                  ->orWhere('reward_complete_status', 0);
            });
        }

        return round((float) $q->sum('rewards'), 2);
    }


    public function sendClaimRewardWhatsAppForParty(Request $request, string $partyCode)
{
    // 0) Normalize incoming party code
    $partyCode = trim($partyCode);

    if ($partyCode === '') {
        return response()->json(['ok' => false, 'message' => 'Invalid party code'], 422);
    }

    // 1) Load customer address (for name & phone)
    $addr = Address::where('acc_code', $partyCode)->first();
    if (!$addr) {
        \Log::warning("WA ClaimReward(Single): Address not found for party_code={$partyCode}");
        return response()->json(['ok' => false, 'message' => 'Address not found'], 404);
    }

    // 2) Name & phone sanitize
    $customerName = trim((string)($addr->company_name ?? '')) ?: 'Customer';

    $to = (string) ($addr->phone ?? '');
    $digits = preg_replace('/\D+/', '', $to);
    if (strlen($digits) === 10) {
        $to = '+91' . $digits;
    } elseif ($digits !== '' && $digits[0] !== '+') {
        $to = '+' . $digits;
    } else {
        $to = $digits;
    }
    if ($to === '') {
        \Log::warning("WA ClaimReward(Single): No phone for party_code={$partyCode}");
        return response()->json(['ok' => false, 'message' => 'No phone found'], 422);
    }

    // 3) Total pending reward (Early Payment + Offer + Manual) with status=0
    $totalReward = (float) DB::table('reward_points_of_users')
        ->where('party_code', $partyCode)
        ->where(function ($w) {
            $w->where('rewards_from', 'like', '%Early Payment%')
              ->orWhere('rewards_from', 'like', '%Offer%')
              ->orWhere('rewards_from', 'like', '%Manual%');
        })
        ->where('reward_complete_status', 0)
        ->sum('rewards');

    if ($totalReward <= 0) {
        return response()->json(['ok' => false, 'message' => 'No pending rewards'], 200);
    }

    // 4) Build PDF URL for this party
    $pdfUrl = app(\App\Http\Controllers\RewardController::class)->getRewardPdfURL($partyCode);

    // 5) WhatsApp template payload (claim_reward_cta)
    $templateData = [
        'name'       => 'claim_reward_cta',
        'language'   => 'en_US',
        'components' => [
            [
                'type' => 'header',
                'parameters' => [[
                    'type'     => 'document',
                    'document' => [
                        'link'     => $pdfUrl,
                        'filename' => 'Reward Statement '.time(),
                    ],
                ]],
            ],
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $customerName],                          // {{1}}
                    ['type' => 'text', 'text' => number_format($totalReward, 2, '.', '')] // {{2}}
                ],
            ],
            [
                'type'       => 'button',
                'sub_type'   => 'url',
                'index'      => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $partyCode], // button variable (party code)
                ],
            ],
        ],
    ];

    try {
        // 6) Send
        $wa   = new WhatsAppWebService(); // ← adjust namespace if needed
        $resp = $wa->sendTemplateMessage($to, $templateData);

        // 7) Extract msg_id from WA Cloud API-like responses
      
        $msgId = data_get($resp, 'messages.0.id')
              ?? data_get($resp, 'data.messages.0.id')
              ?? data_get($resp, '0.id')
              ?? null;

        // 8) Store msg_id on reward rows (pending ones for this party)
        if ($msgId) {
            // If you only want to tag the *latest* pending rows, tweak the where
            RewardPointsOfUser::where('party_code', $partyCode)
                ->where(function ($w) {
                    $w->where('rewards_from', 'like', '%Early Payment%')
                      ->orWhere('rewards_from', 'like', '%Offer%')
                      ->orWhere('rewards_from', 'like', '%Manual%');
                })
                ->where('reward_complete_status', 0)
                ->update(['msg_id' => $msgId, 'updated_at' => now()]);
        } else {
            \Log::warning("WA ClaimReward(Single): msg_id missing for {$partyCode}", ['resp' => $resp]);
        }

        

        \Log::info("WA ClaimReward(Single) sent to {$partyCode} ({$to}) msg_id={$msgId}");
        return response()->json(['ok' => true, 'message' => 'Sent', 'to' => $to, 'msg_id' => $msgId]);
    } catch (\Throwable $e) {
        \Log::error("WA ClaimReward(Single) failed for {$partyCode}: ".$e->getMessage());
        return response()->json(['ok' => false, 'message' => 'Send failed'], 500);
    }
}


    public function sendClaimRewardWhatsAppBulk()
    {
        // 1) DISTINCT party codes with pending rewards (Early Payment + Offer + Manual)
        $partyCodes = DB::table('reward_points_of_users')
            ->whereNotNull('party_code')
            ->where(function ($w) {
                $w->where('rewards_from', 'like', '%Early Payment%')
                  ->orWhere('rewards_from', 'like', '%Offer%')
                  ->orWhere('rewards_from', 'like', '%Manual%');
            })
            ->where('reward_complete_status', 0)
            ->distinct()
            ->pluck('party_code')
            ->filter()
            ->values();

        if ($partyCodes->isEmpty()) {
            \Log::info('WA ClaimReward(Bulk): No parties with pending rewards.');
            return response()->json(['ok' => true, 'message' => 'No pending parties'], 200);
        }

        $wa = new WhatsAppWebService(); // adjust namespace if needed

        $stats = [
            'total_parties' => $partyCodes->count(),
            'sent'          => 0,
            'skipped'       => 0,
            'errors'        => 0,
            'details'       => [],
        ];

        foreach ($partyCodes as $partyCode) {
            try {
                // 2) Load customer (address/phone)
                $addr = Address::where('acc_code', $partyCode)->first();
                if (!$addr) {
                    \Log::warning("WA ClaimReward(Bulk): Address not found for party_code={$partyCode}");
                    $stats['skipped']++;
                    $stats['details'][] = ['party' => $partyCode, 'result' => 'no_address'];
                    continue;
                }

                // 3) Name & phone sanitize
                $customerName = trim((string)($addr->company_name ?? '')) ?: 'Customer';

                $to = (string) ($addr->phone ?? '');
                $digits = preg_replace('/\D+/', '', $to);
                if (strlen($digits) === 10) {
                    $to = '+91' . $digits;
                } elseif ($digits !== '' && $digits[0] !== '+') {
                    $to = '+' . $digits;
                } else {
                    $to = $digits;
                }
                if ($to === '') {
                    \Log::warning("WA ClaimReward(Bulk): No phone for party_code={$partyCode}");
                    $stats['skipped']++;
                    $stats['details'][] = ['party' => $partyCode, 'result' => 'no_phone'];
                    continue;
                }

                // 4) Total pending reward for this party
                $totalReward = (float) DB::table('reward_points_of_users')
                    ->where('party_code', $partyCode)
                    ->where(function ($w) {
                        $w->where('rewards_from', 'like', '%Early Payment%')
                          ->orWhere('rewards_from', 'like', '%Offer%')
                          ->orWhere('rewards_from', 'like', '%Manual%');
                    })
                    ->where('reward_complete_status', 0)
                    ->sum('rewards');

                if ($totalReward <= 0) {
                    $stats['skipped']++;
                    $stats['details'][] = ['party' => $partyCode, 'result' => 'no_pending_rewards'];
                    continue;
                }

                // 5) Build PDF + template payload
                $pdfUrl = app(\App\Http\Controllers\RewardController::class)->getRewardPdfURL($partyCode);

                $templateData = [
                    'name'       => 'claim_reward_cta',
                    'language'   => 'en_US',
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [[
                                'type'     => 'document',
                                'document' => [
                                    'link'     => $pdfUrl,
                                    'filename' => 'Reward Statement ' . time(),
                                ],
                            ]],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $customerName],                             // {{1}}
                                ['type' => 'text', 'text' => number_format($totalReward, 2, '.', '')],  // {{2}}
                            ],
                        ],
                        [
                            'type'       => 'button',
                            'sub_type'   => 'url',
                            'index'      => '0',
                            'parameters' => [
                                ['type' => 'text', 'text' => $partyCode], // button variable = party code
                            ],
                        ],
                    ],
                ];

                // 6) Send (FIX: use actual $to; remove hard-coded number)
                $resp = $wa->sendTemplateMessage($to, $templateData);

                // 7) Extract msg_id (handle common shapes)
                $msgId = data_get($resp, 'messages.0.id')
                      ?? data_get($resp, 'data.messages.0.id')
                      ?? data_get($resp, '0.id')
                      ?? null;

                if (!$msgId) {
                    \Log::warning("WA ClaimReward(Bulk): msg_id missing for {$partyCode}", ['resp' => $resp]);
                }

                // 8) Store msg_id on pending rewards for this party
                RewardPointsOfUser::where('party_code', $partyCode)
                    ->where(function ($w) {
                        $w->where('rewards_from', 'like', '%Early Payment%')
                          ->orWhere('rewards_from', 'like', '%Offer%')
                          ->orWhere('rewards_from', 'like', '%Manual%');
                    })
                    ->where('reward_complete_status', 0)
                    ->update([
                        'msg_id'     => $msgId,           // may be null if API didn’t return; webhook can backfill
                        'updated_at' => now(),
                    ]);

                

                \Log::info("WA ClaimReward(Bulk) sent to {$partyCode} ({$to}) msg_id={$msgId}");
                $stats['sent']++;
                $stats['details'][] = ['party' => $partyCode, 'result' => 'sent', 'msg_id' => $msgId, 'to' => $to];
            } catch (\Throwable $e) {
                \Log::error("WA ClaimReward(Bulk) failed for {$partyCode}: " . $e->getMessage());
                $stats['errors']++;
                $stats['details'][] = ['party' => $partyCode, 'result' => 'error', 'error' => $e->getMessage()];
                // continue with next party
            }
        }

        return response()->json(['ok' => true, 'summary' => $stats], 200);
    }

    


public function back_earlyPaymentReminderIndex(Request $request)
{
    $q         = trim((string) $request->get('search', ''));
    $perPage   = (int) ($request->get('per_page', 25));
    $processed = trim((string) $request->get('processed', '')); // "" | "1" | "0"

    // ---------- helpers ----------
    $norm = function ($s) {
        $s = strtoupper((string)$s);
        return preg_replace('/[ \t\n\r\/\-\._]+/', '', $s);
    };
    $lastToken = function ($s) {
        $s = trim((string)$s);
        $parts = preg_split('/[\/\-\s_\.]+/', $s);
        $parts = array_values(array_filter($parts, fn($x) => $x !== null && $x !== ''));
        if (empty($parts)) return strtoupper($s);
        $last = strtoupper(end($parts));
        if (preg_match('/([A-Z0-9]+)$/i', $last, $m)) {
            return strtoupper($m[1]);
        }
        return $last;
    };

    // ---------- base (grouped per party) ----------
    $base = RewardRemainderEarlyPayment::query()
        ->from('reward_remainder_early_payments as r')
        ->leftJoin('users as u', function ($j) {
            $j->on(\DB::raw('BINARY u.party_code'), '=', \DB::raw('BINARY r.party_code'));
        })
        ->leftJoin('addresses as a', function ($j) {
            $j->on(\DB::raw('BINARY a.acc_code'), '=', \DB::raw('BINARY r.party_code'));
        })
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('r.party_code', 'like', "%{$q}%")
                  ->orWhere('u.name', 'like', "%{$q}%")
                  ->orWhere('u.phone', 'like', "%{$q}%");
            });
        })
        ->groupBy('r.party_code')
        ->selectRaw("
            r.party_code                                   as party_code,
            COALESCE(MAX(u.name), '')                      as party_name,
            COALESCE(MAX(u.phone), '')                     as party_phone,
            COALESCE(MAX(a.due_amount), 0)                 as due_amount,
            COALESCE(MAX(a.overdue_amount), 0)             as overdue_amount,
            COUNT(*)                                       as invoice_count,
            SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) AS unprocessed_count
        ")
        ->when($processed !== '', function ($qq) use ($processed) {
            if ($processed === '1') {
                $qq->havingRaw('SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) = 0');
            } elseif ($processed === '0') {
                $qq->havingRaw('SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) > 0');
            }
        });

    $parties = $base->orderBy('r.party_code')
        ->paginate($perPage)
        ->appends($request->query());

    // ---------- all invoice rows for parties on this page ----------
    $codesOnPage = $parties->pluck('party_code')->filter()->values()->all();

    $invoiceRows = RewardRemainderEarlyPayment::query()
        ->whereIn('party_code', $codesOnPage)
        ->orderBy('invoice_date', 'desc')
        ->get([
            'id', 'party_code', 'msg_id',              // msg_id is important for party-level WA
            'invoice_no','invoice_date','invoice_amount','remaining_amount',
            'payment_status','reminder_sent','is_processed'
        ]);

    // ---------- map: invoice_no -> invoice_orders.id (best-effort) ----------
    $allInvoiceNos = $invoiceRows->pluck('invoice_no')->filter()->unique()->values()->all();
    $mapNormalized = [];

    if (!empty($allInvoiceNos)
        && \Illuminate\Support\Facades\Schema::hasTable('invoice_orders')
        && \Illuminate\Support\Facades\Schema::hasColumn('invoice_orders', 'invoice_no')) {

        try {
            // exact match
            $exact = \DB::table('invoice_orders')
                ->whereIn('invoice_no', $allInvoiceNos)
                ->select(['id', 'invoice_no'])
                ->get();

            foreach ($exact as $r) {
                if (!empty($r->invoice_no)) {
                    $key = $norm($r->invoice_no);
                    $mapNormalized[$key] = (int) $r->id;
                    $tok = $lastToken($r->invoice_no);
                    if (!isset($mapNormalized[$tok])) $mapNormalized[$tok] = (int) $r->id;
                }
            }

            // fuzzy for still missing
            $needFuzzy = [];
            foreach ($allInvoiceNos as $raw) {
                if (!isset($mapNormalized[$norm($raw)]) && !isset($mapNormalized[$lastToken($raw)])) {
                    $needFuzzy[] = $raw;
                }
            }

            if (!empty($needFuzzy)) {
                $tokens = array_unique(array_map($lastToken, $needFuzzy));

                $q2 = \DB::table('invoice_orders')
                    ->select(['id','invoice_no'])
                    ->where(function($w) use ($tokens) {
                        foreach ($tokens as $t) $w->orWhere('invoice_no', 'like', '%' . $t);
                    })
                    ->limit(1000)
                    ->get();

                foreach ($q2 as $r) {
                    if (!empty($r->invoice_no)) {
                        $kNorm = $norm($r->invoice_no);
                        if (!isset($mapNormalized[$kNorm])) $mapNormalized[$kNorm] = (int) $r->id;
                        $tok = $lastToken($r->invoice_no);
                        if (!isset($mapNormalized[$tok])) $mapNormalized[$tok] = (int) $r->id;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore mapping errors
        }
    }

    // ========================
    // PARTY-LEVEL: msg_ids list & latest WA status (direct from cloud_responses)
    // ========================
    // 1) Gather msg_ids per party from current page invoices
    $msgIdsByParty = $invoiceRows
        ->groupBy('party_code')
        ->map(function ($rows) {
            return $rows->pluck('msg_id')
                ->filter(fn($m) => is_string($m) && trim($m) !== '')
                ->unique()
                ->values();
        });

    // 2) For those msg_ids, pick latest cloud_responses row (max id per msg)
    $allMsgIds = $msgIdsByParty->flatten()->unique()->values();

    $latestRowPerMsg = collect(); // msg_id => row
    if ($allMsgIds->isNotEmpty()) {
        $latestIdPerMsg = \DB::table('cloud_responses')
            ->whereIn('msg_id', $allMsgIds)
            ->groupBy('msg_id')
            ->selectRaw('msg_id, MAX(id) as max_id')
            ->pluck('max_id', 'msg_id'); // msg_id => id

        if ($latestIdPerMsg->isNotEmpty()) {
            $latestRowPerMsg = \DB::table('cloud_responses')
                ->whereIn('id', $latestIdPerMsg->values())
                ->get(['id','msg_id','status','created_at'])
                ->keyBy('msg_id'); // msg_id => row
        }
    }

    // 3) Reduce to party-level: latest by created_at across that party's msg_ids
    //    (also keep which msg_id won → to expose as party-level msg_id)
    $partyWa = []; // party_code => ['status'=>..., 'created_at'=>..., 'msg_id'=>...]
    foreach ($msgIdsByParty as $partyCode => $ids) {
        $bestRow = $ids
            ->map(fn($m) => $latestRowPerMsg->get($m))
            ->filter()
            ->sortByDesc('created_at')
            ->first();

        if ($bestRow) {
            $partyWa[$partyCode] = [
                'status'     => strtolower(trim((string) $bestRow->status)),
                'created_at' => (string) $bestRow->created_at,
                'msg_id'     => (string) $bestRow->msg_id,
            ];
        }
    }

    // ---------- constants ----------
    $detailsByParty = $invoiceRows->groupBy('party_code');
    $today = \Carbon\Carbon::now('Asia/Kolkata')->toDateString();

    $parties->setCollection(
        $parties->getCollection()->map(function ($row) use ($detailsByParty, $msgIdsByParty, $partyWa, $mapNormalized, $norm, $lastToken, $today) {

            $partyCode = (string) $row->party_code;

            // ---------- party invoices ----------
            // (invoice level me wa_status/wa_status_at NAHI rakhna)
            $invoices = ($detailsByParty[$partyCode] ?? collect())->map(function ($r) use ($mapNormalized, $norm, $lastToken) {
                $invNo     = (string) $r->invoice_no;
                $keyNorm   = $norm($invNo);
                $keyTok    = $lastToken($invNo);
                $invoiceId = $mapNormalized[$keyNorm] ?? ($mapNormalized[$keyTok] ?? null);

                return [
                    'invoice_no'       => $invNo,
                    'invoice_id'       => $invoiceId ? (int) $invoiceId : null,
                    'invoice_date'     => (string) $r->invoice_date,
                    'invoice_amount'   => (float)  $r->invoice_amount,
                    'remaining_amount' => (float)  $r->remaining_amount,
                    'payment_status'   => (string) $r->payment_status,
                    'reminder_sent'    => (int)    $r->reminder_sent,
                    'is_processed'     => (int)   ($r->is_processed ?? 0),
                    // ❌ NO wa_status/wa_status_at here
                ];
            })->values()->all();

            $row->invoices = $invoices;

            // ---------- party-level processed ----------
            $invCol = collect($invoices);
            $row->is_processed = $invCol->isNotEmpty() && $invCol->every(fn($it) => (int)($it['is_processed'] ?? 0) === 1) ? 1 : 0;

            // ---------- party-level latest msg_id + WA status ----------
            if (isset($partyWa[$partyCode])) {
                $row->msg_id       = (string) $partyWa[$partyCode]['msg_id'];
                $row->wa_status    = (string) $partyWa[$partyCode]['status'];
                $row->wa_status_at = (string) $partyWa[$partyCode]['created_at'];
            } else {
                $row->msg_id       = null;
                $row->wa_status    = '';
                $row->wa_status_at = null;
            }

            // ---------- Optional: PDF URL (guarded) ----------
            if (method_exists($this, 'buildAllRowsForParty')) {
                try {
                    $rowsForParty = (array) $this->buildAllRowsForParty($partyCode, $today);
                } catch (\Throwable $e) { $rowsForParty = []; }
            } else {
                $rowsForParty = [];
            }

            if (!empty($rowsForParty) && method_exists($this, 'generatePartyEarlyPaymentPdf')) {
                try {
                    $row->reward_pdf_url = (string) $this->generatePartyEarlyPaymentPdf($partyCode, $rowsForParty);
                } catch (\Throwable $e) {
                    $row->reward_pdf_url = '';
                }
            } else {
                $row->reward_pdf_url = '';
            }

            return $row;
        })
    );

    return view('backend.reward.early_payment_remainder', [
        'rows'        => $parties,
        'sort_search' => $q,
        'processed'   => $processed,
    ]);
}




public function earlyPaymentReminderIndex(Request $request)
{
    $q         = trim((string) $request->get('search', ''));
    $perPage   = (int) ($request->get('per_page', 25));
    $processed = trim((string) $request->get('processed', '')); // "" | "1" | "0"

    // ---------- helpers ----------
    $norm = function ($s) {
        $s = strtoupper((string)$s);
        return preg_replace('/[ \t\n\r\/\-\._]+/', '', $s);
    };

    $lastToken = function ($s) {
        $s = trim((string)$s);
        $parts = preg_split('/[\/\-\s_\.]+/', $s);
        $parts = array_values(array_filter($parts, fn($x) => $x !== null && $x !== ''));
        if (empty($parts)) return strtoupper($s);
        $last = strtoupper(end($parts));
        if (preg_match('/([A-Z0-9]+)$/i', $last, $m)) {
            return strtoupper($m[1]);
        }
        return $last;
    };

    // ---------- base (grouped per party) ----------
    $base = RewardRemainderEarlyPayment::query()
        ->from('reward_remainder_early_payments as r')
        ->leftJoin('users as u', function ($j) {
            $j->on(\DB::raw('BINARY u.party_code'), '=', \DB::raw('BINARY r.party_code'));
        })
        ->leftJoin('addresses as a', function ($j) {
            $j->on(\DB::raw('BINARY a.acc_code'), '=', \DB::raw('BINARY r.party_code'));
        })
        ->when($q !== '', function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('r.party_code', 'like', "%{$q}%")
                  ->orWhere('u.name', 'like', "%{$q}%")
                  ->orWhere('u.phone', 'like', "%{$q}%");
            });
        })
        ->groupBy('r.party_code')
        ->selectRaw("
            r.party_code                                   as party_code,
            COALESCE(MAX(u.name), '')                      as party_name,
            COALESCE(MAX(u.phone), '')                     as party_phone,
            COALESCE(MAX(a.due_amount), 0)                 as due_amount,
            COALESCE(MAX(a.overdue_amount), 0)             as overdue_amount,
            COUNT(*)                                       as invoice_count,
            SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) AS unprocessed_count
        ")
        ->when($processed !== '', function ($qq) use ($processed) {
            if ($processed === '1') {
                $qq->havingRaw('SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) = 0');
            } elseif ($processed === '0') {
                $qq->havingRaw('SUM(CASE WHEN COALESCE(r.is_processed,0)=0 THEN 1 ELSE 0 END) > 0');
            }
        });

    $parties = $base->orderBy('r.party_code')
        ->paginate($perPage)
        ->appends($request->query());

    // Agar is page pe koi party hi nahi hai to aage ka heavy logic skip
    if ($parties->isEmpty()) {
        return view('backend.reward.early_payment_remainder', [
            'rows'        => $parties,
            'sort_search' => $q,
            'processed'   => $processed,
        ]);
    }

    // ---------- all invoice rows for parties on this page ----------
    $codesOnPage = $parties->pluck('party_code')->filter()->values()->all();

    $invoiceRows = RewardRemainderEarlyPayment::query()
        ->whereIn('party_code', $codesOnPage)
        ->orderBy('invoice_date', 'desc')
        ->get([
            'id', 'party_code', 'msg_id',              // msg_id is important for party-level WA
            'invoice_no','invoice_date','invoice_amount','remaining_amount',
            'payment_status','reminder_sent','is_processed'
        ]);

    // ---------- map: invoice_no -> invoice_orders.id (best-effort) ----------
    $allInvoiceNos = $invoiceRows->pluck('invoice_no')->filter()->unique()->values()->all();
    $mapNormalized = [];

    if (!empty($allInvoiceNos)
        && \Illuminate\Support\Facades\Schema::hasTable('invoice_orders')
        && \Illuminate\Support\Facades\Schema::hasColumn('invoice_orders', 'invoice_no')) {

        try {
            // exact match
            $exact = \DB::table('invoice_orders')
                ->whereIn('invoice_no', $allInvoiceNos)
                ->select(['id', 'invoice_no'])
                ->get();

            foreach ($exact as $r) {
                if (!empty($r->invoice_no)) {
                    $key = $norm($r->invoice_no);
                    $mapNormalized[$key] = (int) $r->id;
                    $tok = $lastToken($r->invoice_no);
                    if (!isset($mapNormalized[$tok])) {
                        $mapNormalized[$tok] = (int) $r->id;
                    }
                }
            }

            // fuzzy for still missing
            $needFuzzy = [];
            foreach ($allInvoiceNos as $raw) {
                if (!isset($mapNormalized[$norm($raw)]) && !isset($mapNormalized[$lastToken($raw)])) {
                    $needFuzzy[] = $raw;
                }
            }

            if (!empty($needFuzzy)) {
                $tokens = array_unique(array_map($lastToken, $needFuzzy));

                $q2 = \DB::table('invoice_orders')
                    ->select(['id','invoice_no'])
                    ->where(function($w) use ($tokens) {
                        foreach ($tokens as $t) {
                            $w->orWhere('invoice_no', 'like', '%' . $t);
                        }
                    })
                    ->limit(1000)
                    ->get();

                foreach ($q2 as $r) {
                    if (!empty($r->invoice_no)) {
                        $kNorm = $norm($r->invoice_no);
                        if (!isset($mapNormalized[$kNorm])) {
                            $mapNormalized[$kNorm] = (int) $r->id;
                        }
                        $tok = $lastToken($r->invoice_no);
                        if (!isset($mapNormalized[$tok])) {
                            $mapNormalized[$tok] = (int) $r->id;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // mapping errors ignore
        }
    }

    // ========================
    // PARTY-LEVEL: msg_ids list & latest WA status (direct from cloud_responses)
    // ========================
    // 1) Gather msg_ids per party from current page invoices
    $msgIdsByParty = $invoiceRows
        ->groupBy('party_code')
        ->map(function ($rows) {
            return $rows->pluck('msg_id')
                ->filter(fn($m) => is_string($m) && trim($m) !== '')
                ->unique()
                ->values();
        });

    // 2) For those msg_ids, pick latest cloud_responses row (max id per msg)
    $allMsgIds = $msgIdsByParty->flatten()->unique()->values();

    $latestRowPerMsg = collect(); // msg_id => row
    if ($allMsgIds->isNotEmpty()) {
        $latestIdPerMsg = \DB::table('cloud_responses')
            ->whereIn('msg_id', $allMsgIds)
            ->groupBy('msg_id')
            ->selectRaw('msg_id, MAX(id) as max_id')
            ->pluck('max_id', 'msg_id'); // msg_id => id

        if ($latestIdPerMsg->isNotEmpty()) {
            $latestRowPerMsg = \DB::table('cloud_responses')
                ->whereIn('id', $latestIdPerMsg->values())
                ->get(['id','msg_id','status','created_at'])
                ->keyBy('msg_id'); // msg_id => row
        }
    }

    // 3) Reduce to party-level: latest by created_at across that party's msg_ids
    $partyWa = []; // party_code => ['status'=>..., 'created_at'=>..., 'msg_id'=>...]
    foreach ($msgIdsByParty as $partyCode => $ids) {
        $bestRow = $ids
            ->map(fn($m) => $latestRowPerMsg->get($m))
            ->filter()
            ->sortByDesc('created_at')
            ->first();

        if ($bestRow) {
            $partyWa[$partyCode] = [
                'status'     => strtolower(trim((string) $bestRow->status)),
                'created_at' => (string) $bestRow->created_at,
                'msg_id'     => (string) $bestRow->msg_id,
            ];
        }
    }

    // ---------- constants ----------
    $detailsByParty = $invoiceRows->groupBy('party_code');

    $parties->setCollection(
        $parties->getCollection()->map(function ($row) use ($detailsByParty, $partyWa, $mapNormalized, $norm, $lastToken) {

            $partyCode = (string) $row->party_code;

            // ---------- party invoices ----------
            $invoices = ($detailsByParty[$partyCode] ?? collect())->map(function ($r) use ($mapNormalized, $norm, $lastToken) {
                $invNo     = (string) $r->invoice_no;
                $keyNorm   = $norm($invNo);
                $keyTok    = $lastToken($invNo);
                $invoiceId = $mapNormalized[$keyNorm] ?? ($mapNormalized[$keyTok] ?? null);

                return [
                    'invoice_no'       => $invNo,
                    'invoice_id'       => $invoiceId ? (int) $invoiceId : null,
                    'invoice_date'     => (string) $r->invoice_date,
                    'invoice_amount'   => (float)  $r->invoice_amount,
                    'remaining_amount' => (float)  $r->remaining_amount,
                    'payment_status'   => (string) $r->payment_status,
                    'reminder_sent'    => (int)    $r->reminder_sent,
                    'is_processed'     => (int)   ($r->is_processed ?? 0),
                ];
            })->values()->all();

            $row->invoices = $invoices;

            // ---------- party-level processed ----------
            $invCol = collect($invoices);
            $row->is_processed = $invCol->isNotEmpty() && $invCol->every(
                fn($it) => (int)($it['is_processed'] ?? 0) === 1
            ) ? 1 : 0;

            // ---------- party-level latest msg_id + WA status ----------
            if (isset($partyWa[$partyCode])) {
                $row->msg_id       = (string) $partyWa[$partyCode]['msg_id'];
                $row->wa_status    = (string) $partyWa[$partyCode]['status'];
                $row->wa_status_at = (string) $partyWa[$partyCode]['created_at'];
            } else {
                $row->msg_id       = null;
                $row->wa_status    = '';
                $row->wa_status_at = null;
            }

            // ⚠️ IMPORTANT:
            // yahan se PDF generation hata diya hai.
            // Index page pe mPDF / heavy processing nahi karna chahiye,
            // warna har page load pe 20–30 PDF generate honge → timeout/hang.

            $row->reward_pdf_url = ''; // agar chaho to yahan koi route() se URL set kar sakte ho

            return $row;
        })
    );

    

    return view('backend.reward.early_payment_remainder', [
        'rows'        => $parties,
        'sort_search' => $q,
        'processed'   => $processed,
    ]);
}





    public function onlyEarlyPaymentPdfDownload(string $party_code = 'OPEL0100014')
    {
        $today = \Carbon\Carbon::now('Asia/Kolkata')->toDateString();

        $rows = (array) $this->buildAllRowsForParty($party_code, $today);
        if (empty($rows)) abort(404, 'No early-payment rows for this party');

        $publicUrl = (string) $this->generatePartyEarlyPaymentPdf($party_code, $rows);

        $basename = basename(parse_url($publicUrl, PHP_URL_PATH));
        $filePath = public_path('reward_pdf/' . $basename);
        if (!is_file($filePath)) abort(404, 'PDF not found');

        return response()->download($filePath, $basename);
    }



    

    public function notifyCustomersEarlyRewardToManager()
    {
        // for single manager (not applied )
        try {
            // ── constants / config ───────────────────────────────────────
             $managerId = 178; // hardcoded as requested
            $tz        = 'Asia/Kolkata';

            // ── dates ────────────────────────────────────────────────────
            $today = \Carbon\Carbon::now($tz)->toDateString();

            // ── parties under this manager ───────────────────────────────
            $partyCodes = RewardRemainderEarlyPayment::query()
                // ->where('manager_id', $managerId)
                ->whereNotNull('party_code')
                ->pluck('party_code')
                ->unique()
                ->filter()
                ->values();

            if ($partyCodes->isEmpty()) {
                return response('No parties found for this manager.', 204);
            }

            // ── build flat list of invoices for each party ───────────────
            $customerData = [];
            foreach ($partyCodes as $partyCodeRaw) {
                $partyCode = trim((string) $partyCodeRaw);
                if ($partyCode === '') continue;

                if (method_exists($this, 'getTrimmedPartyCode')) {
                    $partyCode = $this->getTrimmedPartyCode($partyCode);
                }

                $addr = Address::where('acc_code', $partyCode)->first();
                if (!$addr) continue;

                $paymentRows = RewardRemainderEarlyPayment::where('party_code', $partyCode)
                    ->orderBy('invoice_date', 'asc')
                    ->get();

                if ($paymentRows->isEmpty()) continue;

                foreach ($paymentRows as $payment) {
                    $txDate = \Carbon\Carbon::parse($payment->invoice_date, $tz);

                    // your helpers to compute % and pay-by
                    $percent    = $this->rewardPercentFor($txDate, $today); // (kept; not displayed)
                    $payByDate  = $this->lastPayDateFor($txDate, $percent);

                    // reduced amount rule (based on reminder_sent) — unchanged
                    $discountPercentage = ((int) $payment->reminder_sent === 1) ? 2.0 : 1.5;
                    $reducedAmount      = round(((float) $payment->invoice_amount) * $discountPercentage / 100, 2);

                    $customerData[] = [
                        'customer_name'   => $addr->company_name ?? 'Customer',
                        'party_code'      => $partyCode,
                        'invoice_no'      => (string) $payment->invoice_no,
                        'invoice_amount'  => (float)  $payment->invoice_amount,
                        'pay_by_date'     => $payByDate,
                        'reduced_amount'  => number_format($reducedAmount, 2),
                        'reward_percent'  => $discountPercentage, // shown in PDF
                    ];
                }
            }

            if (empty($customerData)) {
                return response('No customer data available for this manager.', 204);
            }

            // ── sort for PDF table ───────────────────────────────────────
            $customerData = collect($customerData)
                ->sortBy(['customer_name', 'party_code', 'invoice_no'])
                ->values()
                ->all();

            // ── minimal manager object (plus optional DB lookup for phone) ─
            $manager = (object) [
                'id'   => $managerId,
                'name' => 'Manager #' . $managerId,
            ];

            $managerUser = User::find($managerId);
            $managerPhone = $managerUser->phone; // may be null

            // ── generate & save PDF ──────────────────────────────────────
            $pdf = \PDF::loadView('backend.invoices.reward_early_payment_notify_manager', compact('customerData', 'manager','managerUser'));

            $dir = public_path('pdfs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $fileName = 'reward-' . time() . '-' . $managerId . '.pdf';
            $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
            $pdf->save($filePath);

            // public URL (use asset() to avoid /public duplication)
            $fileUrl = asset('public/pdfs/' . $fileName);
            

            // ── WhatsApp: upload + send template ─────────────────────────
            // pick target number (prefer manager’s phone; else fallback)
            //$number = $managerPhone ?: '9894753728';
            $number = '9894753728';

            // sanity: require at least some 10+ digit number
            $digits = preg_replace('/\D+/', '', (string) $number);
            if (strlen($digits) < 10) {
                // Return success for PDF but clear message that WA wasn’t sent
                return response()->json([
                    'status'       => 'pdf_generated_no_whatsapp',
                    'reason'       => 'Manager phone missing/invalid',
                    'pdf_url'      => $fileUrl,
                    'manager_id'   => $managerId,
                    'manager_name' => $managerUser->name,
                ], 200);
            }

            // normalize to +91XXXXXXXXXX if it looks like an Indian number
            if (strlen($digits) == 10) {
                $number = '+91' . $digits;
            } elseif (strpos($number, '+') !== 0) {
                $number = '+' . $digits; // best-effort normalization
            }

            $whatsAppWebService = new \App\Services\WhatsAppWebService();

            // 1) Upload the PDF
            $mediaResp = $whatsAppWebService->uploadMedia($fileUrl);
            if (empty($mediaResp) || empty($mediaResp['media_id'])) {
                return response()->json([
                    'status'       => 'pdf_generated_upload_failed',
                    'pdf_url'      => $fileUrl,
                    'manager_id'   => $managerId,
                    'manager_name' => $managerUser->name,
                    'upload_resp'  => $mediaResp,
                    'message'      => 'WhatsApp media upload failed.',
                ], 502);
            }

            $mediaId  = $mediaResp['media_id'];
            $filename = 'Manager Early Payment Notifications.pdf';

            // 2) Prepare template payload (as you asked)
            $templateData = [
                'name'      => 'early_payment_manager_notify', // your approved template
                'language'  => 'en_US',
                'components'=> [
                    [
                        'type'       => 'header',
                        'parameters' => [
                            [
                                'type'     => 'document',
                                'document' => [
                                    'id'       => $mediaId,
                                    'filename' => $filename,
                                ],
                            ],
                        ],
                    ],
                    [
                        'type'       => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $managerUser->name],
                        ],
                    ],
                ],
            ];

            // 3) Send the message
            $sendResp = $whatsAppWebService->sendTemplateMessage($number="7044300330", $templateData);

            return response()->json([
                'status'         => 'ok',
                'pdf_url'        => $fileUrl,
                'manager_id'     => $managerId,
                'manager_name'   => $manager->name,
                'to'             => $number,
                'media_id'       => $mediaId,
                'upload_resp'    => $mediaResp,
                'send_resp'      => $sendResp,
            ], 200);

        } catch (\Throwable $e) {
            // ensure we don't leak stack traces in prod
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function notifyCustomersEarlyRewardToAllManagers(Request $request)
    {
        $tz    = 'Asia/Kolkata';
        $today = Carbon::now($tz)->toDateString();
    
        // 1) Distinct manager IDs from remainder table (optional ?manager_id=178 filter)
        $managerIdsQuery = RewardRemainderEarlyPayment::query()
            ->whereNotNull('manager_id')
            ->select('manager_id')
            ->distinct();
    
        if ($request->filled('manager_id')) {
            $managerIdsQuery->where('manager_id', (int) $request->get('manager_id'));
        }
    
        $managerIds = $managerIdsQuery->pluck('manager_id');
    
        if ($managerIds->isEmpty()) {
            return response()->json([
                'message' => 'No managers found in remainder table.',
            ], 204);
        }
    
        // 2) Ensure output dir exists
        $dir = public_path('pdfs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    
        $results = [];
    
        // 3) Process each manager independently
        foreach ($managerIds as $managerId) {
    
            /** @var \App\Models\User|null $mgr */
            $mgr          = User::find($managerId);
            $managerName  = $mgr->name  ?? ('Manager #' . (int)$managerId);
            $managerPhone = (string)($mgr->phone ?? '');
    
            // Pull ALL early-payment rows for this manager
            $rows = RewardRemainderEarlyPayment::query()
                ->from('reward_remainder_early_payments as r')
                ->leftJoin('addresses as a', DB::raw('BINARY a.acc_code'), '=', DB::raw('BINARY r.party_code'))
                ->where('r.manager_id', $managerId)
                ->orderBy('a.company_name')
                ->orderBy('r.party_code')
                ->orderBy('r.invoice_date')
                ->get([
                    'r.party_code',
                    'r.invoice_no',
                    'r.invoice_date',
                    'r.invoice_amount',
                    'r.remaining_amount',
                    'a.company_name',
                    'a.address',
                    'a.address_2',
                    'a.postal_code',
                ]);
    
            if ($rows->isEmpty()) {
                $results[] = [
                    'manager_id' => (int)$managerId,
                    'manager'    => $managerName,
                    'url'        => null,
                    'rows'       => 0,
                    'whatsapp'   => [
                        'sent'   => false,
                        'reason' => 'No invoice rows for this manager',
                    ],
                ];
                continue;
            }
    
            // 4) Build "customers" structure → one page per customer in Blade
            $customersMap = [];
    
            foreach ($rows as $r) {
                $partyCode = (string)$r->party_code;
    
                if (!isset($customersMap[$partyCode])) {
                    // minimal Address-like object for the Blade
                    $addressObj = (object)[
                        'company_name' => $r->company_name,
                        'address'      => $r->address,
                        'address_2'    => $r->address_2,
                        'postal_code'  => $r->postal_code,
                    ];
    
                    $customersMap[$partyCode] = [
                        'party_code'    => $partyCode,
                        'customer_name' => $r->company_name ?: $partyCode,
                        'address'       => $addressObj,
    
                        // For manager report we treat everything as "due", no split by overdue.
                        'overdueAmount' => 0.0,
                        'dueAmount'     => 0.0,
                        'previousDue'   => 0.0,
    
                        'rows'          => [],
                    ];
                }
    
                // --- Early-payment logic (15 days = 2%, 16–40 days = 1%) ---
                $txDate = Carbon::parse($r->invoice_date, $tz);
                $todayC = Carbon::parse($today, $tz);
    
                $ageDays = $txDate->diffInDays($todayC);
    
                if ($ageDays <= 15) {
                    $percent = 2.0;
                    $payBy   = $txDate->copy()->addDays(15);
                } elseif ($ageDays <= 40) {
                    $percent = 1.0;
                    $payBy   = $txDate->copy()->addDays(40);
                } else {
                    $percent = 0.0;              // outside window, no reward
                    $payBy   = $txDate->copy();   // just show invoice date
                }
    
                // Discount on REMAINING amount (what is yet to be paid)
                $invAmt   = (float) $r->invoice_amount;
                $remain   = (float) $r->remaining_amount;
                $discBase = $remain; // you can change to $invAmt if you want full-invoice base
                $reward   = round($discBase * $percent / 100, 2);
    
                $customersMap[$partyCode]['rows'][] = [
                    'invoice_no'        => (string)$r->invoice_no,
                    'invoice_date'      => (string)$r->invoice_date,
                    'invoice_amount'    => $invAmt,
                    'remaining_amount'  => $remain,
                    'reward_percentage' => $percent,
                    'reward_amount'     => $reward,
                    'last_payment_date' => $payBy->toDateString(),
                ];
    
                // accumulate "due" per customer as sum of remaining
                $customersMap[$partyCode]['dueAmount'] += $remain;
            }
    
            // Flatten to indexed array for Blade
            $customers = array_values($customersMap);
            $rowCount  = $rows->count();
    
            // Manager object for the view
            $managerObj = (object)[
                'id'   => (int)$managerId,
                'name' => (string)$managerName,
            ];
    
            // 5) Generate & save PDF for this manager
            $pdf = PDF::loadView('backend.invoices.reward_early_payment_notify_manager', [
                'customers'   => $customers,
                'manager'     => $managerObj,
                'managerUser' => $mgr,
            ]);
    
            $fileName = 'reward-' . now($tz)->format('YmdHis')
                      . '-' . $managerId
                      . '-' . substr(md5(uniqid('', true)), 0, 6)
                      . '.pdf';
    
            $pdf->save($dir . DIRECTORY_SEPARATOR . $fileName);
            $fileUrl = asset('public/pdfs/' . $fileName);
    
            unset($customers, $customersMap, $pdf);
            gc_collect_cycles();
    
            // 6) WhatsApp send using template "early_payment_manager_notify"
            $wa = ['sent' => false, 'reason' => 'No phone'];
            try {
                $to = '';
                if (!empty($managerPhone)) {
                    $digits = preg_replace('/\D+/', '', $managerPhone);
                    if (strlen($digits) === 10) {
                        $to = '+91' . $digits;
                    } elseif ($digits !== '' && $digits[0] !== '+') {
                        $to = '+' . $digits;
                    } else {
                        $to = $managerPhone;
                    }
                }
    
                if (!empty($to)) {
                    $svc      = new WhatsAppWebService();
                    $mediaRes = $svc->uploadMedia($fileUrl);
                    $mediaId  = is_array($mediaRes)
                        ? ($mediaRes['media_id'] ?? null)
                        : (is_object($mediaRes) ? ($mediaRes->media_id ?? null) : null);
    
                    if ($mediaId) {
                        $templateData = [
                            'name'      => 'early_payment_manager_notify',
                            'language'  => 'en_US',
                            'components'=> [
                                [
                                    'type'       => 'header',
                                    'parameters' => [[
                                        'type'     => 'document',
                                        'document' => [
                                            'id'       => $mediaId,
                                            'filename' => 'Manager Early Payment Notifications.pdf',
                                        ],
                                    ]],
                                ],
                                [
                                    'type'       => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $managerName],
                                    ],
                                ],
                            ],
                        ];
    
                        $resp   = $svc->sendTemplateMessage($to, $templateData);
                        $status = data_get($resp, 'messages.0.message_status');
                        $msgId  = data_get($resp, 'messages.0.id');
    
                        if (($status && strtolower($status) === 'accepted') || $msgId) {
                            $wa = [
                                'sent'       => true,
                                'status'     => $status ?: 'accepted',
                                'message_id' => $msgId,
                            ];
                        } else {
                            $wa = [
                                'sent'   => false,
                                'reason' => 'not_accepted',
                                'response' => $resp,
                            ];
                        }
                    } else {
                        $wa = [
                            'sent'   => false,
                            'reason' => 'upload_failed',
                            'response' => $mediaRes,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                \Log::error("WA send failed for manager {$managerId}: " . $e->getMessage());
                $wa = [
                    'sent'   => false,
                    'reason' => 'exception',
                    'error'  => $e->getMessage(),
                ];
            }
    
            // 7) Collect result for this manager
            $results[] = [
                'manager_id' => (int)$managerId,
                'manager'    => $managerName,
                'url'        => $fileUrl,
                'rows'       => $rowCount,
                'whatsapp'   => $wa,
            ];
    
            gc_collect_cycles();
        }
    
        return response()->json(['results' => $results], 200);
    }

    public function generateAndSendHeadManagerWarehouseEarlyPaymentReport()
    {
        try {
            $tz            = 'Asia/Kolkata';
            $today         = \Carbon\Carbon::now($tz)->toDateString();
            $headManagerId = 180; // ← fixed head manager id

            // 1) Load head manager & their warehouse_id + phone & name
            /** @var \App\Models\User|null $head */
            $head = User::find($headManagerId);
            if (!$head) {
                return response()->json(['message' => 'Head manager not found.'], 404);
            }
            $warehouseId  = $head->warehouse_id ?? null;
            $managerName  = (string) ($head->name ?? ('Manager #'.$headManagerId));
            $managerPhone = (string) ($head->phone ?? '');

            if (empty($warehouseId)) {
                return response()->json(['message' => 'Head manager has no warehouse_id.'], 404);
            }

            // 2) All customers under this warehouse
            $customers = User::where('warehouse_id', $warehouseId)
                ->whereNotNull('party_code')
                ->orderBy('name', 'asc')
                ->get(['id','name','party_code']);

            if ($customers->isEmpty()) {
                return response()->json(['message' => 'No customers found for this warehouse.'], 204);
            }

            // 3) Build rows for the PDF
            $customerData = [];
            foreach ($customers as $cust) {
                $partyCode = (string) $cust->party_code;
                if ($partyCode === '') continue;

                // Optional sanitizer
                if (method_exists($this, 'getTrimmedPartyCode')) {
                    $partyCode = $this->getTrimmedPartyCode($partyCode);
                }

                $addr = Address::where('acc_code', $partyCode)->first();
                $displayName = $addr->company_name ?? ($cust->name ?? 'Customer');

                $payments = RewardRemainderEarlyPayment::where('party_code', $partyCode)
                    ->orderBy('invoice_date', 'asc')
                    ->get();

                if ($payments->isEmpty()) continue;

                foreach ($payments as $p) {
                    $txDate = \Carbon\Carbon::parse($p->invoice_date, $tz);

                    // Pay-By (last payment) date via your helpers; fallback inline
                    if (method_exists($this, 'rewardPercentFor') && method_exists($this, 'lastPayDateFor')) {
                        $percentForPayBy = (float) $this->rewardPercentFor($txDate, $today);          // 2.0 or 1.0
                        $payByDate       = (string) $this->lastPayDateFor($txDate, $percentForPayBy);  // D+15 or D+40
                    } else {
                        $ageDays         = $txDate->diffInDays(\Carbon\Carbon::parse($today));
                        $percentForPayBy = ($ageDays <= 15) ? 2.0 : 1.0;
                        $payByDate       = ($percentForPayBy <= 1.0)
                            ? $txDate->copy()->addDays(40)->toDateString()
                            : $txDate->copy()->addDays(15)->toDateString();
                    }

                    // Show % column as per your rule (reminder_sent=1 → 2%, else 1.5%)
                    $displayPercent = ((int) $p->reminder_sent === 1) ? 2.0 : 1.5;
                    $reducedAmount  = round(((float) $p->invoice_amount) * $displayPercent / 100, 2);

                    $customerData[] = [
                        'customer_name'   => $displayName,
                        'party_code'      => $partyCode,
                        'invoice_no'      => (string) $p->invoice_no,
                        'invoice_amount'  => (float)  $p->invoice_amount,
                        'pay_by_date'     => $payByDate,                          // last payment date
                        'reduced_amount'  => number_format($reducedAmount, 2),    // optional in view
                        'reward_percent'  => $displayPercent,                      // % column in view
                    ];
                }
            }

            if (empty($customerData)) {
                return response()->json(['message' => 'No invoice rows under this warehouse.'], 204);
            }

            // 4) Sort & render with your existing PDF view
            $customerData = collect($customerData)
                ->sortBy(['customer_name', 'party_code', 'invoice_no'])
                ->values()
                ->all();

            // Pass a $manager object to your blade for heading
            $manager = (object) [
                'id'   => (int) $headManagerId,
                'name' => $managerName,
            ];
             $managerUser=$manager;

            $pdf = \PDF::loadView('backend.invoices.reward_early_payment_notify_manager', compact('customerData', 'manager','managerUser'));

            $dir = public_path('pdfs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fileName = 'reward-warehouse-'.$warehouseId.'-'.now($tz)->format('YmdHis').'.pdf';
            $pdf->save($dir.DIRECTORY_SEPARATOR.$fileName);

            $fileUrl = asset('public/pdfs/'.$fileName);

            // 5) WhatsApp — use EXACT template name: early_payment_manager_notify
            // Normalize phone → +91XXXXXXXXXX if needed
            $digits = preg_replace('/\D+/', '', $managerPhone);
            $to     = '';
            if (strlen($digits) === 10)      $to = '+91'.$digits;
            elseif ($digits !== '' && $digits[0] !== '+') $to = '+'.$digits;
            else                               $to = $managerPhone;

            $wa = ['sent' => false, 'reason' => 'No phone'];
            if (!empty($to)) {
                try {
                    $ws = new \App\Services\WhatsAppWebService();

                    // Upload the PDF → get media_id
                    $upload = $ws->uploadMedia($fileUrl);
                    $mediaId = is_array($upload) ? ($upload['media_id'] ?? null) : (is_object($upload) ? ($upload->media_id ?? null) : null);

                    if ($mediaId) {
                        $templateData = [
                            'name'      => 'early_payment_manager_notify', // ← EXACT template name
                            'language'  => 'en_US',
                            'components'=> [
                                [
                                    'type'       => 'header',
                                    'parameters' => [[
                                        'type'     => 'document',
                                        'document' => [
                                            'id'       => $mediaId,
                                            'filename' => 'Manager Early Payment Notifications.pdf',
                                        ],
                                    ]],
                                ],
                                [
                                    'type'       => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $managerName],
                                    ],
                                ],
                            ],
                        ];

                        $resp      = $ws->sendTemplateMessage($to="7044300330", $templateData);
                        $status    = data_get($resp, 'messages.0.message_status');
                        $messageId = data_get($resp, 'messages.0.id');

                        if (($status && strtolower($status) === 'accepted') || ($messageId && is_string($messageId))) {
                            $wa = ['sent' => true, 'status' => $status ?: 'accepted', 'message_id' => $messageId];
                        } else {
                            $wa = ['sent' => false, 'reason' => 'WhatsApp not accepted', 'response' => $resp];
                        }
                    } else {
                        $wa = ['sent' => false, 'reason' => 'Failed to upload media', 'upload_resp' => $upload];
                    }
                } catch (\Throwable $e) {
                    \Log::error('WA send failed (head manager): '.$e->getMessage());
                    $wa = ['sent' => false, 'reason' => 'Exception', 'error' => $e->getMessage()];
                }
            }

            return response()->json([
                'status'        => 'ok',
                'head_manager'  => $managerName,
                'manager_id'    => (int) $headManagerId,
                'warehouse_id'  => $warehouseId,
                'rows'          => count($customerData),
                'pdf_url'       => $fileUrl,
                'whatsapp'      => $wa,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('generateAndSendHeadManagerWarehouseEarlyPaymentReport error: '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function generateAndSendHeadManagersWarehouseEarlyPayment()
    {
        try {
            $tz    = 'Asia/Kolkata';
            $today = \Carbon\Carbon::now($tz)->toDateString();

            // ✅ Your head managers
            $headManagerIds = [180, 169, 25606];

            // Output dir for PDFs
            $dir = public_path('pdfs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $results = [];

            foreach ($headManagerIds as $headManagerId) {
                /** @var \App\Models\User|null $head */
                $head = User::find($headManagerId);
                if (!$head) {
                    $results[] = [
                        'manager_id' => (int)$headManagerId,
                        'manager'    => null,
                        'warehouse_id' => null,
                        'rows'       => 0,
                        'pdf_url'    => null,
                        'whatsapp'   => ['sent' => false, 'reason' => 'Head manager not found'],
                    ];
                    continue;
                }

                $warehouseId  = $head->warehouse_id ?? null;
                $managerName  = (string) ($head->name ?? ('Manager #'.$headManagerId));
                $managerPhone = (string) ($head->phone ?? '');

                if (empty($warehouseId)) {
                    $results[] = [
                        'manager_id' => (int)$headManagerId,
                        'manager'    => $managerName,
                        'warehouse_id' => null,
                        'rows'       => 0,
                        'pdf_url'    => null,
                        'whatsapp'   => ['sent' => false, 'reason' => 'Head manager has no warehouse_id'],
                    ];
                    continue;
                }

                // All customers under this warehouse
                $customers = User::where('warehouse_id', $warehouseId)
                    ->whereNotNull('party_code')
                    ->orderBy('name', 'asc')
                    ->get(['id','name','party_code']);

                if ($customers->isEmpty()) {
                    $results[] = [
                        'manager_id' => (int)$headManagerId,
                        'manager'    => $managerName,
                        'warehouse_id' => $warehouseId,
                        'rows'       => 0,
                        'pdf_url'    => null,
                        'whatsapp'   => ['sent' => false, 'reason' => 'No customers under this warehouse'],
                    ];
                    continue;
                }

                // Build rows for the PDF (ALL invoices of each party)
                $customerData = [];
                foreach ($customers as $cust) {
                    $partyCode = (string) $cust->party_code;
                    if ($partyCode === '') continue;

                    // Optional sanitizer you already have
                    if (method_exists($this, 'getTrimmedPartyCode')) {
                        $partyCode = $this->getTrimmedPartyCode($partyCode);
                    }

                    $addr = Address::where('acc_code', $partyCode)->first();
                    $displayName = $addr->company_name ?? ($cust->name ?? 'Customer');

                    $payments = RewardRemainderEarlyPayment::where('party_code', $partyCode)
                        ->orderBy('invoice_date', 'asc')
                        ->get();

                    if ($payments->isEmpty()) continue;

                    foreach ($payments as $p) {
                        $txDate = \Carbon\Carbon::parse($p->invoice_date, $tz);

                        // Pay-By (last payment) date via your helpers; fallback inline if missing
                        if (method_exists($this, 'rewardPercentFor') && method_exists($this, 'lastPayDateFor')) {
                            $percentForPayBy = (float) $this->rewardPercentFor($txDate, $today);           // 2.0 or 1.0
                            $payByDate       = (string) $this->lastPayDateFor($txDate, $percentForPayBy);   // D+15 or D+40
                        } else {
                            $ageDays         = $txDate->diffInDays(\Carbon\Carbon::parse($today));
                            $percentForPayBy = ($ageDays <= 15) ? 2.0 : 1.0;
                            $payByDate       = ($percentForPayBy <= 1.0)
                                ? $txDate->copy()->addDays(40)->toDateString()
                                : $txDate->copy()->addDays(15)->toDateString();
                        }

                        // Show % column in PDF per your rule (reminder_sent=1 → 2%, else 1.5%)
                        $displayPercent = ((int) $p->reminder_sent === 1) ? 2.0 : 1.5;
                        $reducedAmount  = round(((float) $p->invoice_amount) * $displayPercent / 100, 2);

                        $customerData[] = [
                            'customer_name'   => $displayName,
                            'party_code'      => $partyCode,
                            'invoice_no'      => (string) $p->invoice_no,
                            'invoice_amount'  => (float)  $p->invoice_amount,
                            'pay_by_date'     => $payByDate,                         // last payment date
                            'reduced_amount'  => number_format($reducedAmount, 2),   // optional in view
                            'reward_percent'  => $displayPercent,                     // % column in view
                        ];
                    }
                }

                if (empty($customerData)) {
                    $results[] = [
                        'manager_id' => (int)$headManagerId,
                        'manager'    => $managerName,
                        'warehouse_id' => $warehouseId,
                        'rows'       => 0,
                        'pdf_url'    => null,
                        'whatsapp'   => ['sent' => false, 'reason' => 'No invoice rows under this warehouse'],
                    ];
                    continue;
                }

                // Sort for neat PDF
                $customerData = collect($customerData)
                    ->sortBy(['customer_name', 'party_code', 'invoice_no'])
                    ->values()
                    ->all();

                // Object passed to the view for heading
                $manager = (object) [
                    'id'   => (int) $headManagerId,
                    'name' => $managerName,
                ];
                $managerUser = $manager; // if your blade expects it

                // Generate & save PDF
                $pdf = \PDF::loadView('backend.invoices.reward_early_payment_notify_manager', compact('customerData', 'manager', 'managerUser'));

                $fileName = 'reward-warehouse-'.$warehouseId.'-'.$headManagerId.'-'.now($tz)->format('YmdHis').'-'.substr(md5(uniqid('', true)),0,6).'.pdf';
                $pdf->save($dir.DIRECTORY_SEPARATOR.$fileName);

                $fileUrl = asset('public/pdfs/'.$fileName);

                // WhatsApp send using EXACT template name
                $wa = ['sent' => false, 'reason' => 'No phone'];
                $digits = preg_replace('/\D+/', '', $managerPhone);
                $to = '';
                if (strlen($digits) === 10) {
                    $to = '+91'.$digits;
                } elseif ($digits !== '' && $digits[0] !== '+') {
                    $to = '+'.$digits;
                } else {
                    $to = $managerPhone;
                }

                if (!empty($to)) {
                    try {
                        $ws = new \App\Services\WhatsAppWebService();

                        // Upload PDF to WA → get media_id
                        $upload  = $ws->uploadMedia($fileUrl);
                        $mediaId = is_array($upload) ? ($upload['media_id'] ?? null) : (is_object($upload) ? ($upload->media_id ?? null) : null);

                        if ($mediaId) {
                            $templateData = [
                                'name'      => 'early_payment_manager_notify', // ← exact template
                                'language'  => 'en_US',
                                'components'=> [
                                    [
                                        'type'       => 'header',
                                        'parameters' => [[
                                            'type'     => 'document',
                                            'document' => [
                                                'id'       => $mediaId,
                                                'filename' => 'Manager Early Payment Notifications.pdf',
                                            ],
                                        ]],
                                    ],
                                    [
                                        'type'       => 'body',
                                        'parameters' => [
                                            ['type' => 'text', 'text' => $managerName],
                                        ],
                                    ],
                                ],
                            ];

                            $resp      = $ws->sendTemplateMessage($to, $templateData);
                            $status    = data_get($resp, 'messages.0.message_status');
                            $messageId = data_get($resp, 'messages.0.id');

                            if (($status && strtolower($status) === 'accepted') || ($messageId && is_string($messageId))) {
                                $wa = ['sent' => true, 'status' => $status ?: 'accepted', 'message_id' => $messageId];
                            } else {
                                $wa = ['sent' => false, 'reason' => 'WhatsApp not accepted', 'response' => $resp];
                            }
                        } else {
                            $wa = ['sent' => false, 'reason' => 'Failed to upload media', 'upload_resp' => $upload];
                        }
                    } catch (\Throwable $e) {
                        \Log::error('WA send failed (head manager '.$headManagerId.'): '.$e->getMessage());
                        $wa = ['sent' => false, 'reason' => 'Exception', 'error' => $e->getMessage()];
                    }
                }

                $results[] = [
                    'manager_id'   => (int)$headManagerId,
                    'manager'      => $managerName,
                    'warehouse_id' => $warehouseId,
                    'rows'         => count($customerData),
                    'pdf_url'      => $fileUrl,
                    'whatsapp'     => $wa,
                ];
            }

            return response()->json(['status' => 'ok', 'results' => $results], 200);

        } catch (\Throwable $e) {
            \Log::error('generateAndSendAllHeadManagersWarehouseEarlyPaymentReports error: '.$e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }



}
