<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportPoItem extends Model
{
    protected $table = 'import_po_items';
    public $timestamps = true;

    protected $fillable = [
        'import_po_id',
        'product_id',              // abhi text / int jo bhi column me hai, Eloquent ko farak nahi
        // 'line_no',
        'supplier_model_no',
        'description',
        'packing_details',
        'requirement_qty',
        'unit_price_usd',
        'unit_price_rmb',
        'packaging',
        'weight_per_carton_kg',
        'cbm_per_carton',
        'photo_id',                // text/id string
        'quantity_allocated',
        'quantity_balance',
        'remarks',
    ];
    
    /**
     * Casts
     *  - packing_details: JSON <-> array
     */
    protected $casts = [
        'packing_details' => 'array',
    ];

    // ------------ Relations ------------

    public function po()
    {
        return $this->belongsTo(ImportPo::class, 'import_po_id');
    }

    // agar product_id me products.id store kar rahe ho to yeh relation kaam karega
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // agar photo_id me uploads.id ya koi string id store hai
    public function photo()
    {
        return $this->belongsTo(Upload::class, 'photo_id');
    }

    public function loadingListItems()
    {
        return $this->hasMany(LoadingListItem::class, 'import_po_item_id');
    }
    
      // ------------ Accessors / Mutators for packing_details ------------

    /**
     * Hamesha clean array return karega:
     * [
     *   ['key' => 'Inner Box', 'value' => '10 pcs'],
     *   ...
     * ]
     */
    public function getPackingDetailsArrayAttribute()
    {
        $value = $this->packing_details;   // cast ke baad already array ya null

        if (is_array($value)) {
            // normalize: key/value structure ensure
            return array_values(array_map(function ($row) {
                return [
                    'key'   => isset($row['key'])   ? (string)$row['key']   : '',
                    'value' => isset($row['value']) ? (string)$row['value'] : '',
                ];
            }, $value));
        }

        return [];
    }
    
    /**
     * UI ke liye ek compact display string:
     * "Inner Box: 10 pcs; Outer Box: 5 inner; Color: Red"
     */
    public function getPackingDetailsDisplayAttribute()
    {
        $rows  = $this->packing_details_array; // accessor above
        $parts = [];

        foreach ($rows as $row) {
            $k = trim($row['key']   ?? '');
            $v = trim($row['value'] ?? '');

            if ($k === '' && $v === '') {
                continue;
            }

            if ($k !== '' && $v !== '') {
                $parts[] = $k . ': ' . $v;
            } elseif ($k !== '') {
                $parts[] = $k;
            } else {
                $parts[] = $v;
            }
        }

        return implode('; ', $parts);
    }

}
