<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Address;
use App\Models\ZohoSetting;
use App\Models\ZohoToken;
use App\Models\UserSalzingStatement;
use App\Models\Seller;

class SyncSalzingStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $clientId, $clientSecret, $redirectUri, $orgId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $settings = ZohoSetting::where('status', '0')->first();

        $this->clientId     = $settings->client_id;
        $this->clientSecret = $settings->client_secret;
        $this->redirectUri  = $settings->redirect_uri;
        $this->orgId        = $settings->organization_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $getData                 = [];
        $currentDate             = date('Y-m-d');
        $currentMonth            = date('m');
        $currentYear             = date('Y');
        $allUserCpontOfAddressData = Address::orderBy('acc_code', 'ASC')->count();

        $allUserAddressDataTemp1 = Address::where('acc_code', '!=', '')
            ->where('zoho_customer_id', '!=', '')
            // ->where('acc_code',  'OPEL0100046')
            ->where(function ($query) {
                $query->whereNull('gstin')->orWhere('gstin', '');
            })
            ->orderBy('acc_code', 'ASC')
            ->get();

        $allUserAddressDataTemp2 = Address::where('acc_code', '!=', '')
            ->where('zoho_customer_id', '!=', '')
            ->whereNotNull('gstin')
            ->where('gstin', '!=', '')
            ->groupBy('gstin')
            ->orderBy('acc_code', 'ASC')
            ->get();

        // Merge both collections
        $allUserAddressData = $allUserAddressDataTemp1->merge($allUserAddressDataTemp2);
        // $allUserAddressData = $allUserAddressDataTemp1;

        $from_date   = '2025-04-01';
        $to_date     = date('Y-m-d');
        $updateCount = 1;

        foreach ($allUserAddressData as $uAkey => $uAvalue) {
            $contactId = $uAvalue->zoho_customer_id;

            \Log::info('Update Statement of party ' . $contactId . ' with cron', [
                'status'     => 'Start',
                'party_code' => $uAvalue->acc_code,
            ]);

            $party_code  = $uAvalue->acc_code;
            $userAddress = Address::where('acc_code', $party_code)->first();
            $userData    = User::where('id', $userAddress->user_id)->first();

            $from_date       = '2025-04-01';
            $to_date         = date('Y-m-d');
            $orgId           = $this->orgId;
            $statement_data  = [];
            $cleanedStatement = [];

            // Get multiple address with same GST number.
            if ($userAddress) {
                $gstin          = $userAddress->gstin;
                $usersAllAddress = Address::where('user_id', $userData->id)
                    ->where('gstin', $gstin)
                    ->get();
            } else {
                $usersAllAddress = collect(); // Return empty collection if no address found
            }

            // Zoho API Call
            foreach ($usersAllAddress as $userAddressData) {
                $contactId       = $userAddressData->zoho_customer_id;
                $arrayBeautifier = [];

                // Get Statement from Zoho
                $url      = "https://www.zohoapis.in/books/v3/customers/{$contactId}/statements";
                $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
                    ->get($url, [
                        'from_date'       => $from_date,
                        'to_date'         => $to_date,
                        'filter_by'       => 'Status.All',
                        'accept'          => 'xls',
                        'organization_id' => $orgId,
                    ]);

                $data = $response->json();

                if ($response->successful()) {
                    // Save the response body as a file
                    $fileName   = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
                    $folderPath = public_path('zoho_statements');

                    // Create the folder if it doesn't exist
                    if (!file_exists($folderPath)) {
                        mkdir($folderPath, 0777, true);
                    }

                    $fullPath = $folderPath . '/' . $fileName;
                    file_put_contents($fullPath, $response->body());

                    $getStatementData = json_decode(json_encode($this->readStatementData($fileName)), true);
                    $getStatementData = $getStatementData['original']['data'][0];
                    $arrayBeautifier  = [];

                    foreach ($getStatementData as $key => $value) {
                        $tempArray = [];

                        if ($key > 9) {
                            if ($value[1] != "" && $value[1] != 'Customer Opening Balance') {
                                $tempVarArray = [];

                                if ($value[1] == 'Invoice' || $value[1] == 'Debit Note' || $value[1] == 'Credit Note') {
                                    $tempVarArray = explode(' - ', $value[2]);
                                } else {
                                    $tempVarArray = explode('₹', $value[2]);
                                }

                                $tempArray['trn_no'] = trim($tempVarArray[0]);

                                // change the date format for zoho tran date.
                                $raw     = trim((string) $value[0]);
                                $formats = [
                                    'd/m/Y H:i:s.u', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
                                    'd-m-Y H:i:s.u', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
                                    'Y-m-d H:i:s.u', 'Y-m-d H:i:s', 'Y-m-d',
                                ];
                                $dt = null;
                                foreach ($formats as $f) {
                                    $dt = \DateTime::createFromFormat($f, $raw);
                                    if ($dt) {
                                        break;
                                    }
                                }
                                $tempArray['trn_date'] = $dt ? $dt->format('Y-m-d') : e($raw);
                                // -----------------------------------------------------------

                                if ($value[1] == 'Invoice') {
                                    $tempArray['vouchertypebasename'] = "Sales";
                                } elseif ($value[1] == 'Debit Note' || $value[1] == 'Credit Note') {
                                    $tempArray['vouchertypebasename'] = $value[1];
                                } elseif ($value[1] == 'Payment Received') {
                                    $tempArray['vouchertypebasename'] = "Receipt";
                                } elseif ($value[1] == 'Payment Refund') {
                                    $tempArray['vouchertypebasename'] = "Payment";
                                } elseif ($value[1] == 'Customer Opening Balance') {
                                    $tempArray['vouchertypebasename'] = "Opening Balance";
                                } else {
                                    $tempArray['vouchertypebasename'] = $value[1];
                                }

                                $tempArray['ledgername'] = '';
                                $tempArray['ledgerid']   = "";

                                if (($value[1] == 'Invoice' || $value[1] == 'Debit Note') && $value[3] != "") {
                                    if ($value[3] >= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[3]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[3]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif (($value[1] == 'Payment Refund') && $value[4] != "") {
                                    if ($value[4] <= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif (($value[1] == 'Payment Received') && $value[4] != "") {
                                    if ($value[4] <= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif ($value[1] == 'Credit Note' && $value[3] != "") {
                                    $crnValue               = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[3])));
                                    $tempArray['cramount']  = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) $crnValue;
                                    $tempArray['dramount']  = (float) 0.00;
                                } elseif ($value[1] == 'Credit Note' && $value[4] != "") {
                                    $drnValue               = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[4])));
                                    $tempArray['dramount']  = $drnValue < 0 ? (float) str_replace('-', '', $drnValue) : (float) $drnValue;
                                    $tempArray['cramount']  = (float) 0.00;
                                } elseif ($value[1] == 'Journal' && $value[3] != "") {
                                    // clean formatting
                                    $amount = str_replace([',', '(', ')'], '', $value[3]);

                                    if (strpos($value[3], '(') !== false || (float) $amount < 0) {
                                        // Credit if bracketed or negative
                                        $tempArray['cramount'] = (float) abs($amount);
                                        $tempArray['dramount'] = 0.00;

                                        // $tempArray['dramount'] = (float)abs($amount);
                                        // $tempArray['cramount'] = 0.00;

                                    } else {
                                        // Otherwise Debit
                                        $tempArray['dramount'] = (float) $amount;
                                        $tempArray['cramount'] = 0.00;

                                        // $tempArray['cramount'] = (float)$amount;
                                        // $tempArray['dramount'] = 0.00;
                                    }
                                } elseif ($value[1] == 'Journal' && $value[4] != "") {
                                    // clean formatting
                                    $amount = str_replace([',', '(', ')'], '', $value[3]);

                                    if (strpos($value[3], '(') !== false || (float) $amount < 0) {
                                        // Credit if bracketed or negative
                                        $tempArray['dramount'] = (float) abs($amount);
                                        $tempArray['cramount'] = 0.00;

                                        // $tempArray['cramount'] = (float)abs($amount);
                                        // $tempArray['dramount'] = 0.00;

                                    } else {
                                        // Otherwise Debit
                                        $tempArray['cramount'] = (float) $amount;
                                        $tempArray['dramount'] = 0.00;

                                        // $tempArray['dramount'] = (float)$amount;
                                        // $tempArray['cramount'] = 0.00;
                                    }
                                } else {
                                    if ($value[3] != "") {
                                        $crnValue              = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[3])));
                                        $tempArray['cramount'] = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) $crnValue;
                                        $tempArray['dramount'] = (float) 0.00;
                                    } elseif ($value[4] != "") {
                                        $crnValue              = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[4])));
                                        $tempArray['dramount'] = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) $crnValue;
                                        $tempArray['cramount'] = (float) 0.00;
                                    }
                                }

                                $tempArray['narration'] = $value[2];
                                $arrayBeautifier[]      = $tempArray;
                            }
                        }
                    }

                    // Merge salzing and zoho statement data in an array.
                    if (count($cleanedStatement) > 0) {
                        $arrayBeautifier = array_merge($cleanedStatement, $arrayBeautifier);
                    }

                    if (File::exists($fullPath)) {
                        File::delete($fullPath);
                    }
                }

                $statement_data[] = $arrayBeautifier;
            }

            // Get salzing data from database
            foreach ($usersAllAddress as $userAddressData) {
                $contactId       = $userAddressData->zoho_customer_id;
                $salezingData    = UserSalzingStatement::where('zoho_customer_id', $contactId)->first();
                $cleanedStatement = [];

                if ($salezingData != null) {
                    // Step 1: Decode the statement
                    $salezingStatement = json_decode($salezingData->statement_data, true);

                    // Step 2: Clean 'x1' from each item
                    $cleanedStatement = array_map(function ($item) {
                        unset($item['x1']);

                        if (isset($item['overdue_status'])) {
                            unset($item['overdue_status']);
                        }

                        if (isset($item['overdue_by_day'])) {
                            unset($item['overdue_by_day']);
                        }

                        return $item;
                    }, $salezingStatement);

                    // Step 3: Remove the last item
                    array_pop($cleanedStatement);
                }

                if (count($cleanedStatement) > 0) {
                    // Remove "closing C/f......" entries
                    $filteredData = array_filter($cleanedStatement, function ($item) {
                        return !isset($item['ledgername']) || stripos($item['ledgername'], 'closing C/f...') === false;
                    });

                    $statement_data[] = $filteredData;
                }
            }

            // Get Seller Statement
            // $getSellerData = Seller::where('customer_user_id', $userData->id)->first();

            $getSellerData = Seller::where('customer_user_id', $userData->id)
                ->whereNotNull('gstin')
                ->where('gstin', $userAddress->gstin)
                ->first();

            $sellerArrayBeautifier = [];

            // if($getSellerData != null){
            if ($getSellerData != null && $userAddress && !empty($userAddress->gstin) && strcasecmp(($getSellerData->gstin ?? ''), $userAddress->gstin) === 0) {
                // $vendorContactId = '2435622000001680418'; // CLIF
                $vendorContactId = $getSellerData->zoho_seller_id;

                // Get Statement from Zoho
                $url      = "https://www.zohoapis.in/books/v3/vendors/{$vendorContactId}/statements";
                $response = Http::withHeaders($this->getAuthHeaders(), ['Accept' => 'application/xls'])
                    ->get($url, [
                        'from_date'       => $from_date,
                        'to_date'         => $to_date,
                        'filter_by'       => 'Status.All',
                        'accept'          => 'xls',
                        'organization_id' => $orgId,
                    ]);

                if ($response->successful()) {
                    // Save the response body as a file
                    $fileName   = 'zoho_statement_' . now()->format('Ymd_His') . '.xls';
                    $folderPath = public_path('zoho_statements');

                    // Create the folder if it doesn't exist
                    if (!file_exists($folderPath)) {
                        mkdir($folderPath, 0777, true);
                    }

                    $fullPath = $folderPath . '/' . $fileName;
                    file_put_contents($fullPath, $response->body());

                    $getStatementData      = json_decode(json_encode($this->readStatementData($fileName)), true);
                    $getStatementData      = $getStatementData['original']['data'][0];
                    $sellerArrayBeautifier = [];

                    foreach ($getStatementData as $key => $value) {
                        $tempArray = [];

                        if ($key > 8) {
                            if ($value[1] != "" && $value[1] != 'Customer Opening Balance') {
                                $tempVarArray = [];

                                if ($value[1] == 'Invoice' || $value[1] == 'Debit Note' || $value[1] == 'Credit Note') {
                                    $tempVarArray = explode(' - ', $value[2]);
                                } else {
                                    $tempVarArray = explode('₹', $value[2]);
                                }

                                $trn_no_array          = explode('<div>', trim($tempVarArray[0]));
                                $tempArray['trn_no']   = $trn_no_array[0];
                                $tempArray['trn_date'] = $value[0];

                                if ($value[1] == 'Invoice') {
                                    $tempArray['vouchertypebasename'] = "Sales";
                                } elseif ($value[1] == 'Debit Note' || $value[1] == 'Credit Note') {
                                    $tempArray['vouchertypebasename'] = $value[1];
                                } elseif ($value[1] == 'Payment Received') {
                                    $tempArray['vouchertypebasename'] = "Receipt";
                                } elseif ($value[1] == 'Payment Refund') {
                                    $tempArray['vouchertypebasename'] = "Payment";
                                } elseif ($value[1] == 'Customer Opening Balance') {
                                    $tempArray['vouchertypebasename'] = "Opening Balance";
                                } else {
                                    $tempArray['vouchertypebasename'] = $value[1];
                                }

                                if ($value[1] == 'Payment Made') {
                                    $tempArray['trn_no'] = 'BANK ENTRY';
                                }

                                $tempArray['ledgername'] = '';
                                $tempArray['ledgerid']   = "";

                                if (($value[1] == 'Invoice' || $value[1] == 'Debit Note') && $value[3] != "") {
                                    if ($value[3] >= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[3]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[3]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif (($value[1] == 'Payment Refund') && $value[4] != "") {
                                    if ($value[4] <= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif (($value[1] == 'Payment Received') && $value[4] != "") {
                                    if ($value[4] <= '0') {
                                        $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['cramount'] = (float) 0.00;
                                    } else {
                                        $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $value[4]));
                                        $tempArray['dramount'] = (float) 0.00;
                                    }
                                } elseif ($value[1] == 'Credit Note' && $value[3] != "") {
                                    $crnValue               = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[3])));
                                    $tempArray['cramount']  = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) $crnValue;
                                    $tempArray['dramount']  = (float) 0.00;
                                } elseif ($value[1] == 'Credit Note' && $value[4] != "") {
                                    $drnValue               = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[4])));
                                    $tempArray['dramount']  = $drnValue < 0 ? (float) str_replace('-', '', $drnValue) : (float) $drnValue;
                                    $tempArray['cramount']  = (float) 0.00;
                                } elseif ($value[1] == 'Journal' && $value[3] != "") {
                                    // clean formatting
                                    $amount = str_replace([',', '(', ')'], '', $value[3]);

                                    if (strpos($value[3], '(') !== false || (float) $amount < 0) {
                                        // Credit if bracketed or negative
                                        $tempArray['cramount'] = (float) abs($amount);
                                        $tempArray['dramount'] = 0.00;

                                        // $tempArray['dramount'] = (float)abs($amount);
                                        // $tempArray['cramount'] = 0.00;

                                    } else {
                                        // Otherwise Debit
                                        $tempArray['dramount'] = (float) $amount;
                                        $tempArray['cramount'] = 0.00;

                                        // $tempArray['cramount'] = (float)$amount;
                                        // $tempArray['dramount'] = 0.00;
                                    }
                                } elseif ($value[1] == 'Journal' && $value[4] != "") {
                                    // clean formatting
                                    $amount = str_replace([',', '(', ')'], '', $value[3]);

                                    if (strpos($value[3], '(') !== false || (float) $amount < 0) {
                                        // Credit if bracketed or negative
                                        $tempArray['dramount'] = (float) abs($amount);
                                        $tempArray['cramount'] = 0.00;

                                        // $tempArray['cramount'] = (float)abs($amount);
                                        // $tempArray['dramount'] = 0.00;

                                    } else {
                                        // Otherwise Debit
                                        $tempArray['cramount'] = (float) $amount;
                                        $tempArray['dramount'] = 0.00;

                                        // $tempArray['dramount'] = (float)$amount;
                                        // $tempArray['cramount'] = 0.00;
                                    }
                                } else {
                                    if ($value[3] != "") {
                                        $crnValue              = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[3])));
                                        $tempArray['cramount'] = $crnValue < 0 ? (float) 0.00 : (float) $crnValue;
                                        $tempArray['dramount'] = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) 0.00;
                                    } elseif ($value[4] != "") {
                                        $crnValue              = str_replace('(', '', str_replace(')', '', str_replace(',', '', $value[4])));
                                        $tempArray['dramount'] = $crnValue < 0 ? (float) str_replace('-', '', $crnValue) : (float) $crnValue;
                                        $tempArray['cramount'] = (float) 0.00;
                                    }
                                }

                                $tempArray['narration'] = $value[2];
                                $sellerArrayBeautifier[] = $tempArray;
                            }
                        }
                    }

                    if (File::exists($fullPath)) {
                        File::delete($fullPath);
                    }

                    $statement_data[] = $sellerArrayBeautifier;
                }
            }

            $mergedData = [];
            foreach ($statement_data as $data) {
                $mergedData = array_merge($mergedData, $data);
            }

            $statement_data = array_values($mergedData);

            // Merge salezing data and zoho data
            usort($statement_data, function ($a, $b) {
                return strtotime($a['trn_date']) - strtotime($b['trn_date']);
            });

            // calculate the running ballance
            $balance = 0.00;
            foreach ($statement_data as $gKey => $gValue) {
                $balance += (float) $gValue['dramount'] - (float) $gValue['cramount'];
                $statement_data[$gKey]['running_balance'] = $balance;
            }

            // Insert closing balance into array
            $tempArray['trn_no']              = "";
            $tempArray['trn_date']            = date('Y-m-d');
            $tempArray['vouchertypebasename'] = "";
            $tempArray['ledgername']          = "closing C/f...";
            $tempArray['ledgerid']            = "";

            if ($balance <= 0) {
                $tempArray['cramount'] = (float) str_replace('-', '', str_replace(',', '', $balance));
                $tempArray['dramount'] = (float) 0.00;
            } else {
                $tempArray['dramount'] = (float) str_replace('-', '', str_replace(',', '', $balance));
                $tempArray['cramount'] = (float) 0.00;
            }

            $tempArray['narration']  = "";
            $statement_data[]        = $tempArray;
            $finalStatementArray     = $statement_data;

            // Start the statement data as like salzing
            $overdueAmount   = "0";
            $openingBalance  = "0";
            $openDrOrCr      = "";
            $closingBalance  = "0";
            $closeDrOrCr     = "";
            $dueAmount       = "0";
            $overdueDateFrom = "";
            $overdueDrOrCr   = "";
            $overDueMark     = [];
            $drBalance       = 0;
            $crBalance       = 0;

            $getUserData = Address::with('user')->where('zoho_customer_id', $contactId)->first();
            $userData    = $getUserData->user;
            $getOverdueData = $finalStatementArray;

            $closingBalanceResult = array_filter($getOverdueData, function ($entry) {
                return isset($entry['ledgername']) && $entry['ledgername'] === 'closing C/f...';
            });

            $closingEntry     = reset($closingBalanceResult);
            $cloasingDrAmount = $closingEntry['dramount'];
            $cloasingCrAmount = $closingEntry['cramount'];

            $overdueDateFrom = date('Y-m-d', strtotime('-' . $userData->credit_days . ' days'));

            if ($cloasingDrAmount > 0) {
                $drBalanceBeforeOVDate = 0;
                $crBalanceBeforeOVDate = 0;

                $getOverdueData = array_reverse($getOverdueData);

                foreach ($getOverdueData as $ovKey => $ovValue) {
                    if ($ovValue['ledgername'] != 'closing C/f...') {
                        if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        } else {
                            $drBalanceBeforeOVDate += $ovValue['dramount'];
                            $crBalanceBeforeOVDate += $ovValue['cramount'];
                        }
                    }
                }

                $overdueAmount   = $temOverDueBalance = $drBalanceBeforeOVDate - $crBalanceBeforeOVDate;
                $overDueMark     = [];

                foreach ($getOverdueData as $ovKey => $ovValue) {
                    if ($ovValue['ledgername'] != 'closing C/f...') {
                        if (strtotime($ovValue['trn_date']) > strtotime($overdueDateFrom)) {
                            // $drBalanceBeforeOVDate += $ovValue['dramount'];
                            // $crBalanceBeforeOVDate += $ovValue['cramount'];
                        } elseif (strtotime($ovValue['trn_date']) <= strtotime($overdueDateFrom) && $temOverDueBalance > 0 && $ovValue['dramount'] != '0.00') {
                            $temOverDueBalance -= $ovValue['dramount'];
                            $date1              = $ovValue['trn_date'];
                            $date2              = $overdueDateFrom;
                            $diff               = abs(strtotime($date2) - strtotime($date1));
                            $dateDifference     = floor($diff / (60 * 60 * 24)) . ' days';

                            if ($temOverDueBalance >= 0) {
                                $overDueMark[] = [
                                    'trn_no'         => $ovValue['trn_no'],
                                    'trn_date'       => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus'  => 'Overdue',
                                ];
                            } else {
                                $overDueMark[] = [
                                    'trn_no'         => $ovValue['trn_no'],
                                    'trn_date'       => $ovValue['trn_date'],
                                    'overdue_by_day' => $dateDifference,
                                    'overdue_staus'  => 'Pertial Overdue',
                                ];
                            }
                        }
                    }
                }
            }

            if ($overdueAmount <= 0) {
                $overdueDrOrCr = 'Cr';
                $overdueAmount = 0;
            } else {
                $overdueDrOrCr = 'Dr';
            }

            $getData = $finalStatementArray;

            if (count($overDueMark) > 0) {
                $overDueMarkTrnNos      = array_column($overDueMark, 'trn_no');
                $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
                $overDueMarkByDay       = array_column($overDueMark, 'overdue_by_day');
            }

            foreach ($getData as $gKey => $gValue) {
                if ($gValue['ledgername'] == "Opening b/f...") {
                    if ($gValue['dramount'] != "0.00") {
                        $openingBalance = $gValue['dramount'];
                        $openDrOrCr     = "Dr";
                    } else {
                        $openingBalance = $gValue['cramount'];
                        $openDrOrCr     = "Cr";
                    }
                } elseif ($gValue['ledgername'] == "closing C/f...") {
                    if ($gValue['dramount'] != "0.00") {
                        $closingBalance = $gValue['dramount'];
                        // $dueAmount = $gValue['dramount'];
                        // $closeDrOrCr = "Dr";
                    } else {
                        $closingBalance = $gValue['cramount'];
                        // $closeDrOrCr = "Cr";
                        // $dueAmount = $gValue['cramount'];
                    }
                }

                if (count($overDueMark) > 0) {
                    $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);

                    if ($key !== false) {
                        $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                        $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                    } else {
                        if (isset($getData[$gKey]['overdue_status'])) {
                            unset($getData[$gKey]['overdue_status']);
                            unset($getData[$gKey]['overdue_by_day']);
                        }
                    }
                } else {
                    if (isset($getData[$gKey]['overdue_status'])) {
                        unset($getData[$gKey]['overdue_status']);
                        unset($getData[$gKey]['overdue_by_day']);
                    }
                }

                if ($gValue['dramount'] != 0.00 && $gValue['ledgername'] != 'closing C/f...') {
                    $drBalance = $drBalance + $gValue['dramount'];
                    $dueAmount = $dueAmount + $gValue['dramount'];
                }

                if ($gValue['cramount'] != 0.00 && $gValue['ledgername'] != 'closing C/f...') {
                    $crBalance = $crBalance + $gValue['cramount'];
                    $dueAmount = $dueAmount - $gValue['cramount'];
                }
            }

            $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';

            // Update value with blank
            foreach ($usersAllAddress as $userAddressData) {
                if ($userAddressData && $userAddressData->acc_code) {
                    Address::where('acc_code', $userAddressData->acc_code)->update([
                        'due_amount'     => "0.00",
                        'dueDrOrCr'      => null,
                        'overdue_amount' => "0.00",
                        'overdueDrOrCr'  => null,
                        'statement_data' => null,
                    ]);
                }
            }

            Address::where('acc_code', $party_code)
                ->update([
                    'due_amount'     => $dueAmount,
                    'dueDrOrCr'      => $closeDrOrCr,
                    'overdue_amount' => $overdueAmount,
                    'overdueDrOrCr'  => $overdueDrOrCr,
                    'statement_data' => json_encode($getData),
                ]);

            \Log::info('Update Statement of party ' . $contactId . ' with cron', [
                'status'      => 'End',
                'information' => 'Updated ' . $updateCount . ' records out of ' . $allUserCpontOfAddressData,
                'party_code'  => $uAvalue->acc_code,
            ]);

            sleep(1); // Delay of 1 second between requests

            $updateCount++;

            // $userAddressData = Address::where('acc_code',"!=","")->groupBy('acc_code')->orderBy('acc_code','ASC')->get();

            $userAddressData = Address::where('acc_code', $party_code)
                ->select('addresses.*')
                ->distinct('gstin')
                ->orderBy('acc_code', 'ASC')
                ->get();

            $rewardCount = 1;

            foreach ($userAddressData as $key => $value) {
                // echo "<pre>"; print_r($value);die;
                $userData = User::where('id', $value->user_id)->first();
                $url      = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';

                $response = Http::get($url, [
                    'address_id' => $value->id,
                    // 'data_from' => 'live',
                    'data_from'  => 'database',
                ]);

                \Log::info($rewardCount . '. Early Rewards Point calculate of ' . $value->acc_code . " - " . $value->company_name);
                $rewardCount++;
            }
        }

        \Log::info('Finish update Statement ' . ($updateCount - 1) . ' records out of ' . $allUserCpontOfAddressData);

        return response()->json(['status' => 'success']);
    }

    private function getAuthHeaders()
    {
        $token = ZohoToken::first();

        if (!$token) {
            abort(403, 'Zoho token not found.');
        }

        // Refresh token if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $settings = ZohoSetting::first();

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
                abort(403, 'Failed to refresh Zoho token.');
            }
        }

        return [
            'Authorization' => 'Zoho-oauthtoken ' . $token->access_token,
            'Content-Type'  => 'application/json',
        ];
    }

    private function readStatementData($filename)
    {
        $filePath = public_path('zoho_statements/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Read the file as a Collection
        $data = Excel::toCollection(null, $filePath);

        return response()->json([
            'data' => $data, // usually the first sheet
        ]);
    }
}