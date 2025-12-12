<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GoogleSheetsService;
use App\Models\User;
use App\Models\Address;
use App\Models\Warehouse;
use App\Models\RewardUser;
use App\Models\RewardPointsOfUser;
use App\Models\RewardRemainderEarlyPayment;
use App\Models\PaymentHistory;
use App\Models\PaymentUrl;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CheckoutController;
use Maatwebsite\Excel\Facades\Excel; // Assuming you're using Laravel Excel for export
use App\Imports\ImportRewardsCreditNotes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PDF;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Crypt;
use App\Jobs\SendWhatsAppMessagesJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

use App\Services\PdfContentService;

class LogisticWhatsappAssignController extends Controller
{

    private function getManagerPhone($managerId)
    {
      // $managerData = DB::table('users')
      //     ->where('id', $managerId)
      //     ->select('phone')
      //     ->first();
        $managerData = User::where('id', $managerId)->select('phone')->first();


      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }
   public function getRewardPdfURL($party_code)
    {
        // Fetch rewards data
        $getData = RewardPointsOfUser::where('party_code', $party_code)
            ->whereNull('cancel_reason')
            ->get();



        // Process rewards data to add narration
        foreach ($getData as $reward) {
            if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($reward->rewards_from === 'Manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // Exclude 'Total' and 'Closing Balance' rows from calculations
        $rewardRows = $getData->filter(function ($reward) {
            return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
        });

        // Calculate reward amount
        $rewardAmount = $rewardRows->sum('rewards');

        // Get the last valid row before totals for closing balance
        $lastRow = $rewardRows->last();
        $closing_balance = $lastRow ? $lastRow->rewards : 0;
        $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

        // Adjust the closing balance based on Dr/Cr logic
        if ($last_dr_or_cr === 'dr') {
            $closing_balance = $rewardAmount;
        } else {
            $closing_balance = -$rewardAmount;
        }

        // User data
        $userData = Auth::user();


        // File name and path
        $fileName = 'reward_statement_' . $party_code . '_' . time() . '.pdf';
        $filePath = public_path('reward_pdf/' . $fileName);
        $publicUrl = url('public/reward_pdf/' . $fileName);

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('reward_statement');

        // Generate and save the PDF
        PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount',
            'closing_balance',
            'last_dr_or_cr',
            'pdfContentBlock' // ✅ Blade me use hoga
        ))->save($filePath);

        // Return the public URL
        return $publicUrl;
    }


 public function whatsappRewardAssign()
    {

        // Fetch data using the RewardPointsOfUser model where rewards are unprocessed
        $rewardsData = RewardPointsOfUser::where('is_processed', 0)
            ->whereIn('rewards_from', ['Logistic'])
           // ->where('invoice_no', 'DEL/1657/24-25')
            //->where('id', '48')
            ->get();

        if ($rewardsData->isEmpty()) {

            return response()->json(['error' => 'No rewards data found.'], 400);
        }

        $groupId = 'group_' . uniqid(); // Generate a unique group ID if needed

        foreach ($rewardsData as $reward) {
            $imageUrl = "https://mazingbusiness.com/public/reward_pdf/reward_image.jpg";
 
            // Generate reward URL
            $rewardURL = $this->getRewardPdfURL($reward->party_code);
            
            $rewardBaseFileName = basename($rewardURL);

            // Check if reward type is manual to skip statement URL generation
            if ($reward->rewards_from !== 'Manual') {
                $statementURL = (new InvoiceController)->getStatementPdfURL(request()->merge(['party_code' => encrypt($reward->party_code)]));
                $statementBaseFileName = basename($statementURL);
            } else {
                $statementURL = null;
                $statementBaseFileName = null;
            }
  
            // Fetch corresponding bill data using party_code and invoice_no
            $billData = DB::table('bills_data')
                ->where('billing_company', $reward->party_code)
                ->where('invoice_no', $reward->invoice_no)
                ->first();

            if (!$billData && $reward->rewards_from !== 'Manual') {
                Log::warning("No matching bill data found for party_code: {$reward->party_code}, invoice_no: {$reward->invoice_no}");
                continue; // Skip if no matching record found for non-manual reward
            }

            // Fetch company name from addresses table using model
            $addressData = Address::where('acc_code', $reward->party_code)->first();
            $user = User::where('party_code', $reward->party_code)->first();

            if (!$addressData) {
                Log::warning("No address found for party_code: {$reward->party_code}");
                continue; // Skip if no matching address found
            }

            // Prepare necessary data from bills_data table
            $company_name = $addressData->company_name;
            $invoice_no = $billData->invoice_no ?? '';
            $invoice_date = $billData->invoice_date ?? '';
            $invoice_amount = $billData->invoice_amount ?? '';

            // Message template for non-manual rewards (with invoice details and statement link)
            $templateData = [
                'name' => 'utility_rewards_whatsapp', // Template name for regular rewards
                'language' => 'en_US', // Language code
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            ['type' => 'image', 'image' => ['link' => $imageUrl]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $company_name],
                            ['type' => 'text', 'text' => $reward->rewards],
                            ['type' => 'text', 'text' => $invoice_no],
                            ['type' => 'text', 'text' => $invoice_date],
                            ['type' => 'text', 'text' => $invoice_amount],
                            ['type' => 'text', 'text' => $reward->rewards_from],
                        ],
                    ],
                    [
                        'type' => 'button', 'sub_type' => 'url', 'index' => '0',
                        'parameters' => [
                            ['type' => 'text', 'text' => $rewardBaseFileName],
                        ],
                    ],
                    [
                        'type' => 'button', 'sub_type' => 'url', 'index' => '1',
                        'parameters' => [
                            ['type' => 'text', 'text' => $statementBaseFileName],
                        ],
                    ],
                ],
            ];
          

            // Insert WhatsApp message data into the wa_sales_queue table
            $publicUrl = $statementURL ?? ''; // Assigning the statement URL to the file_url field

            $manager_phone=$this->getManagerPhone($user->manager_id);

            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => $addressData->phone,  // Fallback to test number $addressData->phone
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('wa_sales_queue')->insert([
                'group_id' => $groupId,
                'callback_data' => $templateData['name'],
                'recipient_type' => 'individual',
                'to_number' => '9894753728',  // Fallback to test number $addressData->phone
                'type' => 'template',
                'file_url' => $publicUrl,
                'content' => json_encode($templateData),
                'status' => 'pending',
                'response' => '',
                'msg_id' => '',
                'msg_status' => '',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update the reward status to processed
            $reward->update(['is_processed' => 1]);
        }

        // Dispatch the job to process WhatsApp messages
        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json(['message' => 'Reward statements have been queued for sending via WhatsApp.']);
    }
}
