<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\RewardPointsOfUser;

use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportRewardsCreditNotes implements  ToCollection, WithHeadingRow
{
    protected $tableName;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }
    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        $exRewards = 0;
        $party_code = "";
        foreach ($rows as $row) {
            if (!empty($row['ledger_code'])) {
                $getData = RewardPointsOfUser::where('invoice_no',$row['voucher_number'])->first();
                if(!$getData){
                    // Insert Credit note into table
                    $data = array();
                    $data['party_code']= $row['ledger_code'];
                    $data['invoice_no']= $row['voucher_number'];
                    // $data['rewards_from']= $row['rewards_from'];
                    $data['rewards']= $row['voucher_amount'];
                    $data['dr_or_cr']= 'cr';
                    $data['voucher_date']= Date::excelToDateTimeObject($row['voucher_date'])->format('Y-m-d');

                    // if(isset($row['canceled_on']) AND $row['canceled_on'] != "0000-00-00 00:00:00"){
                    //     $data['canceled_on']= $row['canceled_on'];
                    // }
                    // $data['cancel_reason']= $row['cancel_reason'];
                    RewardPointsOfUser::create($data);
                    
                    if(!isset($data['canceled_on'])){
                       // Accumulate rewards
                        if($party_code == $row['ledger_code']){
                            $exRewards += $row['voucher_amount'];
                        }else{
                            $exRewards = $row['voucher_amount'];
                        }

                        // Fetch data for the current party_code
                        $getRewardsData = RewardPointsOfUser::where('party_code', $row['ledger_code'])
                            ->where('dr_or_cr', 'dr')
                            ->where('reward_complete_status', '<>', '1')
                            ->orderBy('id', 'ASC')
                            ->get();

                        foreach ($getRewardsData as $rValue) {
                            if ($exRewards > 0) {
                                if($rValue->remaining_rewards > 0 AND $exRewards >= $rValue->remaining_rewards){
                                    $credit_rewards = $rValue->rewards;
                                    $remaining_rewards = 0;
                                    $exRewards = $exRewards - $rValue->remaining_rewards;
                                    RewardPointsOfUser::where('id', $rValue->id)
                                    ->update([
                                        'credit_rewards' => $credit_rewards,
                                        'remaining_rewards' => $remaining_rewards,
                                        'reward_complete_status' => 1,
                                    ]);
                                }else{
                                    if ($exRewards >= $rValue->rewards) {
                                        // Case 1: exRewards can completely cover remaining_rewards
                                        $credit_rewards = $rValue->rewards;
                                        $remaining_rewards = 0;
                                        $exRewards -= $rValue->rewards;

                                        // Update the record
                                        RewardPointsOfUser::where('id', $rValue->id)
                                            ->update([
                                                'credit_rewards' => $credit_rewards,
                                                'remaining_rewards' => $remaining_rewards,
                                                'reward_complete_status' => 1, // Fully satisfied
                                            ]);
                                    } else {
                                        // Case 2: exRewards can partially cover remaining_rewards
                                        $credit_rewards = $exRewards;
                                        $remaining_rewards = $rValue->rewards - $exRewards;
                                        $exRewards = 0;
                                        // Update the record
                                        RewardPointsOfUser::where('id', $rValue->id)
                                            ->update([
                                                'credit_rewards' => $credit_rewards,
                                                'remaining_rewards' => $remaining_rewards,
                                                'reward_complete_status' => 2, // Partially satisfied
                                            ]);
                                    }
                                }
                            }
                        }
                        $party_code = $row['ledger_code']; 
                    }
                }else{

                    // Fetch data for the current party_code
                    $getRevRewardsData = RewardPointsOfUser::where('party_code', $row['ledger_code'])
                    ->where('dr_or_cr', 'dr')
                    ->where('reward_complete_status', '<>', '0')
                    ->orderBy('id', 'DESC')
                    ->get();

                    if($party_code == $row['ledger_code']){
                        $revRewards += $getData->rewards;
                    }else{
                        $revRewards = $getData->rewards;
                    }

                    foreach ($getRevRewardsData as $rValue) {
                        if($revRewards > 0){
                            if($rValue->remaining_rewards > 0 AND $revRewards >= $rValue->remaining_rewards){
                                $credit_rewards = 0;
                                $remaining_rewards = 0;
                                $revRewards = $revRewards - $rValue->credit_rewards;
                                RewardPointsOfUser::where('id', $rValue->id)
                                ->update([
                                    'credit_rewards' => $credit_rewards,
                                    'remaining_rewards' => $remaining_rewards,
                                    'reward_complete_status' => 0,
                                ]);
                            }else{
                                if ($revRewards >= $rValue->rewards) {
                                    // Case 1: exRewards can completely cover remaining_rewards
                                    $credit_rewards = 0;
                                    $remaining_rewards = 0;
                                    $revRewards -= $rValue->rewards;

                                    // Update the record
                                    RewardPointsOfUser::where('id', $rValue->id)
                                        ->update([
                                            'credit_rewards' => $credit_rewards,
                                            'remaining_rewards' => $remaining_rewards,
                                            'reward_complete_status' => 0, // Fully satisfied
                                        ]);
                                } else {
                                    // Case 2: exRewards can partially cover remaining_rewards
                                    $credit_rewards = $revRewards;
                                    $remaining_rewards = $rValue->rewards - $revRewards;
                                    $revRewards = 0;
                                    // Update the record
                                    RewardPointsOfUser::where('id', $rValue->id)
                                        ->update([
                                            'credit_rewards' => $credit_rewards,
                                            'remaining_rewards' => $remaining_rewards,
                                            'reward_complete_status' => 2, // Partially satisfied
                                    ]);
                                }
                            }
                        }
                    }


                    if(isset($row['canceled_on']) AND $row['canceled_on'] != "0000-00-00 00:00:00"){
                        $getData->canceled_on = Date::excelToDateTimeObject($row['canceled_on'])->format('Y-m-d');
                    }
                    $getData->voucher_date = Date::excelToDateTimeObject($row['voucher_date'])->format('Y-m-d');
                    $getData->cancel_reason = $row['cancel_reason'];
                    $getData->save();
                }
            }
        }
    }
    
    /**
     * Convert Excel serial date to Carbon date
     * 
     * @param mixed $excelDate
     * @return string
     */
    private function transformDate($excelDate)
    {
        if (is_numeric($excelDate)) {
            // Excel stores dates as number of days since 1900-01-01
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($excelDate))->format('Y-m-d');
        }
        
        // If it's already in a date format, return it directly
        return Carbon::parse($excelDate)->format('Y-m-d');
    }

    public function chunkSize(): int
    {
        return 100; // Number of rows per chunk
    }
}
