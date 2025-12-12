<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductsDemoExport;
use App\Models\ProductsExport;
use App\Models\ProductsImport;
use App\Models\StocksExport;
use App\Models\User;
use Auth;
use Excel;
use Illuminate\Http\Request;
use PDF;

class ProductBulkUploadController extends Controller {
  public function __construct() {

    $this->middleware(['permission:product_bulk_import'])->only('index');
    $this->middleware(['permission:product_bulk_export'])->only('export');
    $this->middleware(['permission:download_seller_products'])->only(['sellerStocksExport', 'sellerProductsExport']);
    $this->middleware(['permission:download_warehouse_products'])->only(['warehouseStocksExport', 'warehouseProductsExport']);
  }

  public function index() {
    if (Auth::user()->user_type == 'seller') {
      if (Auth::user()->shop->verification_status) {
        return view('seller.product_bulk_upload.index');
      } else {
        flash('Your shop is not verified yet!')->warning();
        return back();
      }
    } elseif (Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'staff') {
      return view('backend.product.bulk_upload.index');
    }
  }

  public function exportDemo() {
    return Excel::download(new ProductsDemoExport, 'products_bulk_demo.xlsx');
  }

  public function export() {
    return Excel::download(new ProductsExport, 'products.xlsx');
  }

  public function sellerProductsExport($id) {
    return Excel::download(new ProductsExport('seller', $id), 'Seller_' . $id . '_products.xlsx');
  }

  public function sellerStocksExport($id) {
    return Excel::download(new StocksExport('seller', $id), 'Seller_' . $id . '_stocks.xlsx');
  }

  public function warehouseProductsExport($id) {
    return Excel::download(new ProductsExport('warehouse', $id), 'Warehouse_' . $id . '_products.xlsx');
  }

  public function warehouseStocksExport($id) {
    return Excel::download(new StocksExport('warehouse', $id), 'Warehouse_' . $id . '_stocks.xlsx');
  }

  public function pdf_download_category() {
    $categories = Category::all();

    return PDF::loadView('backend.downloads.category', [
      'categories' => $categories,
    ], [], [])->download('category.pdf');
  }

  public function pdf_download_brand() {
    $brands = Brand::all();

    return PDF::loadView('backend.downloads.brand', [
      'brands' => $brands,
    ], [], [])->download('brands.pdf');
  }

  public function pdf_download_seller() {
    $users = User::where('user_type', 'seller')->get();

    return PDF::loadView('backend.downloads.user', [
      'users' => $users,
    ], [], [])->download('user.pdf');

  }

  public function bulk_upload(Request $request) {
    if ($request->hasFile('bulk_file')) {
      $import = new ProductsImport;
      Excel::import($import, request()->file('bulk_file'));
    }
    return back();
  }

}
