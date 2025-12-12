<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CloudResponse;
use App\Models\Cart;
use App\Models\Product;
use App\Models\CartSaveForLater;

class CloudResponseController extends Controller
{
    public function purgePreviousQuarterCloudResponses(Request $request)
    {
        // Options
        $batch        = max(100, (int) $request->query('batch', 1000));
        $forceDelete  = (bool) $request->query('force_delete', false);

        // Work out last completed quarter
        $now          = Carbon::now();                 // Asia/Kolkata timezone assume
        $currentQtr   = (int) ceil($now->month / 3);   // 1..4

        // last completed quarter & year
        $lastQtr      = $currentQtr - 1;
        $year         = (int) $now->year;
        if ($lastQtr <= 0) {
            $lastQtr = 4;
            $year   -= 1;
        }

        // quarter -> start month
        $qtrStartMonth = [1 => 1, 2 => 4, 3 => 7, 4 => 10][$lastQtr];

        // Range boundaries
        $rangeStart = Carbon::create($year, $qtrStartMonth, 1, 0, 0, 0);
        $rangeEnd   = (clone $rangeStart)->addMonths(3)->subSecond(); // inclusive end

        // Count only that quarter
        $baseQuery = CloudResponse::whereBetween('created_at', [$rangeStart, $rangeEnd]);
        $wouldDelete = (int) $baseQuery->count();
        $totalDeleted = 0;

        if ($wouldDelete > 0) {
            // chunk delete by id
            $baseQuery->select('id')
                ->orderBy('id')
                ->chunkById($batch, function ($rows) use (&$totalDeleted, $forceDelete) {
                    $ids = $rows->pluck('id');
                    if ($forceDelete && method_exists(CloudResponse::class, 'bootSoftDeletes')) {
                        CloudResponse::whereKey($ids)->forceDelete();
                    } else {
                        CloudResponse::whereKey($ids)->delete();
                    }
                    $totalDeleted += $ids->count();
                }, 'id');
        }

        return response()->json([
            'ok'          => true,
            'mode'        => 'previous_quarter_only',
            'quarter'     => $lastQtr,
            'year'        => $year,
            'range_start' => $rangeStart->toDateTimeString(),
            'range_end'   => $rangeEnd->toDateTimeString(),
            'deleted'     => $totalDeleted,
            'would_match' => $wouldDelete,
            'batch'       => $batch,
            'table'       => (new CloudResponse)->getTable(),
            'hard_delete' => $forceDelete,
        ]);
    }


    public function archivePreviousQuarterCartsToSaveForLater(Request $request)
    {
        $batch = max(100, (int) $request->query('batch', 1000));

        // --- Determine previous quarter ---
        $now        = Carbon::now();
        $currentQtr = (int) ceil($now->month / 3);   // 1..4
        $lastQtr    = $currentQtr - 1;
        $year       = (int) $now->year;
        if ($lastQtr <= 0) { $lastQtr = 4; $year -= 1; }

        $qtrStartMonth = [1 => 1, 2 => 4, 3 => 7, 4 => 10][$lastQtr];
        $rangeStart = Carbon::create($year, $qtrStartMonth, 1, 0, 0, 0);
        $rangeEnd   = (clone $rangeStart)->addMonths(3)->subSecond();

        // --- Fetch all Carts updated in that quarter ---
        $baseQuery = Cart::whereBetween('updated_at', [$rangeStart, $rangeEnd]);

        $toMoveCount  = (int) $baseQuery->count();
        $movedCount   = 0;

        if ($toMoveCount > 0) {
            $baseQuery->select([
                    'id','address_id','price','tax','shipping_cost','discount',
                    'product_referral_code','coupon_code','coupon_applied','quantity',
                    'user_id','customer_id','temp_user_id','owner_id','product_id',
                    'variation','is_carton','is_offer_product','cash_and_carry_item','is_manager_41',
                    'created_at','updated_at'
                ])
                ->orderBy('id')
                ->chunkById($batch, function ($rows) use (&$movedCount) {
                    $nowTs = now();
                    $payload = [];

                    // Get product mapping for all involved product_ids
                    $productIds = $rows->pluck('product_id')->filter()->unique();
                    $productMap = Product::whereIn('id', $productIds)
                        ->get(['id','group_id','category_id','brand_id'])
                        ->keyBy('id');

                    foreach ($rows as $r) {
                        $p = $productMap[$r->product_id] ?? null;

                        $payload[] = [
                            'address_id'             => $r->address_id,
                            'price'                  => $r->price,
                            'tax'                    => $r->tax,
                            'shipping_cost'          => $r->shipping_cost,
                            'discount'               => $r->discount,
                            'product_referral_code'  => $r->product_referral_code,
                            'coupon_code'            => $r->coupon_code,
                            'coupon_applied'         => $r->coupon_applied,
                            'quantity'               => $r->quantity,
                            'user_id'                => $r->user_id,
                            'customer_id'            => $r->customer_id,
                            'temp_user_id'           => $r->temp_user_id,
                            'owner_id'               => $r->owner_id,
                            'product_id'             => $r->product_id,
                            'variation'              => $r->variation,
                            'is_carton'              => $r->is_carton,
                            'is_offer_product'       => $r->is_offer_product,
                            'cash_and_carry_item'    => $r->cash_and_carry_item,
                            'is_manager_41'          => $r->is_manager_41,

                            // 🔹 Auto-fill from Product table
                            'group_id'               => $p->group_id ?? null,
                            'category_id'            => $p->category_id ?? null,
                            'brand_id'               => $p->brand_id ?? null,

                            // Preserve timestamps
                            'created_at'             => $r->created_at ?? $nowTs,
                            'updated_at'             => $r->updated_at ?? $nowTs,
                        ];
                    }

                    DB::transaction(function () use ($rows, $payload, &$movedCount) {
                        CartSaveForLater::insert($payload);
                        Cart::whereKey($rows->pluck('id'))->delete();
                        $movedCount += count($payload);
                    });
                }, 'id');
        }

        return response()->json([
            'ok'           => true,
            'mode'         => 'archive_previous_quarter_to_save_for_later',
            'quarter'      => $lastQtr,
            'year'         => $year,
            'range_start'  => $rangeStart->toDateTimeString(),
            'range_end'    => $rangeEnd->toDateTimeString(),
            'matched'      => $toMoveCount,
            'moved'        => $movedCount,
            'batch'        => $batch,
            'from_table'   => (new Cart)->getTable(),
            'to_table'     => (new CartSaveForLater)->getTable(),
        ]);
    }



}


