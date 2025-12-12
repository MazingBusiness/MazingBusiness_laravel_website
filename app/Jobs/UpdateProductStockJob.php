<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\ProductApi;
use App\Models\Manager41ProductStock;
use App\Models\Warehouse;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateProductStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle()
    {
        // Map godown → warehouse_id (same as your legacy script)
        $warehouses = Warehouse::orderBy('name')->where('active', '1')->get(['id', 'name']);
        $godownToWarehouse = [];
        foreach ($warehouses as $warehouse) {
            $godownToWarehouse[trim($warehouse->name)] = $warehouse->id;
        }
        Product::query()
            ->select([
                'id',
                'alias_name',
                'warehouse_id',
                'seller_id',
                'seller_stock',
                'is_manager_41',
                'slug',
                'part_no',
                'mrp',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($godownToWarehouse) {
                foreach ($products as $product) {
                    $this->processSingleProduct($product, $godownToWarehouse);
                }
            });
        \Log::info("Stock cron run successfully.");
    }

    protected function processSingleProduct(Product $product, array $godownToWarehouse)
    {
        // echo "<pre>"; print_r($product); die;
        $productId    = $product->id;
        $productAlias = $product->alias_name;
        $warehouseId  = $product->warehouse_id;
        $sellerId     = $product->seller_id;
        $sellerStock  = (int) $product->seller_stock;
        $isManager41  = isset($product->is_manager_41) ? (int) $product->is_manager_41 : 0;

        $slug   = addslashes($product->slug);
        $partNo = $product->part_no;
        $mrp    = $product->mrp;

        if ($mrp === '' || $mrp === null) {
            $mrp = 0;
        }

        // same behaviour as your legacy $warehouse_id variable
        $lastWarehouseId = $warehouseId;

        $flag = 0;

        \Log::info("Stock cron: Product ID {$productId}, Seller Stock {$sellerStock}");

        DB::beginTransaction();

        try {
            // 1) Clear all stock for this product
            ProductWarehouse::where('product_id', $productId)->delete();
            ProductWarehouse::where('part_no', $partNo)->delete();

            // reset current_stock
            $product->current_stock = 0;
            $product->save();

            // 2) Seller stock logic
            if ($sellerStock != 0) {
                $qty = 1;

                if ($mrp !== '0' && $mrp != '' && (float)$mrp > 0) {
                    $qty = (int) round(100000 / (float)$mrp);
                    if ($qty <= 0) {
                        $qty = 1;
                    }
                }

                ProductWarehouse::create([
                    'product_id'      => $productId,
                    'warehouse_id'    => $warehouseId,
                    'seller_id'       => $sellerId,
                    'seller_stock'    => 1,
                    'variant'         => $slug,
                    'part_no'         => $partNo,
                    'qty'             => $qty,
                    'sz_manual_price' => $mrp,
                    'is_manager_41'   => 0,   // if this column exists
                ]);

                $flag = 1;

                // keep as-is for seller logic
                $product->current_stock = 1;
                $product->save();
            }

            /*
             * 3) Manager-41 handling
             */

            if ($isManager41 === 1) {
                $total41 = Manager41ProductStock::where('part_no', $partNo)->count();

                if ($total41 > 0) {
                    // rows exist in manager_41_product_stocks
                    $rows41 = Manager41ProductStock::where('part_no', $partNo)
                        ->where('closing_stock', '>', 0)
                        ->get(['godown', 'closing_stock']);

                    foreach ($rows41 as $row41) {
                        $godown = $row41->godown;
                        $whId   = $godownToWarehouse[$godown] ?? null;
                        $qty41  = (int)$row41->closing_stock;

                        if ($whId && $qty41 > 0) {
                            ProductWarehouse::create([
                                'product_id'      => $productId,
                                'warehouse_id'    => $whId,
                                'seller_id'       => $sellerId,
                                'seller_stock'    => 0,
                                'variant'         => $slug,
                                'part_no'         => $partNo,
                                'qty'             => $qty41,
                                'sz_manual_price' => $mrp,
                                'is_manager_41'   => 1,
                            ]);

                            $flag = 1;
                            // intentionally NOT updating current_stock here
                        }
                    }
                } else {
                    // No 41 rows exist -> arbitrary insert
                    if (!empty($warehouseId)) {
                        $qty41 = 1;

                        if ($mrp !== '0' && $mrp != '' && (float)$mrp > 0) {
                            $qty41 = (int) round(100000 / (float)$mrp);
                            if ($qty41 <= 0) {
                                $qty41 = 1;
                            }
                        }

                        ProductWarehouse::create([
                            'product_id'      => $productId,
                            'warehouse_id'    => $warehouseId,
                            'seller_id'       => $sellerId,
                            'seller_stock'    => 0,
                            'variant'         => $slug,
                            'part_no'         => $partNo,
                            'qty'             => $qty41,
                            'sz_manual_price' => $mrp,
                            'is_manager_41'   => 1,
                        ]);

                        $flag = 1;
                        // intentionally NOT updating current_stock here
                    }
                }
            }

            // 4) products_api logic
            $apiRows = ProductApi::where('part_no', $partNo)
                ->where('closing_stock', '>', 0)
                ->get();

            foreach ($apiRows as $apiRow) {
                $godown = $apiRow->godown;
                $qty    = $apiRow->closing_stock;

                $mappedWarehouseId = $godownToWarehouse[$godown] ?? '';

                if ($mappedWarehouseId !== '') {
                    ProductWarehouse::create([
                        'product_id'      => $productId,
                        'warehouse_id'    => $mappedWarehouseId,
                        'seller_id'       => $sellerId,
                        'seller_stock'    => 0,
                        'variant'         => $slug,
                        'part_no'         => $partNo,
                        'qty'             => $qty,
                        'sz_manual_price' => $mrp,
                        'is_manager_41'   => 0,
                    ]);

                    // same as legacy code: update current_stock = 1 from products_api
                    $product->current_stock = 1;
                    $product->save();

                    $flag = 1;
                    $lastWarehouseId = $mappedWarehouseId;
                }
            }

            // 5) Fallback row if nothing got inserted
            if ($flag === 0 && !empty($lastWarehouseId)) {
                ProductWarehouse::create([
                    'product_id'      => $productId,
                    'warehouse_id'    => $lastWarehouseId,
                    'seller_id'       => $sellerId,
                    'seller_stock'    => 0,
                    'variant'         => $slug,
                    'part_no'         => $partNo,
                    'qty'             => 0,
                    'sz_manual_price' => $mrp,
                    'is_manager_41'   => 0,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error("Stock update failed for product {$productId}: {$e->getMessage()}", [
                'product_id' => $productId,
                'part_no'    => $partNo,
            ]);
        }
    }
}
