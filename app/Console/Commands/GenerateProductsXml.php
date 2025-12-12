<?php

namespace App\Console\Commands;

use App\Models\Product;
use File;
use Illuminate\Console\Command;

class GenerateProductsXml extends Command {
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xml:products';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Generates a list of products for Merchant Center XML';

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
    $products = Product::select('id', 'name', 'description', 'slug', 'brand_id', 'category_id', 'unit_price', 'photos')->with(['brand:id,name', 'stocks:id,product_id,qty', 'category:id,parent_id,category_group_id,name,google_category_id', 'taxes:id,product_id,tax'])->where('published', 1)->where('unit_price', '!=', 0)->get();
    File::put('products.xml', view('backend.downloads.products', compact('products')));
  }
}
