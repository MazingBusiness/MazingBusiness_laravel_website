<?php

namespace App\Services;

use App\Models\PdfContent;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Offer;

class PdfContentService
{
    public function buildBlockForType(string $pdfType): ?array
    {
        $content = PdfContent::where('pdf_type', $pdfType)->first();

        if (!$content) {
            return null;
        }

        // Parse IDs from comma string
        $ids = collect(explode(',', (string) $content->content_products))
            ->map(function ($v) {
                return (int) trim($v);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return null;
        }

        $items = collect();

        switch ($content->content_type) {
            case 'product':
                $items = Product::whereIn('id', $ids)
                    ->get(['id', 'name', 'part_no', 'thumbnail_img'])
                    ->map(function ($p) {
                        return [
                            'id'        => (int) $p->id,
                            'name'      => (string) $p->name,
                            'subtitle'  => null, // agar code dikhana ho to yahan $p->part_no
                            'image_url' => $p->thumbnail_img ? uploaded_asset($p->thumbnail_img) : null,
                        ];
                    });
                break;

            case 'category':
                $items = Category::whereIn('id', $ids)
                    ->get(['id', 'name', 'banner'])
                    ->map(function ($c) {
                        return [
                            'id'        => (int) $c->id,
                            'name'      => (string) $c->name,
                            'subtitle'  => null,
                            'image_url' => !empty($c->banner) ? uploaded_asset($c->banner) : null,
                        ];
                    });
                break;

            case 'brand':
                $items = Brand::whereIn('id', $ids)
                    ->get(['id', 'name', 'logo'])
                    ->map(function ($b) {
                        return [
                            'id'        => (int) $b->id,
                            'name'      => (string) $b->name,
                            'subtitle'  => null,
                            'image_url' => !empty($b->logo) ? uploaded_asset($b->logo) : null,
                        ];
                    });
                break;

            case 'offer':
                $items = Offer::whereIn('id', $ids)
                    ->get(['id', 'offer_name', 'offer_id', 'offer_banner'])
                    ->map(function ($o) {
                        return [
                            'id'        => (int) $o->id,
                            'name'      => $o->offer_name ?: $o->offer_id,
                            'subtitle'  => null, // agar code dikhana ho to yahan $o->offer_id
                            'image_url' => !empty($o->offer_banner) ? uploaded_asset($o->offer_banner) : null,
                        ];
                    });
                break;

            default:
                return null;
        }

        if ($items->isEmpty()) {
            return null;
        }

        // IDs ke order ke hisaab se sort + plain array
        $items = $items
            ->sortBy(function ($row) use ($ids) {
                return array_search($row['id'], $ids);
            })
            ->values()
            ->all();

        return [
            'placement'     => $content->placement_type ?: 'last',
            'content_type'  => $content->content_type,
            'items'         => $items,
            'no_of_poster'  => (int) ($content->no_of_poster ?? 0), // 👈 yahan se Blade ko value milegi
        ];
    }
}
