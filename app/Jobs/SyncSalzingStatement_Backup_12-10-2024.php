<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\Address;

class SyncSalzingStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $currentDate = date('Y-m-d');
        $currentMonth = date('m');
        $currentYear = date('Y');

        if ($currentMonth >= 4) {
            $fy_form_date = date('Y-04-01');
            $fy_to_date = date('Y-03-31', strtotime('+1 year'));
        } else {
            $fy_form_date = date('Y-04-01', strtotime('-1 year'));
            $fy_to_date = date('Y-03-31');
        }

        $from_date = $fy_form_date;
        $to_date = $fy_to_date;
        $headers = [
            'authtoken' => '65d448afc6f6b',
        ];

        Address::where('acc_code', '!=', "")
            ->groupBy('gstin')
            ->orderBy('acc_code', 'ASC')
            ->chunk(10, function ($userAddressData) use ($from_date, $to_date, $headers) {
                foreach ($userAddressData as $value) {
                    $this->processAddress($value, $from_date, $to_date, $headers);
                    sleep(1); // Delay between requests
                }
            });
    }

    private function processAddress($value, $from_date, $to_date, $headers)
    {
        $overdueAmount = "0";
        $openingBalance = "0";
        $openDrOrCr = "";
        $closingBalance = "0";
        $closeDrOrCr = "";
        $dueAmount = "0";
        $overdueDateFrom = "";
        $overdueDrOrCr = "";

        $userData = User::where('id', $value->user_id)->first();
        $body = [
            'party_code' => $value->acc_code,
            'from_date' => $from_date,
            'to_date' => $to_date,
        ];

        $overdue_response = Http::withHeaders($headers)
            ->retry(3, 100)
            ->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);

        \Log::info('Received response from Salzing API For Sync Statement Overdue Calculation', [
            'status' => $overdue_response->status(),
            'party_code' => $value->acc_code,
            'body' => $overdue_response->body()
        ]);

        if ($overdue_response->successful()) {
            $getOverdueData = $overdue_response->json();
            $overDueMark = [];
            if (!empty($getOverdueData) && isset($getOverdueData['data']) && !empty($getOverdueData['data'])) {
                $this->calculateOverdueData($getOverdueData['data'], $userData, $overdueAmount, $overdueDrOrCr, $overDueMark);
            }

            $response = Http::withHeaders($headers)
                ->retry(3, 100)
                ->post('https://saleszing.co.in/itaapi/getclientstatement.php', $body);

            \Log::info('Received response from Salzing API For Sync Statement', [
                'status' => $response->status(),
                'party_code' => $value->acc_code,
                'body' => $response->body()
            ]);

            $getData = $response->json();
            if (!empty($getData) && isset($getData['data']) && !empty($getData['data'])) {
                $this->updateAddressData($userData, $getData['data'], $overDueMark, $value, $overdueAmount, $overdueDrOrCr);
            }
        }
    }

    private function calculateOverdueData($getOverdueData, $userData, &$overdueAmount, &$overdueDrOrCr, &$overDueMark)
    {
        $overdueAmount = 0;
        $overdueDrOrCr = '';
        $overDueMark = [];

        foreach ($getOverdueData as $data) {
            $dueDate = isset($data['due_date']) ? $data['due_date'] : null;
            $transactionNo = $data['trn_no'] ?? '';
            $drAmount = $data['dramount'] ?? 0;
            $crAmount = $data['cramount'] ?? 0;
            $ledgerName = $data['ledgername'] ?? '';

            if ($dueDate && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
                $daysOverdue = (strtotime(date('Y-m-d')) - strtotime($dueDate)) / 86400;
                $overDueMark[] = [
                    'trn_no' => $transactionNo,
                    'overdue_staus' => 'Overdue',
                    'overdue_by_day' => $daysOverdue,
                ];

                $overdueAmount += $drAmount;
            }
        }

        $overdueDrOrCr = $overdueAmount > 0 ? 'Dr' : 'Cr';
    }

    private function updateAddressData($userData, $getData, $overDueMark, $value, $overdueAmount, $overdueDrOrCr)
    {
        $openingBalance = 0;
        $closingBalance = 0;
        $openDrOrCr = "";
        $drBalance = 0;
        $crBalance = 0;
        $closeDrOrCr = "";
        $dueAmount = 0;

        if (count($overDueMark) > 0) {
            $overDueMarkTrnNos = array_column($overDueMark, 'trn_no');
            $overDueMarkOverdueStaus = array_column($overDueMark, 'overdue_staus');
            $overDueMarkByDay = array_column($overDueMark, 'overdue_by_day');
        }

        foreach ($getData as $gKey => $gValue) {
            if ($gValue['ledgername'] == "Opening b/f...") {
                if ($gValue['dramount'] != "0.00") {
                    $openingBalance = $gValue['dramount'];
                    $openDrOrCr = "Dr";
                } else {
                    $openingBalance = $gValue['cramount'];
                    $openDrOrCr = "Cr";
                }
            } else if ($gValue['ledgername'] == "closing C/f...") {
                if ($gValue['dramount'] != "0.00") {
                    $closingBalance = $gValue['dramount'];
                } else {
                    $closingBalance = $gValue['cramount'];
                }
            }
            if (count($overDueMark) > 0) {
                $key = array_search($gValue['trn_no'], $overDueMarkTrnNos);
                if ($key !== false) {
                    $getData[$gKey]['overdue_status'] = $overDueMarkOverdueStaus[$key];
                    $getData[$gKey]['overdue_by_day'] = $overDueMarkByDay[$key];
                }
            }
            if ($gValue['dramount'] != 0.00 && $gValue['ledgername'] != 'closing C/f...') {
                $drBalance += $gValue['dramount'];
                $dueAmount += $gValue['dramount'];
            }
            if ($gValue['cramount'] != '0.00' && $gValue['ledgername'] != 'closing C/f...') {
                $crBalance += $gValue['cramount'];
                $dueAmount -= $gValue['cramount'];
            }
        }

        $closeDrOrCr = $drBalance > $crBalance ? 'Dr' : 'Cr';

        Address::where('acc_code', $value->acc_code)
            ->update([
                'due_amount' => $dueAmount,
                'dueDrOrCr' => $closeDrOrCr,
                'overdue_amount' => $overdueAmount,
                'overdueDrOrCr' => $overdueDrOrCr,
                'statement_data' => json_encode($getData),
            ]);

        $url = 'https://mazingbusiness.com/mazing_business_react/api/saleszing/saleszing-statement-get';
        $response = Http::get($url, [
            'user_id' => $userData->id,
            'data_from' => 'live',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return response()->json(['status' => 'success', 'data' => $data]);
        }
    }
}
