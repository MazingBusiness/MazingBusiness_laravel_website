<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Mpdf\Mpdf;
use App\Models\User;
use App\Models\Address;
use App\Services\PdfContentService;

class GenerateStatementPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $fileName;

    public function __construct($data, $fileName)
    {
        $this->data     = $data;
        $this->fileName = $fileName;
    }

    public function handle()
  {
      $userData      = $this->data['userData'];
      $party_code    = $this->data['party_code'];
      $statementData = $this->data['statementData'] ?? [];
      $overdueAmount = (float)($this->data['overdueAmount'] ?? 0);
      $dueAmount     = (float)($this->data['dueAmount'] ?? 0);
      $form_date     = $this->data['form_date'] ?? now()->toDateString();
      $to_date       = $this->data['to_date']   ?? now()->toDateString();

      // Address + company
      $addressData = Address::where('acc_code', $party_code)->first();
      $companyName = $addressData->company_name ?? ($userData->company_name ?? '');
      $address     = $addressData->address      ?? 'Address not found';
      $address_2   = $addressData->address_2    ?? '';
      $postal_code = $addressData->postal_code  ?? '';

      // Credit settings
      $userCreditDetails = User::where('id', $userData->id)
          ->select('credit_days', 'credit_limit')
          ->first();

      $creditDays  = (int)($userCreditDetails->credit_days   ?? 0);
      $creditLimit = (float)($userCreditDetails->credit_limit ?? 0);

      // Available credit — if already in Cr, show limit; otherwise limit - due
      $availableCredit = ($userData->dueDrOrCr === 'Cr')
          ? $creditLimit
          : max(0, $creditLimit - $dueAmount);

      // =========================================================
      // 1) PDF CONTENT / POSTER BLOCK LOAD  (statement ke liye)
      // =========================================================
      $pdfContentService = new PdfContentService();   // make sure: use App\Services\PdfContentService;
      $pdfContentBlock   = $pdfContentService->buildBlockForType('statement');

      $posterTopHtml    = '';
      $posterBottomHtml = '';

      if (!empty($pdfContentBlock)) {
          $rendered = view('backend.sales.partials.pdf_content_block', [
              'block' => $pdfContentBlock,
          ])->render();

          $placement = $pdfContentBlock['placement'] ?? 'last'; // first | last

          if ($placement === 'first') {
              // sirf first page, header ke neeche
              $posterTopHtml = $rendered;
          } elseif ($placement === 'last') {
              // sirf last page, hum isko sabse niche (bank details ke baad) likhenge
              $posterBottomHtml = $rendered;
          }
      }

      // ---------- Header / Footer ----------
      $header = '
        <table width="100%" border="0" cellpadding="0" cellspacing="0">
          <tr>
            <td style="text-align:right;position:relative;">
              <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display:block;" />
            </td>
          </tr>
        </table>';

      $footer = '
        <table width="100%" border="0" cellpadding="0" cellspacing="0">
          <tbody>
            <tr bgcolor="#174e84">
              <td style="height:40px;text-align:center;color:#fff;font-family:Arial;font-weight:bold;">
                ACE TOOLS PVT LTD STATEMENT
              </td>
            </tr>
          </tbody>
        </table>';

      $color = ($overdueAmount > 0) ? '#ff0707' : '#333';

      // ---------- Top Summary ----------
      $htmlContent = '
        <table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top:20px;">
          <tr>
            <td width="50%" style="text-align:left;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;color:#174e84;">
              ACE TOOLS PRIVATE LIMITED<br>
              Building No./Flat No.: Khasra No. 58/15,<br>
              Pal Colony, Village Rithala,Delhi<br>
              New Delhi - 110085
            </td>
            <td width="50%" style="text-align:right;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              '.e($companyName).'<br>'.e($address).'<br>'.e($address_2).'<br>
              Pincode: '.e($postal_code).'<br>
            </td>
          </tr>
        </table>

        <table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top:20px;">
          <tr>
            <td width="33%" style="text-align:center;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Credit Days</strong><br>
              <span style="font-size:12px;color:#333;">'.$creditDays.' days</span>
            </td>
            <td width="33%" style="text-align:center;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Credit Limit</strong><br>
              <span style="font-size:12px;color:#333;">₹'.number_format($creditLimit, 2).'</span>
            </td>
            <td width="33%" style="text-align:center;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Available Credit</strong><br>
              <span style="font-size:12px;color:#000;">₹'.number_format($availableCredit, 2).'</span>
            </td>
          </tr>
        </table>

        <table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top:20px;">
          <tr>
            <td width="50%" style="text-align:center;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Overdue Balance</strong><br>
              <span style="font-size:12px;color:'.$color.';">₹'.number_format($overdueAmount, 2).' '.($userData->overdueDrOrCr ?? '').'</span>
            </td>
            <td width="50%" style="text-align:center;font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Due Amount</strong><br>
              <span style="font-size:12px;color:#333;">₹'.number_format($dueAmount, 2).' '.($userData->dueDrOrCr ?? '').'</span>
            </td>
          </tr>
        </table>

        <p style="font-size:14px;text-align:center;margin-top:20px;">
          <strong>'.date('d-M-y', strtotime($form_date)).' to '.date('d-M-y', strtotime($to_date)).'</strong>
        </p>';

      // ---------- Table Header ----------
      $htmlTableHeader = '
        <table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse:collapse;margin-top:25px;font-family:Arial,sans-serif;border:1px solid #ccc;">
          <thead>
            <tr>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Date.</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Particulars</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Txn No</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:right;">Debit</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:right;">Credit</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:right;">Balance</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Overdue Status</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Dr / Cr</th>
              <th style="border:1px solid #ccc;background:#f1f1f1;padding:5px;text-align:center;">Overdue By Day</th>
            </tr>
          </thead>
          <tbody>';

      $htmlTableFooter = '</tbody></table>';

      // =========================================================
      // 2) MPDF INIT + 1st PAGE POSTER
      // =========================================================
      $mpdf = new Mpdf(['format' => 'A4']);
      $mpdf->setHTMLHeader($header);
      $mpdf->setHTMLFooter($footer);
      $mpdf->SetMargins(0, 10, 40, 10);
      $mpdf->AddPageByArray(['size' => 'A4']);

      // ⭐ Header ke baad, sirf 1st page pe poster (placement = first)
      if ($posterTopHtml !== '') {
          $mpdf->WriteHTML($posterTopHtml);
      }

      // Top summary
      $mpdf->WriteHTML($htmlContent);
      // Table header
      $mpdf->WriteHTML($htmlTableHeader);

      // ---------- Seed opening balance (hidden row) ----------
      $openingDr = 0.0;
      $openingCr = 0.0;
      foreach ($statementData as $t) {
          if ($this->isOpeningRow($t)) {
              $openingDr = $this->num($t['dramount'] ?? 0);
              $openingCr = $this->num($t['cramount'] ?? 0);
              break;
          }
      }

      // ---------- Counters ----------
      $balance        = $openingDr - $openingCr;
      $totalDebit     = 0.0;
      $totalCredit    = 0.0;
      $closingBalance = 0.0;
      $closingDrOrCr  = '-';

      // ---------- Rows ----------
      foreach ($statementData as $transaction) {
          if ($this->isOpeningRow($transaction)) {
              continue;
          }
          if (strcasecmp(trim($transaction['ledgername'] ?? ''), 'closing C/f...') === 0) {
              continue;
          }

          $ovStatus = trim($transaction['overdue_status'] ?? '-');
          $isOver   = strcasecmp($ovStatus, 'Overdue') === 0;
          $isPart   = strcasecmp($ovStatus, 'Partial Overdue') === 0
                      || strcasecmp($ovStatus, 'Pertial Overdue') === 0;

          $bgColor  = $isOver ? 'background-color:#ff00006b;'
                      : ($isPart ? 'background-color:#ffcccc;' : '');

          $dr = $this->num($transaction['dramount'] ?? 0);
          $cr = $this->num($transaction['cramount'] ?? 0);

          $totalDebit  += $dr;
          $totalCredit += $cr;

          $balance += ($dr - $cr);
          $closingBalance = $balance;
          $closingDrOrCr  = ($balance > 0) ? 'Dr' : (($balance < 0) ? 'Cr' : '-');

          $rawOvText = $transaction['overdue_by_day'] ?? '-';
          $rawOvDays = $this->extractDays($rawOvText);
          $combined  = ($isOver || $isPart) && $rawOvDays > 0
              ? ($creditDays + $rawOvDays) . ' days'
              : '-';

          $particulars = strtoupper($transaction['vouchertypebasename'] ?? ($transaction['particulars'] ?? ''));
          $dateStr     = $transaction['trn_date'] ?? '';
          $dateOut     = $dateStr ? date('d-M-y', strtotime($dateStr)) : '';

          $txnNo   = trim((string)($transaction['trn_no'] ?? ''));
          $txnHtml = $txnNo !== ''
              ? '<a href="' . route('generate.invoice', ['invoice_no' => encrypt($txnNo)]) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:#074e86;">' . e($txnNo) . '</a>'
              : 'N/A';

          $rowHtml = '
            <tr style="'.$bgColor.'">
              <td style="border:1px solid #ccc;padding:10px;text-align:center;">'.$dateOut.'</td>
              <td style="border:1px solid #ccc;padding:12px;text-align:center;">'.e($particulars).'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:center;">'.$txnHtml.'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:right;">₹'.number_format($dr, 2).'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:right;">₹'.number_format($cr, 2).'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:right;">₹'.number_format($balance, 2).'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:center;">'.($ovStatus ?: '-').'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:center;">'.$closingDrOrCr.'</td>
              <td style="border:1px solid #ccc;padding:10px;text-align:center;">'.$combined.'</td>
            </tr>';

          $mpdf->WriteHTML($rowHtml);
      }

      // ---------- Totals ----------
      $totalsHtml = '
        <tr>
          <td colspan="3" style="border:1px solid #ccc;padding:10px;text-align:right;"><strong>Total</strong></td>
          <td style="border:1px solid #ccc;padding:10px;text-align:right;">₹'.number_format($totalDebit, 2).'</td>
          <td style="border:1px solid #ccc;padding:10px;text-align:right;">₹'.number_format($totalCredit, 2).'</td>
          <td colspan="3" style="border:1px solid #ccc;padding:10px;"></td>
          <td style="border:1px solid #ccc;"></td>
        </tr>

        <!-- Closing Balance Row -->
        <tr>
          <td colspan="3" style="border:1px solid #ccc;text-align:right;"><strong>Closing Balance</strong></td>
          <td style="border:1px solid #ccc;"></td>
          <td style="border:1px solid #ccc;">₹'.number_format(abs($closingBalance), 2).'</td>
          <td colspan="3" style="border:1px solid #ccc;"></td>
          <td style="border:1px solid #ccc;text-align:center;"><strong>'.$closingDrOrCr.'</strong></td>
        </tr>

        <!-- Grand Total Row -->
        <tr>
          <td colspan="3" style="border:1px solid #ccc;padding:10px;text-align:right;"><strong>Grand Total</strong></td>
          <td style="border:1px solid #ccc;padding:10px;text-align:right;"><strong>₹'.number_format($totalDebit, 2).'</strong></td>
          <td style="border:1px solid #ccc;padding:10px;text-align:right;"><strong>₹'.number_format($totalCredit + abs($closingBalance), 2).'</strong></td>
          <td colspan="3" style="border:1px solid #ccc;"></td>
          <td style="border:1px solid #ccc;"></td>
        </tr>';

      $mpdf->WriteHTML($totalsHtml);

      // ---------- Bottom block (bank details) ----------
      $lastFooter = '
        <table width="100%" border="0" cellpadding="10" cellspacing="0" style="margin-top:20px;">
          <tr>
            <td width="50%" style="font-family:Arial,sans-serif;font-size:14px;color:#174e84;">
              <strong>Bank Details:</strong><br>
              A/C Name: ACE TOOLS PRIVATE LIMITED<br>
              Branch : NAJAFGARH ROAD, NEW DELHI<br>
              A/C No: 235605001202<br>
              IFSC Code: ICIC0002356<br>
              Bank Name: ICICI Bank<br>
            </td>
            <td style="width:50%;text-align:right;padding:10px;">
              <img src="https://mazingbusiness.com/public/assets/img/barcode.png" alt="Scan QR Code" style="width:100px;height:100px;">
              <br><span style="font-size:12px;">Scan the barcode with any UPI app to pay.</span>
            </td>
          </tr>
        </table>';

      // Table close
      $mpdf->WriteHTML($htmlTableFooter);

      // ⬇️ Order change:
      // 1) Bank details
      $mpdf->WriteHTML($lastFooter);

      // 2) Sirf last page pe, bank details ke BILKUL NICHE poster (placement = last)
      if ($posterBottomHtml !== '') {
          $mpdf->WriteHTML($posterBottomHtml);
      }

      // ---------- Save file ----------
      $pdfPath = public_path('statements/');
      if (!file_exists($pdfPath)) {
          @mkdir($pdfPath, 0755, true);
      }

      $mpdf->Output($pdfPath . '/' . $this->fileName, 'F');
  }



    /**
     * Opening-row detector.
     */
    private function isOpeningRow(array $tx): bool
    {
        $vt = strtoupper(trim($tx['vouchertypebasename'] ?? ''));
        $ln = strtolower(trim($tx['ledgername'] ?? ''));

        return $vt === 'OPENING BALANCE'
            || $ln === 'opening b/f...'
            || $ln === 'opening balance'
            || str_contains($vt, 'OPENING'); // safety
    }

    /**
     * Extract numeric day count from values like "7 days", "27", "5 day", etc.
     */
    private function extractDays($val): int
    {
        if (is_numeric($val)) {
            return (int)$val;
        }
        $n = (int)preg_replace('/\D+/', '', (string)$val);
        return max(0, $n);
    }

    /**
     * Normalize numeric-like values safely.
     */
    private function num($v): float
    {
        if ($v === null || $v === '' || $v === '-') return 0.0;
        return (float)$v;
    }
}
