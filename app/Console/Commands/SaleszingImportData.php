<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SaleszingImportData extends Command {
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'saleszing:import-inventory';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Updates Warehouse Inventory Data based on entries in Saleszing';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle() {
    $response = Http::withHeaders([
      'authtoken' => '650299187cebc',
      'Accept'    => 'application/json',
    ])->post('http://101.53.149.93/itaapi/getwarehousestock.php', []);
    if ($response->successful()) {
      $data              = json_decode($response->body())->data;
      $warehouse_mapping = Warehouse::pluck('id', 'inhouse_saleszing_id');
      DB::transaction(function () use ($data, $warehouse_mapping) {
        foreach ($data as $product) {
          $pw = ProductWarehouse::where('warehouse_id', $warehouse_mapping[$product->warehouseId])->where('seller_sku', $product->partNo)->first();
          if ($pw) {
            $pw->qty          = (int) $product->inventoryStock;
            $pw->price        = $product->price - (0.24 * $product->price);
            $pw->carton_price = $product->price - (0.27 * $product->price);
            Product::where('id', $pw->product_id)->update(['unit_price' => $pw->price]);
            $pw->save();
          }
        }
      });
      $this->info('Product Warehouse Entries Updated!');
    }
  }
}
