<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\RewardPointsOfUser;

class ImportRewardsCreditNotes implements ToCollection, WithHeadingRow
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
        foreach ($rows as $row) { 
            if($row['party_code'] != ""){  
                if($exRewards != 0){
                    $exRewards += $row['rewards'];
                }else{
                    $exRewards = $row['rewards'];
                }
                $credit_rewards = 0;
                $remaining_rewards = 0;
                $getRewardsData = RewardPointsOfUser::where('party_code', $row['party_code'])
                        ->where('dr_or_cr', 'dr')
                        ->where('reward_complete_status', '<>', '1')
                        ->orderBy('id','ASC')->get();
                foreach($getRewardsData as $rKey=>$rValue){                
                    if($exRewards != 0 AND $rValue->remaining_rewards > 0 AND $exRewards >= $rValue->remaining_rewards){
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
                        if($exRewards != 0 AND $exRewards == $rValue->rewards){
                            $credit_rewards = $exRewards;
                            $remaining_rewards = 0;
                            $exRewards = 0;

                            RewardPointsOfUser::where('id', $rValue->id)
                            ->update([
                                'credit_rewards' => $credit_rewards,
                                'remaining_rewards' => $remaining_rewards,
                                'reward_complete_status' => 1,
                            ]);

                        }elseif($exRewards != 0 AND $exRewards >= $rValue->rewards){
                            $credit_rewards = $rValue->rewards;
                            $remaining_rewards = 0;
                            $exRewards = $exRewards - $rValue->rewards;
                            RewardPointsOfUser::where('id', $rValue->id)
                            ->update([
                                'credit_rewards' => $credit_rewards,
                                'remaining_rewards' => $remaining_rewards,
                                'reward_complete_status' => 1,
                            ]);
                        }elseif($exRewards != 0 AND $exRewards <= $rValue->rewards){
                            $credit_rewards = $exRewards;
                            $remaining_rewards = $rValue->rewards - $exRewards;
                            $exRewards = 0;

                            RewardPointsOfUser::where('id', $rValue->id)
                            ->update([
                                'credit_rewards' => $credit_rewards,
                                'remaining_rewards' => $remaining_rewards,
                                'reward_complete_status' => 2,
                            ]);
                        }
                    }
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
