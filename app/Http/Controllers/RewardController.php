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
use App\Http\Controllers\RewardReminderController;
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
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Services\PdfContentService;

class RewardController extends Controller
{
    protected $sheetsService;
    public function __construct(GoogleSheetsService $sheetsService){
        $this->sheetsService = $sheetsService;
    }


    public function getReminderDates()
{

    //Update part remainder_sent and is_processed

    // Fetch all records for the specified invoice
    $reminders = RewardRemainderEarlyPayment::select('id', 'invoice_date', 'reminder_sent', 'is_processed')
    // ->where('invoice_no', 'DEL/1732/24-25')
    ->where('reminder_sent','0')
        

        ->get();
    // echo "<pre>";print_r($reminders);die();

     if ($reminders->isEmpty()) {
        Log::warning("No records found.");
        return response()->json(['message' => 'No records found.'], 404);
    }

    $data = [];
    $currentDate = Carbon::parse('2025-02-22'); // Assuming today's date

    foreach ($reminders as $reminder) {
        $transactionDate = Carbon::parse($reminder->invoice_date);

        // Calculate reminder dates based on invoice_date
        $firstReminderDate = $transactionDate->copy()->addDays(14);
        $secondReminderDate = $transactionDate->copy()->addDays(39);

        // Both reminders have passed → Set `reminder_sent = 2`
        if ($firstReminderDate->isPast() && $secondReminderDate->isPast() && $reminder->reminder_sent < 2) {
            $reminder->update(['reminder_sent' => 2, 'is_processed' => 1]);
            Log::info("Both reminders passed, updated to 2: Invoice No: {$reminder->invoice_no}");
        }
        // First reminder passed, Second reminder in future → Set `reminder_sent = 1`
        elseif ($firstReminderDate->isPast() && !$secondReminderDate->isPast() && $reminder->reminder_sent == 0) {
            $reminder->update(['reminder_sent' => 1, 'is_processed' => 1]);
            Log::info("First reminder passed, updated to 1: Invoice No: {$reminder->invoice_no}");
        }

        $data[] = [
            'id' => $reminder->id,
            'invoice_no' => $reminder->invoice_no,
            'invoice_date' => $reminder->invoice_date,
            'first_reminder_date' => $firstReminderDate->format('Y-m-d'),
            'second_reminder_date' => $secondReminderDate->format('Y-m-d'),
            'reminder_sent' => $reminder->reminder_sent,
            'is_processed' => $reminder->is_processed
        ];
    }

    return response()->json($data, 200);
}


    public function rewardUserList(Request $request){
        $city = $request->city ? $request->city : "";
        $assigned_warehouse = $request->assigned_warehouse ? $request->assigned_warehouse : "";
        $manager = $request->manager ? $request->manager : "";
        $search_text = $request->search_text ? $request->search_text : "";

        $userList = RewardUser::with([
                'warehouse:id,name',
                'user_data:id,party_code,company_name,name,phone,manager_id',
                'user_data.getManager:id,name'
            ])
            ->when(!empty($city), function ($query) use ($city) {
                $query->where('city', $city);
            })
            ->when(!empty($assigned_warehouse), function ($query) use ($assigned_warehouse) {
                $query->where('assigned_warehouse', $assigned_warehouse);
            })
            ->when(!empty($search_text), function ($query) use ($search_text) {
                $query->whereRaw('party_code COLLATE utf8mb3_unicode_ci LIKE ?', ["%$search_text%"])
                    ->orWhereHas('user_data', function ($subQuery) use ($search_text) {
                        $subQuery->whereRaw('company_name COLLATE utf8mb3_unicode_ci LIKE ?', ["%$search_text%"]);
                    });
            })
            ->get()->groupBy('user_data.party_code')
            ->map(function ($group) use ($manager) {
                $firstUser = $group->first(); // Get the first user in the group
                
                // Safely handle null `user_data`
                $userData = $firstUser->user_data; // Access as an object

                if ($manager != "" && ($userData && $userData->getManager && $userData->getManager->id != $manager)) {
                    return null; // Exclude non-matching records
                }

                return [
                    'user_id' => $userData ? $userData->id : null,
                    'party_code' => $userData ? $userData->party_code : 'N/A',
                    'company_name' => $userData ? $userData->company_name : 'N/A',
                    'name' => $userData ? $userData->name : 'N/A',
                    'phone' => $userData ? $userData->phone : 'N/A',
                    'assigned_warehouse' => $firstUser->assigned_warehouse ?? 'N/A', // Access assigned_warehouse from RewardUser
                    'city' => $firstUser->city ?? 'N/A', // Access city from RewardUser
                    'managerName' => $userData && $userData->getManager ? $userData->getManager->name : 'N/A', // Access manager name
                    'warehouses' => $group->map(function ($item) {
                        return [
                            'reward_id' => $item->id,
                            'warehouse_id' => $item->warehouse ? $item->warehouse->id : null, // Handle null cases
                            'warehouse_name' => $item->warehouse ? $item->warehouse->name : 'N/A',
                            'preference' => $item->preference,
                            'rewards_percentage' => $item->rewards_percentage
                        ];
                    })->values()->toArray(),
                ];
            })
            ->filter(function ($item) {
                return !is_null($item); // Filter out null items
            })
            ->values();
        // echo "<pre>"; print_r($userList);exit;
        $userList = $userList->paginate(15);
        
        $cityList = RewardUser::select('city')->groupBy('city')->orderBy('city','ASC')->get();
        $assignedWarehouseList = RewardUser::select('assigned_warehouse')->groupBy('assigned_warehouse')->orderBy('assigned_warehouse','ASC')->get();

        $managerList = RewardUser::with([
            'user_data:id,party_code,company_name,manager_id',
            'user_data.manager:id,name'
        ])
        ->get()
        ->filter(function ($item) {
            return isset($item->user_data->manager);
        })
        ->groupBy('user_data.party_code')
        ->map(function ($group) {
            $firstItem = $group->first();
            return [
                'manager_id' => $firstItem->user_data->manager->id,
                'manager_name' => $firstItem->user_data->manager->name,
            ];
        })->values();

        // echo "<pre>"; print_r($assignedWarehouseList); exit;
        return view('backend.reward.index', compact('userList','cityList','assignedWarehouseList','managerList','city','assigned_warehouse','manager','search_text'));
    }

    public function updatePreferance(Request $request)
    {
        $rewardData = RewardUser::where('id',$request->id)->first();
        $rewardData->preference = $request->status;        
        if($rewardData->rewards_percentage == "" AND $request->status == '1'){
            $rewardData->rewards_percentage = '1';
        }
        if($rewardData->save()){
            return response()->json(['status' => 1, 'preference' => $rewardData->preference, 'rewards_percentage' => $rewardData->rewards_percentage]);
        }
        return response()->json(['status' => 0]);
    }

    public function updateReward(Request $request)
    {
        $rewardData = RewardUser::where('id',$request->id)->first();
        $rewardData->rewards_percentage = $request->rewards_percentage;  
        if($rewardData->preference == '1'){
            $rewardData->save();
            return response()->json(['status' => 1, 'preference' => $rewardData->preference, 'rewards_percentage' => $rewardData->rewards_percentage]);
        }else{
            return response()->json(['status' => 0]);
        }
    }
    

    public function pullPartyCodeIntoGoogleSheet(Request $request){

        $userList = RewardUser::with(['warehouse:id,name', 'user_data:id,party_code,company_name,name,phone,warehouse_id', 'user_data.user_warehouse:id,name', 'address_with_party_code:id,user_id,acc_code,city'])
            ->get()
            ->filter(function ($item) {
                return $item->user_data !== null; // Exclude records without user_data
            })
            ->groupBy('user_data.party_code')
            ->map(function ($group) {
                $firstUser = $group->first();
                return [
                    'party_code' => $firstUser->user_data['party_code'],
                    'company_name' => $firstUser->user_data['company_name'],
                    'city' => isset($firstUser->address_with_party_code->city) ? ucfirst($firstUser->address_with_party_code->city): 'N/A', // Correct access to city
                    'user_warehouse' => $firstUser->user_data->user_warehouse->name ?? 'N/A', // Access warehouse name correctly
                    'warehouses' => $group->map(function ($item) {
                        return [
                            'reward_id' => $item->id,
                            'warehouse_id' => $item->warehouse['id'] ?? null,
                            'warehouse_name' => $item->warehouse['name'] ?? '',
                            'preference' => $item->preference,
                            'rewards_percentage' => $item->rewards_percentage,
                        ];
                    })->values()->toArray(),
                ];
            })->values();
            $values = $userList->map(function ($user) {
                return [
                    $user['company_name'] ?? '',
                    $user['party_code'] ?? '',
                    $user['city'] ?? '',
                    $user['user_warehouse'] ?? '',
                    $user['warehouses'][0]['preference'] ?? 0,
                    $user['warehouses'][0]['rewards_percentage'] ?? 0,
                    $user['warehouses'][1]['preference'] ?? 0,
                    $user['warehouses'][1]['rewards_percentage'] ?? 0,
                    $user['warehouses'][2]['preference'] ?? 0,
                    $user['warehouses'][2]['rewards_percentage'] ?? 0,
                    $user['warehouses'][3]['preference'] ?? 0,
                    $user['warehouses'][3]['rewards_percentage'] ?? 0,
                    $user['warehouses'][4]['preference'] ?? 0,
                    $user['warehouses'][4]['rewards_percentage'] ?? 0,
                ];
            })->toArray();
        // echo "<pre>"; print_r($values);exit;
        // Clear previous data
        $range = config('sheets.get_rewards');
        $this->sheetsService->clearData($range);

        // // Append data to Google Sheets
        $this->sheetsService->appendData($range, $values);

        $userNotInRewards = User::with(['address_with_party_code:id,user_id,acc_code,city', 'warehouse:id,name'])
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')->from('reward_users');
            })
            ->where('party_code', '!=', '')
            ->orderBy('id', 'ASC')
            ->get();

        // Map the user data into the desired format
        $values = $userNotInRewards->map(function ($user) {
            $address = $user->address_with_party_code->first(); // Get the first address
            return [
                $user->company_name ?? '',
                $user->party_code ?? '',
                $address ? ucfirst($address->city) : 'N/A',
                $user->warehouse ? $user->warehouse->name : 'N/A',
            ];
        })->toArray();

        // Clear previous data
        // $clearRange = config('sheets.get_rewards');
        // $this->sheetsService->clearData($clearRange);

        $range = config('sheets.range_rewards');
        $this->sheetsService->appendData($range, $values);

        return response()->json(['status' => 'success','userData'=>$values]);
    }

    public function insertDataFromGoogleSheet(Request $request){
        // Specify the range of data you want to fetch from the Google Sheet
        $range = config('sheets.get_rewards'); // e.g., 'Sheet1!A2:M' to fetch from A2 to M

        // Fetch data from Google Sheets
        $rows = $this->sheetsService->getData($range);
        foreach ($rows as $row) {
            $company_name =  $row[0] ?? ''; 
            $party_code =  $row[1] ?? '';            
            $city =  ucfirst($row[2]) ?? '';
            $assigned_warehouse =  $row[3] ?? '';
            $kolkata = $row[4] ?? '';
            $kr = $row[5] ?? '';
            $delhi = $row[6] ?? '';
            $dr = $row[7] ?? '';
            $mumbai = $row[8] ?? '';
            $mr = $row[9] ?? '';
            $chennai = $row[10] ?? '';
            $cr = $row[11] ?? '';
            $pune = $row[12] ?? '';
            $pr = $row[13] ?? '';
            
            $getUserData=User::select('id','party_code')->where('party_code',$party_code)->first();
            // echo "<pre>"; print_r($getUserData);
            // For Kolkata
            $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '1')->first();
            if ($rewardsData) {                
                $rewardsData->update([
                    'preference' => $kolkata,
                    'company_name' => $company_name,
                    'rewards_percentage' => $kr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ]);
            }else{
                $data = [
                    'user_id' => $getUserData->id,
                    'company_name' => $company_name,
                    'party_code' => $getUserData->party_code,                    
                    'warehouse_id' => '1',
                    'warehouse_name' => 'Kolkata',
                    'preference' => $kolkata,
                    'rewards_percentage' => $kr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ];
                RewardUser::create($data);
            }
            // For Delhi
            $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '2')->first();
            if ($rewardsData) {                
                $rewardsData->update([
                    'preference' => $delhi,
                    'company_name' => $company_name,
                    'rewards_percentage' => $dr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ]);
            }else{
                $data = [
                    'user_id' => $getUserData->id,
                    'company_name' => $company_name,
                    'party_code' => $getUserData->party_code,
                    'warehouse_id' => '2',
                    'warehouse_name' => 'Delhi',
                    'preference' => $delhi,
                    'rewards_percentage' => $dr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ];
                RewardUser::create($data);
            }
            // For Chennai
            $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '3')->first();
            if ($rewardsData) {                
                $rewardsData->update([
                    'preference' => $chennai,
                    'company_name' => $company_name,
                    'rewards_percentage' => $cr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ]);
            }else{
                $data = [
                    'user_id' => $getUserData->id,
                    'company_name' => $company_name,
                    'party_code' => $getUserData->party_code,
                    'warehouse_id' => '3',
                    'warehouse_name' => 'Chennai',
                    'preference' => $chennai,
                    'rewards_percentage' => $cr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                ];
                RewardUser::create($data);
            }
             // For Pune
             $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '5')->first();
             if ($rewardsData) {                
                 $rewardsData->update([
                    'preference' => $pune,
                    'company_name' => $company_name,
                    'rewards_percentage' => $pr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                 ]);
             }else{
                 $data = [
                    'user_id' => $getUserData->id,
                    'company_name' => $company_name,
                    'party_code' => $getUserData->party_code,
                    'warehouse_id' => '5',
                    'warehouse_name' => 'Pune',
                    'preference' => $pune,
                    'rewards_percentage' => $pr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                 ];
                 RewardUser::create($data);
             }
             // For Mumbai
             $rewardsData = RewardUser::where('party_code', $party_code)->where('warehouse_id', '6')->first();
             if ($rewardsData) {                
                 $rewardsData->update([
                    'preference' => $mumbai,
                    'company_name' => $company_name,
                    'rewards_percentage' => $mr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                 ]);
             }else{
                 $data = [
                    'user_id' => $getUserData->id,
                    'company_name' => $company_name,
                    'party_code' => $getUserData->party_code,
                    'warehouse_id' => '6',
                    'warehouse_name' => 'Mumbai',
                    'preference' => $mumbai,
                    'rewards_percentage' => $mr,
                    'assigned_warehouse' => $assigned_warehouse,
                    'city' => $city,
                 ];
                 RewardUser::create($data);
             }
        }
        return 1;
    }

    public function syncNewUser(Request $request){
        
        // $getUserId = User::where('user_type','customer')->pluck('id')->toArray();;
        // $getRewardsUserId = RewardUser::groupBy('user_id')->pluck('user_id')->toArray();;
        // // Find user IDs that are in $getUserId but not in $getRewardsUserId
        // $usersNotInRewards = array_diff($getUserId, $getRewardsUserId);

        $userNotInRewards = User::with(['address_with_party_code:id,user_id,acc_code,city', 'warehouse:id,name'])
            ->whereNotIn('id', function ($query) {
                $query->select('user_id')->from('reward_users');
            })
            ->where('party_code', '!=', '')
            ->where('user_type', 'customer')
            ->orderBy('id', 'ASC')
            ->get();
        $newRecord = $userNotInRewards->count();
        // Output the result
        // print_r($userNotInRewards);die;
        // print_r($getRewardsUserId); die;
        foreach ($userNotInRewards as $key=>$value) {
            if(isset($value->address_with_party_code[0])){
                // For Kolkata
                $data = [
                    'user_id' => $value->id,
                    'company_name' => $value->company_name,
                    'party_code' => $value->party_code,                    
                    'warehouse_id' => '1',
                    'warehouse_name' => 'Kolkata',
                    'preference' => 0,
                    'rewards_percentage' => 0,
                    'assigned_warehouse' => $value->warehouse->name,
                    'city' => $value->address_with_party_code[0]->city,
                ];
                RewardUser::create($data);
                
                // For Delhi
                $data = [
                    'user_id' => $value->id,
                    'company_name' => $value->company_name,
                    'party_code' => $value->party_code,
                    'warehouse_id' => '2',
                    'warehouse_name' => 'Delhi',
                    'preference' => 0,
                    'rewards_percentage' => 0,
                    'assigned_warehouse' => $value->warehouse->name,
                    'city' => $value->address_with_party_code[0]->city,
                ];
                RewardUser::create($data);            

                // For Chennai
                $data = [
                    'user_id' => $value->id,
                    'company_name' => $value->company_name,
                    'party_code' => $value->party_code,
                    'warehouse_id' => '3',
                    'warehouse_name' => 'Chennai',
                    'preference' => 0,
                    'rewards_percentage' => 0,
                    'assigned_warehouse' => $value->warehouse->name,
                    'city' => $value->address_with_party_code[0]->city,
                ];
                RewardUser::create($data);

                // For Pune
                $data = [
                    'user_id' => $value->id,
                    'company_name' => $value->company_name,
                    'party_code' => $value->party_code,
                    'warehouse_id' => '5',
                    'warehouse_name' => 'Pune',
                    'preference' => 0,
                    'rewards_percentage' => 0,
                    'assigned_warehouse' => $value->warehouse->name,
                    'city' => $value->address_with_party_code[0]->city,
                ];
                RewardUser::create($data);

                // For Mumbai
                $data = [
                    'user_id' => $value->id,
                    'company_name' => $value->company_name,
                    'party_code' => $value->party_code,
                    'warehouse_id' => '6',
                    'warehouse_name' => 'Mumbai',
                    'preference' => 0,
                    'rewards_percentage' => 0,
                    'assigned_warehouse' => $value->warehouse->name,
                    'city' => $value->address_with_party_code[0]->city,
                ];
                RewardUser::create($data);
            }else{
                $newRecord = $newRecord - 1;
            }
            
        }

        return $newRecord;
    }

    public function exportDataFromDatabase(Request $request){

        $userList = RewardUser::with(['warehouse:id,name', 'user_data:id,party_code,company_name,name,phone,warehouse_id', 'user_data.user_warehouse:id,name', 'address_with_party_code:id,user_id,acc_code,city'])
            ->get()
            ->filter(function ($item) {
                return $item->user_data !== null; // Exclude records without user_data
            })
            ->groupBy('user_data.party_code')
            ->map(function ($group) {
                $firstUser = $group->first();
                return [
                    'party_code' => $firstUser->user_data['party_code'],
                    'company_name' => $firstUser->user_data['company_name'],
                    'city' => isset($firstUser->address_with_party_code->city) ? ucfirst($firstUser->address_with_party_code->city): 'N/A', // Correct access to city
                    'user_warehouse' => $firstUser->user_data->user_warehouse->name ?? 'N/A', // Access warehouse name correctly
                    'warehouses' => $group->map(function ($item) {
                        return [
                            'reward_id' => $item->id,
                            'warehouse_id' => $item->warehouse['id'] ?? null,
                            'warehouse_name' => $item->warehouse['name'] ?? '',
                            'preference' => $item->preference,
                            'rewards_percentage' => $item->rewards_percentage,
                        ];
                    })->values()->toArray(),
                ];
            })->values();
            $values = $userList->map(function ($user) {
                return [
                    $user['company_name'] ?? '',
                    $user['party_code'] ?? '',
                    $user['city'] ?? '',
                    $user['user_warehouse'] ?? '',
                    $user['warehouses'][0]['preference'] ?? 0,
                    $user['warehouses'][0]['rewards_percentage'] ?? 0,
                    $user['warehouses'][1]['preference'] ?? 0,
                    $user['warehouses'][1]['rewards_percentage'] ?? 0,
                    $user['warehouses'][2]['preference'] ?? 0,
                    $user['warehouses'][2]['rewards_percentage'] ?? 0,
                    $user['warehouses'][3]['preference'] ?? 0,
                    $user['warehouses'][3]['rewards_percentage'] ?? 0,
                    $user['warehouses'][4]['preference'] ?? 0,
                    $user['warehouses'][4]['rewards_percentage'] ?? 0,
                ];
            })->toArray();
        // echo "<pre>"; print_r($values);exit;
        // Clear previous data
        $range = config('sheets.get_rewards');
        $this->sheetsService->clearData($range);

        // // Append data to Google Sheets
        $this->sheetsService->appendData($range, $values);
        return 1;
    }


    public function usersRewardsPoints(Request $request)
{
    $city               = (string) ($request->city ?? '');
    $assigned_warehouse = (string) ($request->assigned_warehouse ?? '');
    $manager            = (string) ($request->manager ?? '');
    $search_text        = (string) ($request->search_text ?? '');
     $wa_status          = (string) ($request->wa_status ?? '');   // ✅ NEW: sent/delivered/read

    $userList = RewardPointsOfUser::with([
            'warehouse:id,name',
            'user_data:id,party_code,company_name,name,phone,manager_id,city',
            'user_data.getManager:id,name',
            'get_user_addresses:id,user_id,acc_code,city',
            // ✅ qualify columns from cloud_response to avoid ambiguity
            'latestCloudResponse' => function ($q) {
                $q->select(
                    'cloud_responses.id',
                    'cloud_responses.msg_id',
                    'cloud_responses.status',
                    'cloud_responses.created_at'
                );
            },
        ])
        // ✅ Only the intended reward sources (match your dataset)
        ->whereIn('rewards_from', ['Early Payment', 'Offer', 'Credit Note'])

        // City filter (user’s city OR address city)
        ->when($city !== '', function ($query) use ($city) {
            $query->where(function ($q) use ($city) {
                $q->whereHas('user_data', fn ($uq) => $uq->where('city', $city))
                  ->orWhereHas('get_user_addresses', fn ($aq) => $aq->where('city', $city));
            });
        })

        // Assigned warehouse filter (adjust if it actually lives elsewhere)
        ->when($assigned_warehouse !== '', fn ($q) => $q->where('assigned_warehouse', $assigned_warehouse))

        // Manager filter via related user
        ->when($manager !== '', function ($q) use ($manager) {
            $q->whereHas('user_data', fn ($uq) => $uq->where('manager_id', $manager));
        })

        // Search party_code or company_name
        ->when($search_text !== '', function ($q) use ($search_text) {
            $q->whereHas('user_data', function ($uq) use ($search_text) {
                $uq->where('party_code', 'LIKE', "%{$search_text}%")
                   ->orWhere('company_name', 'LIKE', "%{$search_text}%");
            });
        })

         ->when(in_array($wa_status, ['sent','delivered','read']), function ($q) use ($wa_status) {
            $q->whereHas('latestCloudResponse', function ($qq) use ($wa_status) {
                $qq->where('cloud_responses.status', $wa_status);
            });
        })

        /**
         * IMPORTANT:
         * We aggregate by party_code, so we must expose:
         *  - a representative id (MIN(id)) so Eloquent can hydrate models
         *  - a representative msg_id (MAX(msg_id)) so the relation key exists
         */
        ->selectRaw('
            MIN(id) as id,
            party_code,
            MAX(msg_id) as msg_id,
            MIN(warehouse_id) as warehouse_id,
            SUM(CASE WHEN dr_or_cr = "dr" THEN rewards ELSE 0 END) AS total_dr_rewards,
            SUM(CASE WHEN dr_or_cr = "cr" THEN rewards ELSE 0 END) AS total_cr_rewards
        ')
        ->groupBy('party_code')
        ->orderBy('party_code', 'ASC')
        ->paginate(15);

    // Dropdown lists (unchanged)
    $cityList = RewardUser::select('city')
        ->groupBy('city')
        ->orderBy('city', 'ASC')
        ->get();

    $assignedWarehouseList = RewardUser::select('assigned_warehouse')
        ->groupBy('assigned_warehouse')
        ->orderBy('assigned_warehouse', 'ASC')
        ->get();

    // Manager list (id + name) deduped by party_code
    $managerList = RewardUser::with([
            'user_data:id,party_code,company_name,manager_id',
            'user_data.manager:id,name'
        ])
        ->get()
        ->filter(fn ($item) => isset($item->user_data->manager))
        ->groupBy('user_data.party_code')
        ->map(function ($group) {
            $first = $group->first();
            return [
                'manager_id'   => $first->user_data->manager->id,
                'manager_name' => $first->user_data->manager->name,
            ];
        })
        ->values();

    return view(
        'backend.reward.users_rewards_points',
        compact(
            'userList',
            'cityList',
            'assignedWarehouseList',
            'managerList',
            'city',
            'assigned_warehouse',
            'manager',
            'search_text',
            'wa_status'     // ✅ send to view
        )
    );
}

    public function exportRewards(Request $request){
        $processedData = [];
        $totalRewareds = 0;
        $userList = RewardPointsOfUser::with([
            'warehouse:id,name',
            'user_data:id,party_code,company_name,name,phone,manager_id,city',
            'user_data.getManager:id,name',
            'get_user_addresses:id,user_id,acc_code,city'
        ])
        ->where('dr_or_cr','dr')
        ->whereNull('credit_rewards')
        ->selectRaw('
            id,party_code,rewards_from,rewards,invoice_no
        ')
        ->get()->toarray();
        foreach($userList as $key=>$values){
            $processedData[] = [
                'Party Code' => $values['party_code'],
                'Company Name' => $values['user_data']['company_name'] ?? '',
                'Invoice No' => $values['invoice_no'],
                'Rewards From' => $values['rewards_from'],
                'Rewards' => $values['rewards']
            ];
            $totalRewareds +=$values['rewards'];
        }
        
        if (count($processedData) > 0) {
            $processedData[] = [
                'Party Code' => 'TOTAL',
                'Company Name' => '',
                'Invoice No' => '',
                'Rewards From' => '',
                'Rewards' => $totalRewareds
            ];
        }
        // Export the processed data to Excel
        return Excel::download(new \App\Exports\RewardsExport($processedData), 'rewardsPoint.xlsx');
    }

    public function importCreditNoteRewards(Request $request){
        // Validate the incoming request
        $request->validate([
            'excel_file' => 'required|file|mimes:xls,xlsx'
        ], ['excel_file' => "File is required"]);

        // Get the real path of the uploaded file
        $filePath = $request->file('excel_file');

        $tableName = 'reward_points_of_users';

        try {
            // Attempt to import the file
            Excel::import(new ImportRewardsCreditNotes($tableName), $filePath);

            // If no exception occurs, consider it a success
            return redirect()->back()->with('success', 'Data imported successfully!');
        } catch (Exception $e) {
            // If an exception occurs, handle it and return an error message
            return redirect()->back()->with('error', 'Data import failed: ' . $e->getMessage());
        }
    }


    public function manualRewardPoint(Request $request)
    {
        $sort_search = $request->input('search', null);
        $filter = $request->input('filter', null);
        $user = Auth::user();

        // Fetch staff users (managers)
        $staffUsers = User::whereHas('roles', function ($query) {
            $query->where('role_id', 5); // Assuming role_id 5 is for managers
        })->select('id', 'name')->get();

        // Fetch warehouses using DB::table
        $warehouses = Warehouse::whereIn('id', [1, 2, 6])->get();

        $query = User::query()
            ->with(['warehouse:id,name', 'manager:id,name', 'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount:id,city,user_id,due_amount,overdue_amount'])
            ->whereIn('user_type', ['customer', 'warehouse'])
            ->whereNotNull('email_verified_at');

        // Apply user-specific logic
        if (in_array($user->id, ['180', '25606', '169'])) {
            // Head managers see all users
        } elseif ($user->user_type != 'admin') {
            $query->where('manager_id', $user->id);
        }

        // Apply search filter
        if ($sort_search) {
            $query->where(function ($q) use ($sort_search) {
                $q->where('party_code', 'like', "%$sort_search%")
                    ->orWhere('phone', 'like', "%$sort_search%")
                    ->orWhere('name', 'like', "%$sort_search%")
                    ->orWhere('gstin', 'like', "%$sort_search%")
                    ->orWhere('company_name', 'like', "%$sort_search%")
                    ->orWhereHas('warehouse', function ($subQuery) use ($sort_search) {
                        $subQuery->where('name', 'like', "%$sort_search%");
                    })
                    ->orWhereHas('manager', function ($subQuery) use ($sort_search) {
                        $subQuery->where('name', 'like', "%$sort_search%");
                    })
                    ->orWhereHas('address_by_party_code', function ($subQuery) use ($sort_search) {
                        $subQuery->where('city', 'like', "%$sort_search%");
                    });
            });
        }

        // Apply manager filter
        if ($request->filled('manager')) {
            $query->whereIn('manager_id', $request->input('manager'));
        }

        // Apply warehouse filter
        if ($request->filled('warehouse')) {
            $query->whereIn('warehouse_id', $request->input('warehouse'));
        }

        // Apply additional filters
        if ($filter) {
            $query->when($filter === 'approved', fn($q) => $q->where('banned', '0'))
                ->when($filter === 'un_approved', fn($q) => $q->where('banned', '1'));
        }

        // Sorting logic
        $sort_by = $request->input('sort_by', 'company_name');
        $sort_order = $request->input('sort_order', 'asc');
        $query->when($sort_by === 'manager_name', fn($q) => $q->orderBy('manager.name', $sort_order))
            ->when($sort_by === 'warehouse_name', fn($q) => $q->orderBy('warehouse.name', $sort_order))
            ->orderBy($sort_by, $sort_order);

        // Paginate results
        $users = $query->paginate(15);

        return view('backend.reward.manual_rewards_point', compact(
            'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'staffUsers', 'warehouses'
        ));
    }

    private function getManagerPhone($managerId)
    {
          // $managerData = DB::table('users')
          //     ->where('id', $managerId)
          //     ->select('phone')
          //     ->first();
        $managerData = User::where('id', $managerId)->select('phone')->first();


          return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }


   public function updateRewardPoint(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'reward_points' => 'required|integer|min:0',
            'note' => 'nullable|string|max:255', // Allow note as an optional field
        ]);

        // Fetch the user and associated data
        $user = User::with('warehouse')->findOrFail($request->user_id);

        // Create a new record in the RewardPointsOfUser model
        RewardPointsOfUser::create([
            'party_code' => $user->party_code,
            'rewards_from' => 'Manual', // Default value
            'warehouse_id' => $user->warehouse->id ?? null,
            'warehouse_name' => $user->warehouse->name ?? null,
            'rewards' => $request->reward_points,
            'dr_or_cr' => 'dr', // Default value
            'notes' => $request->note, // Save the note
        ]);

         $addressData = Address::where('acc_code', $user->party_code)->first();
         $imageUrl = "https://mazingbusiness.com/public/reward_pdf/reward_image.jpg";
         // Generate reward URL
         $rewardURL = $this->getRewardPdfURL($user->party_code);
         $rewardBaseFileName = basename($rewardURL);
         $manager_phone=$this->getManagerPhone($user->manager_id);

        $templateData = [
                    'name' => 'utility_manual_reward_whatsapp', // Template name for manual reward
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
                                ['type' => 'text', 'text' => $addressData->company_name],
                                ['type' => 'text', 'text' =>  $request->reward_points],
                            ],
                        ],
                        [
                            'type' => 'button', 'sub_type' => 'url', 'index' => '0',
                            'parameters' => [
                                ['type' => 'text', 'text' => $rewardBaseFileName],
                            ],
                        ],
                    ],
                ];

                $whatsappNumbers=[$addressData->phone,$manager_phone];
                $this->whatsAppWebService = new WhatsAppWebService();
                foreach ($whatsappNumbers as $number) {
                    if (!empty($number)) {
                        $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($number, $templateData);
                       

                        // Check response and update status
                        if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] === 'accepted') {
                            Log::info("Ok");
                        } else {
                            $error = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';
                            Log::error("Not Ok");
                        }
                    }
                }
            //return response()->json(['success' => 1]);
               return redirect()->back()->with('success', 'Reward points added successfully.');
    }


    public function getRewardPdfURL($party_code)
    {
        // 1) Fetch rewards (ordered) & skip cancelled
        // $getData = RewardPointsOfUser::where('party_code', $party_code)
        //     ->whereNull('cancel_reason')
        //     ->orderBy('id') // or ->orderBy('voucher_date') if available
        //     ->get();
        $getData = RewardPointsOfUser::where('party_code', $party_code)
        ->whereNull('cancel_reason')
        ->where('rewards_from', '!=', 'Logistic')
        ->orderBy('id')
        ->get();

        // 2) Add narration (case-insensitive)
        foreach ($getData as $reward) {
            $from = strtolower((string)($reward->rewards_from ?? ''));
            if ($from === 'logistic' && !empty($reward->invoice_no)) {
                $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
                $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
            } elseif ($from === 'manual') {
                $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
            } else {
                $reward->narration = '-';
            }
        }

        // 3) Totals: NET = DR - CR (exclude any synthetic rows if present)
        $rewardRows = $getData->filter(function ($r) {
            $name = strtoupper((string)($r->rewards_from ?? ''));
            return !in_array($name, ['TOTAL', 'CLOSING BALANCE']);
        })->values();

        $debitTotal = (float) $rewardRows
            ->filter(fn($r) => strtolower((string)$r->dr_or_cr) === 'dr')
            ->sum('rewards');

        $creditTotal = (float) $rewardRows
            ->filter(fn($r) => strtolower((string)$r->dr_or_cr) === 'cr')
            ->sum('rewards');

        $net = round($debitTotal - $creditTotal, 2); // +ve => DR, -ve => CR

        $closing_balance = abs($net);
        $last_dr_or_cr   = ($net > 0) ? 'dr' : 'cr';
        $rewardAmount    = $closing_balance; // Header "Reward Balance"

        // 4) User (for PDF header)
        $userData = Auth::user();

        // 5) Ensure directory and file paths
        $dirPath  = public_path('reward_pdf');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dirPath);

        $fileName  = 'reward_statement_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$party_code) . '_' . time() . '.pdf';
        $filePath  = $dirPath . DIRECTORY_SEPARATOR . $fileName;
        $publicUrl = url('public/reward_pdf/' . $fileName);

        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('reward_statement');

        // 6) Render & save PDF
        PDF::loadView('backend.invoices.rewards_pdf', compact(
            'userData',
            'party_code',
            'getData',
            'rewardAmount',
            'closing_balance',
            'last_dr_or_cr',
            'pdfContentBlock' // ✅ Blade me use hoga
        ))->save($filePath);

        // 7) Return full URL
        return $publicUrl;
    }

    // public function getRewardPdfURL($party_code)
    // {
    //     // Fetch rewards data
    //     $getData = RewardPointsOfUser::where('party_code', $party_code)
    //         ->whereNull('cancel_reason')
    //         ->get();

    //     // Process rewards data to add narration
    //     foreach ($getData as $reward) {
    //         if ($reward->rewards_from === 'Logistic' && !empty($reward->invoice_no)) {
    //             $billData = DB::table('bills_data')->where('invoice_no', $reward->invoice_no)->first();
    //             $reward->narration = $billData ? $billData->invoice_amount : 'N/A';
    //         } elseif ($reward->rewards_from === 'Manual') {
    //             $reward->narration = !empty($reward->notes) ? $reward->notes : '-';
    //         } else {
    //             $reward->narration = '-';
    //         }
    //     }

    //     // Exclude 'Total' and 'Closing Balance' rows from calculations
    //     $rewardRows = $getData->filter(function ($reward) {
    //         return !in_array($reward->rewards_from, ['Total', 'Closing Balance']);
    //     });

    //     // Calculate reward amount
    //     $rewardAmount = $rewardRows->sum('rewards');

    //     // Get the last valid row before totals for closing balance
    //     $lastRow = $rewardRows->last();
    //     $closing_balance = $lastRow ? $lastRow->rewards : 0;
    //     $last_dr_or_cr = $lastRow ? strtolower($lastRow->dr_or_cr) : null;

    //     // Adjust the closing balance based on Dr/Cr logic
    //     if ($last_dr_or_cr === 'dr') {
    //         $closing_balance = $rewardAmount;
    //     } else {
    //         $closing_balance = -$rewardAmount;
    //     }

    //     // User data
    //     $userData = Auth::user();

    //     // File name and path
    //     $fileName = 'reward_statement_' . $party_code . '_' . time() . '.pdf';
    //     $filePath = public_path('reward_pdf/' . $fileName);
    //     $publicUrl = url('public/reward_pdf/' . $fileName);

    //     // Generate and save the PDF
    //     PDF::loadView('backend.invoices.rewards_pdf', compact(
    //         'userData',
    //         'party_code',
    //         'getData',
    //         'rewardAmount',
    //         'closing_balance',
    //         'last_dr_or_cr'
    //     ))->save($filePath);

    //     // Return the public URL
    //     return $publicUrl;
    // }




private function generatePaymentUrl($party_code, $payment_for)
{
    $client = new \GuzzleHttp\Client();
    $response = $client->post('https://mazingbusiness.com/api/v2/payment/generate-url', [
        'json' => [
            'party_code' => $party_code,
            'payment_for' => $payment_for
        ]
    ]);
    $data = json_decode($response->getBody(), true);
    return $data['url'] ?? '';  // Return the generated URL or an empty string if it fails
}
	
private function rewardICICIPaymentQrCodeGenerater($amount="",$billNumber="",$party_code="",$payment_for=""){
      // $getPaymentHistoryData = PaymentHistory::where('bill_number',$billNumber)->where('api_name','QR_CODE')->first();
      
      // if(empty($getPaymentHistoryData) AND $amount >= 1){
      if($amount >= 1){   
        // Set MID, VPA, and other variables
        $mid = env('MERCHANT_ID'); //'610853';
        $vpa = env('VPA'); //'aceuat@icici';
        $merchantName = env('MARCHANT_NAME'); //'Ace Tools Pvt. Ltd'; // Merchant name can be dynamic
        $api_url = env('API_URL_QR'). $mid; // 'https://apibankingonesandbox.icicibank.com/api/MerchantAPI/UPI/v0/QR3/' . $mid;
        $merchantTranId = uniqid();
        // $amount ='1.00';
        // Payload to be encrypted
        $payload = json_encode([
            'merchantId' => $mid,
            'terminalId' => env('TERMINAL_ID'), // '5411',
            'amount' => number_format($amount, 2, '.', ''),
            'merchantTranId' => $merchantTranId,
            'billNumber' => $billNumber,
            'validatePayerAccFlag' => 'N'
        ]);

        // Encrypt the payload
        $encrypted_payload = $this->encrypt_payload($payload, storage_path('pay/public/key/rsa_apikey.txt'));
        
        // Send API request
        $response = $this->send_api_request($api_url, $encrypted_payload);

        // Decrypt the response
        $decrypted_response = $this->decrypt_response($response, storage_path('pay/private/key/private_cer.pem'));
        
        // Handle response and generate UPI URL and QR code
        $response_data = json_decode($decrypted_response, true);
        // echo "<pre>"; print_r($response_data);die;
        $qrCodeUrl = "";
        if ($response_data['success'] == 'true') {
            $refId = $response_data['refId'];
            $currency = 'INR';
            $mccCode = $response_data['terminalId'];

            // Generate UPI URL
            $upiUrl = "upi://pay?pa=$vpa&pn=$merchantName&tr=$refId&am=$amount&cu=$currency&mc=$mccCode";
            $encodedUpiUrl = urlencode($upiUrl);

            // Generate QR code URL
            $qrCodeUrl = "https://quickchart.io/qr?text=" . $encodedUpiUrl . "&size=250";
			$userId = Address::where('acc_code', $party_code)->pluck('user_id')->first();
            
            $paymentHistoryData = array();
            $paymentHistoryData['qrCodeUrl']=$qrCodeUrl;
            $paymentHistoryData['user_id']=$userId;
            $paymentHistoryData['party_code']=$party_code;
            $paymentHistoryData['bill_number']=$billNumber;
            $paymentHistoryData['merchantId']=$mid;
            $paymentHistoryData['subMerchantId']=$mid;
            $paymentHistoryData['terminalId']=$response_data['terminalId'];
            $paymentHistoryData['merchantTranId']=$merchantTranId;
            $paymentHistoryData['refId']=$refId;
            $paymentHistoryData['merchantName']=$merchantName;
            $paymentHistoryData['vpa']=$vpa;
            $paymentHistoryData['amount']=$amount;
            $paymentHistoryData['api_name']='QR_CODE';
			 $paymentHistoryData['payment_for']=$payment_for;
            PaymentHistory::create($paymentHistoryData);


        }
      // }else if($getPaymentHistoryData->status == 'PENDING' AND $amount >= 1){
      //   $qrCodeUrl = $getPaymentHistoryData->qrCodeUrl;
      //   $merchantTranId = $getPaymentHistoryData->merchantTranId;
      }else{
        $qrCodeUrl ='';
        $merchantTranId = '';
      }
      $data = [
        'qrCodeUrl'=> $qrCodeUrl,
        'merchantTranId'=> $merchantTranId
      ];
      return response()->json($data);
  }
	
	private function decrypt_response($encrypted_response, $private_key_path){
      // Load the private key from the file
      $private_key = file_get_contents($private_key_path);

      // Decode the base64-encoded encrypted response
      $decoded_response = base64_decode($encrypted_response);

      // Variable to hold the decrypted response
      $decrypted = '';

      // Decrypt the response using the private key and PKCS1 padding
      $decryption_successful = openssl_private_decrypt($decoded_response, $decrypted, $private_key, OPENSSL_PKCS1_PADDING);

      // Check if decryption was successful
      if ($decryption_successful) {
          return $decrypted;  // Return the decrypted response
      } else {
          return 'Decryption failed';  // Handle decryption failure
      }
  }

  private function encrypt_payload($payload, $public_key_path){
      // Load the public key from the file
      $public_key = file_get_contents($public_key_path);

      // Variable to hold the encrypted result
      $encrypted = '';

      // Encrypt the payload using the public key and PKCS1 padding
      $encryption_successful = openssl_public_encrypt($payload, $encrypted, $public_key, OPENSSL_PKCS1_PADDING);

      // Check if encryption was successful
      if ($encryption_successful) {
          // Base64 encode the encrypted payload
          return base64_encode($encrypted);
      } else {
          return 'Encryption failed';  // Handle encryption failure
      }
  }

  private function send_api_request($url, $encrypted_payload) {
      // Initialize cURL
      $ch = curl_init();

      // Set the cURL options
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $encrypted_payload);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      // Set headers
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'accept: */*',
          'accept-encoding: *',
          'accept-language: en-US,en;q=0.8,hi;q=0.6',
          'cache-control: no-cache',
          'connection: keep-alive',
          'content-length: ' . strlen($encrypted_payload),
          'content-type: text/plain;charset=UTF-8',
      ]);

      // Execute the cURL request and fetch response
      $response = curl_exec($ch);

      // Check for errors
      if ($response === false) {
          $response = curl_error($ch);
      }

      // Close cURL
      curl_close($ch);

      // Return the response
      return $response;
  }


//  Functions start for cron job for early payment


     public function insertEarlyPaymentRemainders()
    {
        // Fetch all party codes from the addresses table
        $addresses = Address::select('acc_code', 'statement_data', 'phone')
         // ->where('acc_code','OPEL0100037')
        ->get();

        if ($addresses->isEmpty()) {
            Log::warning("No party codes found in the addresses table.");
            return response()->json(['message' => 'No party codes found.'], 404);
        }

        // Define the start date filter (1st January 2025)
        $startDate = Carbon::parse('2025-01-01');

        foreach ($addresses as $address) {
            $partyCode = $address->acc_code;

            // Decode the JSON data from the statement_data column
            $statementData = json_decode($address->statement_data, true);

            if (!$statementData || !is_array($statementData)) {
                Log::warning("Invalid statement data for party_code: $partyCode");
                continue; // Skip this party_code if statement data is invalid
            }

            // Fetch invoices that already have "early_payment" rewards
            $invoicesWithEarlyPayment = RewardPointsOfUser::where('rewards_from', 'Early Payment')
                ->pluck('invoice_no')
                ->toArray();

            // Separate sales and receipts
            $sales = array_filter($statementData, function ($data) use ($startDate, $invoicesWithEarlyPayment) {
                return (
                    Carbon::parse($data['trn_date'])->greaterThanOrEqualTo($startDate) &&
                    $data['vouchertypebasename'] === 'Sales' &&
                    !empty($data['trn_no']) &&
                    !empty($data['dramount']) &&
                    !in_array($data['trn_no'], $invoicesWithEarlyPayment)
                );
            });

            $receipts = array_filter($statementData, function ($data) {
                return $data['vouchertypebasename'] === 'Receipt' || $data['vouchertypebasename'] === 'Journal';
            });

            // Sort transactions by date (FIFO Order)
            usort($sales, function ($a, $b) {
                return strtotime($a['trn_date']) - strtotime($b['trn_date']);
            });

            usort($receipts, function ($a, $b) {
                return strtotime($a['trn_date']) - strtotime($b['trn_date']);
            });

            $receiptBalance = 0;
            
            foreach ($sales as $key => &$sale) {
                $saleDate = Carbon::parse($sale['trn_date']);
                $saleAmount = $sale['dramount'];
                $invoiceNo = $sale['trn_no'];
                $remainingAmount = $saleAmount;  // Start with full invoice amount as the remaining amount

                // Check if no payment is applied yet (i.e., receiptBalance is 0)
                if ($receiptBalance == 0) {
                    // Ensure that if no payments are applied, the remaining amount is set to full invoice amount
                    $remainingAmount = $saleAmount;
                }

                // Check for partial or full payment
                if ($receiptBalance >= $saleAmount) {
                    // Fully paid case (SKIP insertion)
                    $receiptBalance -= $saleAmount;
                    unset($sales[$key]); // Remove from list
                } else {
                    // Partial payment case
                    if ($receiptBalance > 0) {
                        $remainingAmount -= $receiptBalance;
                        $paymentApplied = $receiptBalance;
                        $status = 'Partially Paid';
                        $receiptBalance = 0;
                    } else {
                        $paymentApplied = 0;
                        $status = 'Unpaid';
                    }

                    // Insert only "Partially Paid" and "Unpaid" invoices
                    RewardRemainderEarlyPayment::updateOrCreate(
                        [
                            'invoice_no' => $invoiceNo,
                        ],
                        [
                            'party_code'       => $partyCode,
                            'invoice_no'       => $invoiceNo,
                            'invoice_date'     => $sale['trn_date'],
                            'invoice_amount'   => $saleAmount,
                            'payment_applied'  => $paymentApplied,
                            'remaining_amount' => $remainingAmount,  // Updated line to set remaining_amount correctly
                            'payment_status'   => $status,
                            'reminder_sent'    => 0,
                            'is_processed'     => 0,
                        ]
                    );

                    Log::info("Inserted/Updated invoice {$invoiceNo} for party_code: $partyCode with status {$status}");
                }
            }
        }

        return response()->json(['message' => 'Early payment reminders data inserted successfully.'], 200);
    }

     public function getTrimmedPartyCode($party_code)
    {
        return substr($party_code, 0, 11);
    }
    public function sendEarlyPaymentWhatsApp()
    {

        $groupId = 'group_' . uniqid(); // Generate a unique group ID
        $currentDate = Carbon::now()->format('Y-m-d');

        // Fetch all records where reminders need to be sent
        $reminders = RewardRemainderEarlyPayment::get();

        if ($reminders->isEmpty()) {
            Log::warning("No pending reminders found.");
            return response()->json(['message' => 'No pending reminders found.'], 404);
        }

        foreach ($reminders as $reminder) {
            $transactionDate = Carbon::parse($reminder->invoice_date);

            // Calculate reminder dates
            $firstReminderDate = $transactionDate->copy()->addDays(14)->format('Y-m-d');
            $secondReminderDate = $transactionDate->copy()->addDays(39)->format('Y-m-d');
            $firstFollowUpDate = $transactionDate->copy()->addDays(18)->format('Y-m-d'); // Follow-up for the first reminder
            $secondFollowUpDate = $transactionDate->copy()->addDays(43)->format('Y-m-d'); // Follow-up for the second reminder

             // echo $secondReminderDate;
             // die();
             // $currentDate="2025-02-06";

            // Handle first reminder
            if ($currentDate == $firstReminderDate && $reminder->reminder_sent == 0) {
                $this->rewardICICIPaymentQrCodeGenerater($reminder->remaining_amount, $reminder->invoice_no, $this->getTrimmedPartyCode($reminder->party_code), "payable_amount");
                $payment_url = $this->generatePaymentUrl($reminder->invoice_no, $payment_for = "payable_amount");
                $discountLastDate = $transactionDate->copy()->addDays(20)->format('Y-m-d'); // Discount expires on 20th day

                $this->sendWhatsAppReminder(
                    $this->getTrimmedPartyCode($reminder->party_code),
                    $reminder->invoice_no,
                    $reminder->invoice_date,
                    $reminder->invoice_amount,
                    $reminder->remaining_amount,
                    1, // First reminder
                    $discountLastDate,
                    $groupId,
                    $payment_url
                );

                $reminder->update(['reminder_sent' => 1, 'is_processed' => 1]);
            }

            // Handle follow-up for the first reminder
            if ($currentDate == $firstFollowUpDate && $reminder->reminder_sent == 1) {
                $this->rewardICICIPaymentQrCodeGenerater($reminder->remaining_amount, $reminder->invoice_no, $this->getTrimmedPartyCode($reminder->party_code), "payable_amount");
                $payment_url = $this->generatePaymentUrl($reminder->invoice_no, $payment_for = "payable_amount");
                $discountLastDate = $transactionDate->copy()->addDays(20)->format('Y-m-d'); // Discount expires on 20th day

                $this->sendWhatsAppReminder(
                    $this->getTrimmedPartyCode($reminder->party_code),
                    $reminder->invoice_no,
                    $reminder->invoice_date,
                    $reminder->invoice_amount,
                    $reminder->remaining_amount,
                    1, // Follow-up for first reminder
                    $discountLastDate,
                    $groupId,
                    $payment_url
                );

                Log::info("Follow-up WhatsApp reminder sent for Invoice No: {$reminder->invoice_no}");
            }

            // Handle second reminder
            if ($currentDate == $secondReminderDate && $reminder->reminder_sent == 1) {
                $this->rewardICICIPaymentQrCodeGenerater($reminder->remaining_amount, $reminder->invoice_no, $this->getTrimmedPartyCode($reminder->party_code), "payable_amount");
                $payment_url = $this->generatePaymentUrl($reminder->invoice_no, $payment_for = "payable_amount");
                $discountLastDate = $transactionDate->copy()->addDays(45)->format('Y-m-d'); // Discount expires on 45th day

                $this->sendWhatsAppReminder(
                    $this->getTrimmedPartyCode($reminder->party_code),
                    $reminder->invoice_no,
                    $reminder->invoice_date,
                    $reminder->invoice_amount,
                    $reminder->remaining_amount,
                    2, // Second reminder
                    $discountLastDate,
                    $groupId,
                    $payment_url
                );

                $reminder->update(['reminder_sent' => 2, 'is_processed' => 1]);
            }

            // Handle follow-up for the second reminder
            if ($currentDate == $secondFollowUpDate && $reminder->reminder_sent == 2) {
                $this->rewardICICIPaymentQrCodeGenerater($reminder->remaining_amount, $reminder->invoice_no, $this->getTrimmedPartyCode($reminder->party_code), "payable_amount");
                $payment_url = $this->generatePaymentUrl($reminder->invoice_no, $payment_for = "payable_amount");
                $discountLastDate = $transactionDate->copy()->addDays(45)->format('Y-m-d'); // Discount expires on 45th day

                $this->sendWhatsAppReminder(
                    $this->getTrimmedPartyCode($reminder->party_code),
                    $reminder->invoice_no,
                    $reminder->invoice_date,
                    $reminder->invoice_amount,
                    $reminder->remaining_amount,
                    2, // Follow-up for second reminder
                    $discountLastDate,
                    $groupId,
                    $payment_url
                );

                Log::info("Follow-up WhatsApp reminder sent for Invoice No: {$reminder->invoice_no}");
            }
        }

        SendWhatsAppMessagesJob::dispatch($groupId);

        return response()->json(['message' => 'WhatsApp reminders sent successfully.'], 200);
    }

    


    private function sendWhatsAppReminder($party_code, $invoiceNo, $invoice_date, $amount, $remaining_amount, $reminder_type, $lastDate, $groupId, $payment_url)
    {
        $imageUrl = "https://mazingbusiness.com/public/reward_pdf/reward_image.jpg";

        Log::info('Payment URL: ' . trim($payment_url));

        $fileName = substr($payment_url, strpos($payment_url, "pay-amount/") + strlen("pay-amount/"));
        $button_variable_encode_part = $fileName;

        // Fetch customer details from DB (Assuming User or Address table contains the details)
        $customerData = Address::where('acc_code', $party_code)->first();
        $userData = User::where('party_code', $party_code)->first();

        if (!$customerData) {
            Log::error("Customer data not found for Invoice No: {$invoiceNo}");
            return;
        }

        $customerName = $customerData->company_name ?? 'Customer'; // Use 'Customer' if name is not found
        $invoiceDate = $invoice_date ?? 'N/A';

        // Determine the discount percentage based on the reminder type
        $discountPercentage = ($reminder_type == 1) ? 2 : 1.5;

        // Calculate reduced amount
        $reducedAmount = ($amount * $discountPercentage / 100);

        // Prepare WhatsApp template data
        $templateData = [
            'name' => 'early_payment_remaind',
            'language' => 'en_US',
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
                        ['type' => 'text', 'text' => $customerName], // Customer Name
                        ['type' => 'text', 'text' => $invoiceNo],   // Invoice No
                        ['type' => 'text', 'text' => $invoiceDate], // Invoice Date
                        ['type' => 'text', 'text' => '' . number_format($amount, 2)],  // Invoice Amount
                        ['type' => 'text', 'text' => '' . number_format($remaining_amount, 2)], // Remaining Payable
                        ['type' => 'text', 'text' => '' . number_format($reducedAmount, 2)], // Reduced Amount after Discount
                        ['type' => 'text', 'text' => $lastDate], // Payment due date
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        ['type' => 'text', 'text' => $button_variable_encode_part], // Payment Link
                    ],
                ],
            ],
        ];

        $whatsappNumber = $customerData->phone; // Use default if phone is missing

        $manager_phone=$this->getManagerPhone($userData->manager_id);

        // Insert WhatsApp message data into the wa_sales_queue table
        DB::table('wa_sales_queue')->insert([
            'group_id'       => $groupId,
            'callback_data'  => $templateData['name'],
            'recipient_type' => 'individual',
            // 'to_number'      => $whatsappNumber,
            'to_number'      => "9804722029",
            'type'           => 'template',
            'file_url'       => $payment_url,
            'content'        => json_encode($templateData),
            'status'         => 'pending',
            'response'       => '',
            'msg_id'         => '',
            'msg_status'     => '',
            'created_at'     => now(),
            'updated_at'     => now()
        ]);

        DB::table('wa_sales_queue')->insert([
            'group_id'       => $groupId,
            'callback_data'  => $templateData['name'],
            'recipient_type' => 'individual',
            'to_number'      => "7044300330",
            'type'           => 'template',
            'file_url'       => $payment_url,
            'content'        => json_encode($templateData),
            'status'         => 'pending',
            'response'       => '',
            'msg_id'         => '',
            'msg_status'     => '',
            'created_at'     => now(),
            'updated_at'     => now()
        ]);

        Log::info("WhatsApp reminder queued successfully for Invoice No: {$invoiceNo}");
    }

    
    //  Functions end for cron job for early payment



    //notify early payment  to manager 
    public function notifyEarlyRewardToManager()
    {
        // Fetch all managers
        $managers = User::join('staff', 'users.id', '=', 'staff.user_id')->where('staff.role_id', 5)->where('users.banned', 0)->select('users.*')->get();
        if ($managers->isEmpty()) {
            Log::warning("No managers found.");
            return response()->json(['message' => 'No managers found.'], 404);
        }
        // Loop through each manager
        foreach ($managers as $manager) {
            // Get all customers under this manager from the 'users' table
            $managerCustomers = User::where('manager_id', $manager->id)->orderBy('name', 'asc') ->get();
            $customerData = [];
            // Loop through each customer under the manager
            foreach ($managerCustomers as $customer) {
                // Fetch customer address from the 'addresses' table
                $customerAddress = Address::where('acc_code', $customer->party_code)->first();
                // Fetch data from the 'reward_remainder_early_payments' table
                $paymentData = RewardRemainderEarlyPayment::where('party_code', $customer->party_code)->first();
               
                // Ensure the customer has invoice data
                if ($paymentData) {
                    $transactionDate = Carbon::parse($paymentData->invoice_date);
                    // Determine the discount percentage based on the reminder type
                    $discountPercentage = ($paymentData->reminder_sent == 1) ? 2 : 1.5;
                    // If the customer address exists, proceed
                    if ($customerAddress) {
                        $reducedAmount = ($paymentData->invoice_amount * $discountPercentage / 100); // Calculate reduced amount
                        // Add the customer's data to the array
                        $customerData[] = [
                            'customer_name' => $customerAddress->company_name ?? 'Customer',
                            'party_code' => $this->getTrimmedPartyCode($customer->party_code), // Use the trimmed party code
                            'invoice_no' => $paymentData->invoice_no,
                            'invoice_amount' => $paymentData->invoice_amount,
                            'pay_by_date' => $transactionDate->copy()->addDays(20)->format('Y-m-d'), // Assuming 20 days for discount
                            'reduced_amount' => number_format($reducedAmount, 2),
                        ];
    
                    }
                }
            }
            $customerData = collect($customerData)->sortBy('customer_name'); // Sorting by customer_name in ascending order
    
            if (!empty($customerData)) {
                // Generate the PDF for the manager's customers
                $pdf = PDF::loadView('backend.invoices.reward_early_payment_notify_manager', compact('customerData', 'manager'));
                $fileName = 'reward-' . time() . '-' . $manager->id . '.pdf';
                $filePath = public_path('pdfs/' . $fileName);
                $pdf->save($filePath);
    
                // Get the URL of the saved PDF
                $fileUrl = url('public/pdfs/' . $fileName);
    
                //whatsapp sending code start 
                $whatsAppWebService = new WhatsAppWebService();
                $media_id=$whatsAppWebService->uploadMedia($fileUrl);
    
                $templateData = [
                    'name' => 'early_payment_manager_notify', // Replace with your template name, e.g., 'abandoned_cart_template'
                    'language' => 'en_US', // Replace with your desired language code
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                               ['type' => 'document', 'document' => ['id' => $media_id['media_id'], 'filename' => "Mananger Early Payment Notifications"]]
    
                               
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $manager->name],
    
                            ],
                        ],
                    ],
                ];
               // echo $manager->phone;
               // die();
                
                $whatsappNumbers=[$manager->phone];
               
                foreach ($whatsappNumbers as $number) {
                    if (!empty($number)) {
                        $jsonResponse = $whatsAppWebService->sendTemplateMessage($number, $templateData);
                        // Check response and update status
                        if (isset($jsonResponse['messages'][0]['message_status']) && $jsonResponse['messages'][0]['message_status'] === 'accepted') {
                            Log::info("whatsapp sent");
                        } else {
                            $error = $jsonResponse['messages'][0]['message_status'] ?? 'unknown';
                            Log::error("Failed To sent");
                        }
                    }
                }
                // whatsapp sending code end
              
                // Log the process
                Log::info("PDF generated for Manager: {$manager->name}, file URL: {$fileUrl}");
            } else {
                Log::warning("No customers found for Manager: {$manager->name}");
            }
    
           
        }
    
        return response()->json(['message' => 'Early Reward notifications sent successfully.'], 200);
    }
}
