<?php

namespace App\Models;

use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\ProductWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use Auth;
use Cache;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Str;

//class ProductsImport implements ToModel, WithHeadingRow, WithValidation
class ProductsImport implements ToCollection, WithHeadingRow, WithValidation, ToModel {
  private $rows = 0;

  public function collection(Collection $rows) {
    $canImport = true;
    $user      = Auth::user();
    if ($user->user_type == 'seller' && addon_is_activated('seller_subscription')) {
      if ((count($rows) + $user->products()->count()) > $user->shop->product_upload_limit
        || $user->shop->package_invalid_at == null
        || Carbon::now()->diffInDays(Carbon::parse($user->shop->package_invalid_at), false) < 0
      ) {
        $canImport = false;
        flash(translate('Please upgrade your package.'))->warning();
      }
    }

    if ($canImport) {
      $warehouses = Cache::remember('warehouses_list', 86400, function () {
        return Warehouse::select('id', 'name')->get();
      });
      $sellers = Cache::remember('sellers_list', 86400, function () {
        return User::select('id', 'name')->where('user_type', 'seller')->get();
      });
      $sellers_data = Cache::remember('sellers_data_list', 86400, function () {
        return Seller::select('id', 'user_id')->get();
      });
      $categories = Cache::remember('categories_list', 86400, function () {
        return Category::select('id', 'name')->with('attributes:id,name', 'attributes.attribute_values')->get();
      });
      $brands = Cache::remember('brands_list', 86400, function () {
        return Brand::select('id', 'name')->get();
      });
      $last_part_no = ProductWarehouse::orderBy('part_no', 'desc')->orderBy('id', 'desc')->first();
      if ($last_part_no) {
        $last_part_no = (int) filter_var($last_part_no->part_no, FILTER_SANITIZE_NUMBER_INT) + 1;
      } else {
        $last_part_no = 1;
      }
      foreach ($rows as $row) {
        $approved = 1;
        $row['seller'] = 'VELMOC';
        if ($user->user_type == 'seller') {
          $seller_id = $user->id;
          if (get_setting('product_approve_by_admin') == 1) {
            $approved = 0;
          }
        } else {
          $seller_id = ($row['seller'] && $sellers->locate('name', $row['seller'])->first()) ? $sellers_data->where('user_id', $sellers->locate('name', $row['seller'])->first()->id)->first()->id : 0;
          // $seller_id = 93;
          if ($user->user_type == 'staff' || !$row['warehouse']) {
            $warehouse_id = $user->warehouse_id;
          } else {
            $warehouse_id = ($row['warehouse'] && $warehouses->locate('name', $row['warehouse'])->first()) ? $warehouses->locate('name', $row['warehouse'])->first()->id : 0;
          }
        }
        $category_id = ($row['category'] && $categories->locate('name', $row['category'])->first()) ? $categories->locate('name', $row['category'])->first()->id : 0;
        $attributes  = $choice_options  = $variant  = [];
        if ($category_id) {
          $all_attributes = $categories->where('id', $category_id)->first()->has('attributes') ? $categories->where('id', $category_id)->first()->attributes : [];
          $count          = 1;
          foreach ($all_attributes as $attribute) {
            $attr_value   = $row['attribute_' . $count];
            $attributes[] = (string) $attribute->id;
            if ($attr_value) {
              AttributeValue::firstOrCreate(['attribute_id' => $attribute->id, 'value' => ucwords(strtolower($attr_value))]);
            }
            $choice_options[] = ["attribute_id" => (string) $attribute->id, "values" => ($attr_value) ? [(string) ucwords(strtolower($attr_value))] : []];
            if ($variant) {
              $variant .= '_' . str_replace(' ', '', strtolower($attr_value));
            } else {
              $variant = str_replace(' ', '', strtolower($attr_value));
            }
            $count++;
          }
        }
        $slug    = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($row['name']))) . '-' . Str::random(5);
        $product = Product::updateOrCreate(['name' => ucwords(strtolower($row['name'])), 'category_id' => $category_id], [
          'name'                   => ucwords(strtolower($row['name'])),
          'alias_name'             => ucwords(strtolower($row['alias_name'])),
          'import_duty'            => $row['import_duty'],
          'mrp'                    => $row['mrp'],
          'seller_id'              => $seller_id,
          'billing_name'           => $row['billing_name'],
          'added_by'               => $user->user_type == 'seller' ? 'seller' : 'admin',
          'user_id'                => $user->user_type == 'seller' ? $user->id : User::where('user_type', 'admin')->first()->id,
          'category_id'            => $category_id,
          'brand_id'               => ($row['brand'] && $brands->locate('name', $row['brand'])->first()) ? $brands->locate('name', $row['brand'])->first()->id : 0,
          'tags'                   => $row['tags'],
          'description'            => $row['description'],
          'generic_name'           => $row['generic_name'] ? ucwords(strtolower($row['generic_name'])) : null,
          'unit_price'             => round(($row['price'] * 100 / 118), 2),
          'attributes'             => json_encode($attributes),
          'colors'                 => json_encode(array()),
          'variations'             => json_encode(array()),
          'published'              => $row['published'] ? $row['published'] : 0,
          'approved'               => $approved,
          'cash_on_delivery'       => $row['cash_on_delivery'] ? $row['cash_on_delivery'] : 0,
          'featured'               => $row['featured'] ? $row['featured'] : 0,
          'min_qty'                => $row['min_qty'] ? $row['min_qty'] : 1,
          'current_stock'          => 1,
          'stock_visibility_state' => 'hide',
          'low_stock_quantity'     => $row['low_stock_quantity'],
          'est_shipping_days'      => 3,
          'meta_title'             => $row['meta_title'],
          'meta_description'       => $row['meta_description'],
        ]);
        if (!$product->slug) {
          $product->slug = $slug;
          $product->save();
        }
        if (!$product->choice_options) {
          $product->choice_options = json_encode($choice_options);
          $product->save();
        } else {
          $old_chop = collect(json_decode($product->choice_options))->keyBy('attribute_id');
          foreach ($choice_options as $chop) {
            if (isset($old_chop[$chop['attribute_id']])) {
              array_merge($old_chop[$chop['attribute_id']]->values, $chop['values']);
              $old_chop[$chop['attribute_id']]->values = array_unique($old_chop[$chop['attribute_id']]->values);
            }
          }
          $product->choice_options = json_encode($old_chop->values());
          $product->save();
        }
        if ($row['part_no']) {
          $productw = ProductWarehouse::updateOrCreate(['part_no' => $row['part_no'], 'warehouse_id' => $warehouse_id], [
            'product_id'       => $product->id,
            'seller_id'        => $seller_id,
            'sz_category'      => $row['sz_category'],
            'sz_group'         => $row['sz_group'],
            'sz_manual_price'  => $row['sz_manual_price'],
            'hsncode'          => $row['hsncode'],
            'print_name'       => ucwords(strtolower($row['name'])) . ' ' . ucwords(strtolower($row['model_no'])),
            'variant'          => $variant ? $variant : null,
            'model_no'         => $row['model_no'],
            'seller_sku'       => $row['seller_sku'],
            'price'            => round(($row['price'] * 100 / 118), 2),
            'carton_price'     => round(($row['carton_price'] * 100 / 118), 2),
            'piece_per_carton' => $row['piece_per_carton'] ? $row['piece_per_carton'] : 0,
            'qty'              => $row['warehouse_stock'] ? $row['warehouse_stock'] : 0,
            'seller_stock'     => $row['seller_stock'] ? $row['seller_stock'] : 0,
            'length'           => $row['length'] ? $row['length'] : 0,
            'height'           => $row['height'] ? $row['height'] : 0,
            'breadth'          => $row['breadth'] ? $row['breadth'] : 0,
            'cbm'              => $row['cbm'] ? $row['cbm'] : 0,
            'carton_cbm'       => $row['carton_cbm'] ? $row['carton_cbm'] : 0,
            'weight'           => $row['weight'] ? $row['weight'] : 0,
          ]);
        } else {
          $productw = ProductWarehouse::create([
            'product_id'       => $product->id,
            'warehouse_id'     => $warehouse_id,
            'seller_id'        => $seller_id,
            'sz_category'      => $row['sz_category'],
            'sz_group'         => $row['sz_group'],
            'sz_manual_price'  => $row['sz_manual_price'],
            'part_no'          => 'MZXXXXX',
            'hsncode'          => $row['hsncode'],
            'print_name'       => ucwords(strtolower($row['name'])) . ' ' . ucwords(strtolower($row['model_no'])),
            'variant'          => $variant ? $variant : null,
            'model_no'         => $row['model_no'],
            'seller_sku'       => $row['seller_sku'],
            'price'            => round(($row['price'] * 100 / 118), 2),
            'carton_price'     => round(($row['carton_price'] * 100 / 118), 2),
            'piece_per_carton' => $row['piece_per_carton'] ? $row['piece_per_carton'] : 0,
            'qty'              => $row['warehouse_stock'] ? $row['warehouse_stock'] : 0,
            'seller_stock'     => $row['seller_stock'] ? $row['seller_stock'] : 0,
            'length'           => $row['length'] ? $row['length'] : 0,
            'height'           => $row['height'] ? $row['height'] : 0,
            'breadth'          => $row['breadth'] ? $row['breadth'] : 0,
            'cbm'              => $row['cbm'] ? $row['cbm'] : 0,
            'carton_cbm'       => $row['carton_cbm'] ? $row['carton_cbm'] : 0,
            'weight'           => $row['weight'] ? $row['weight'] : 0,
          ]);
        }
        ProductTax::updateOrCreate(['product_id' => $product->id], [
          'product_id' => $product->id,
          'tax_id'     => 1,
          'tax'        => 18,
          'tax_type'   => 'percent',
        ]);
        ProductTranslation::updateOrCreate(['product_id' => $product->id], [
          'product_id'  => $product->id,
          'name'        => ucwords(strtolower($row['name'])),
          'unit'        => 'Pc',
          'description' => $row['description'],
          'lang'        => 'en',
        ]);
        $pws    = ProductWarehouse::where('product_id', $product->id)->orderBy('variant', 'asc')->get();
        $last_v = $last_pno = '';
        foreach ($pws as $pw) {
          if ($pw->part_no == 'MZXXXXX') {
            if ($pw->variant != $last_v) {
              $pw->part_no = 'MZ' . str_pad($last_part_no++, 5, '0', STR_PAD_LEFT);
              $pw->save();
            } else {
              $pw->part_no = $last_pno;
              if (!$pw->part_no) {
                $pw->part_no = 'MZ' . str_pad($last_part_no++, 5, '0', STR_PAD_LEFT);
              }
              $pw->save();
            }
          }
          $last_v   = $pw->variant;
          $last_pno = $pw->part_no;
        }
        $pws = ProductWarehouse::where('product_id', $product->id)->get();
        if ($pws->groupBy('part_no')->count() > 1) {
          $pw = Product::where('id', $product->id)->update(['variant_product' => 1]);
        }
      }
      Cache::forget('categories_list');
      flash(translate('Products imported successfully'))->success();
    }
  }

  public function model(array $row) {
    ++$this->rows;
  }

  public function getRowCount(): int {
    return $this->rows;
  }

  public function rules(): array {
    return [
      // Can also use callback validation rules
      'price' => function ($attribute, $value, $onFailure) {
        if (!is_numeric($value)) {
          $onFailure('Unit price is not numeric');
        }
      },
    ];
  }

  public function downloadThumbnail($url) {
    try {
      $upload                = new Upload;
      $upload->external_link = $url;
      $upload->type          = 'image';
      $upload->save();

      return $upload->id;
    } catch (\Exception $e) {
    }
    return null;
  }

  public function downloadGalleryImages($urls) {
    $data = array();
    foreach (explode(',', str_replace(' ', '', $urls)) as $url) {
      $data[] = $this->downloadThumbnail($url);
    }
    return implode(',', $data);
  }
}
