<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PdfContent;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class PdfContentController extends Controller
{
    public function index()
    {
        // Selection lists
        $offers     = Offer::where('status', 1)->orderByDesc('id')->get();
        $categories = Category::orderBy('name')->get();
        $brands     = Brand::orderBy('name')->get();

        // Existing mappings
        $pdfContents = PdfContent::orderByDesc('id')->paginate(25);

        return view('backend.pdf_contents.index', compact(
            'offers',
            'categories',
            'brands',
            'pdfContents'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pdf_type'       => 'required|string|max:100',
            // 'url'            => 'nullable|string|max:500',
            'placement_type' => 'nullable|in:first,last',
            'content_type'   => 'required|in:offer,product,category,brand',
            'content_ids'    => 'nullable|string',  // comma separated IDs
            'no_of_poster'   => 'nullable|integer|min:0', // ⬅️ NEW
        ]);

        // ek pdf_type ke liye sirf ek row — updateOrCreate
        PdfContent::updateOrCreate(
            ['pdf_type' => $request->pdf_type],
            [
                'url'              => $request->url,
                'placement_type'   => $request->placement_type,
                'content_type'     => $request->content_type,
                'content_products' => $request->content_ids,
                'no_of_poster'     => $request->no_of_poster, // ⬅️ NEW
            ]
        );

        return redirect()
            ->route('pdf_contents.index')
            ->with('success', 'PDF content mapping saved/updated successfully.');
    }

    /**
     * Product search by part_no (AJAX)
     */
    public function searchProducts(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([
                'success'  => false,
                'message'  => 'Empty query.',
                'products' => [],
            ]);
        }

        $products = Product::where('part_no', 'LIKE', '%' . $q . '%')
            ->orderBy('part_no')
            ->take(10)
            ->get(['id', 'part_no', 'name', 'thumbnail_img']);

        $mapped = $products->map(function ($p) {
            return [
                'id'            => $p->id,
                'part_no'       => $p->part_no,
                'name'          => $p->name,
                'thumbnail_url' => uploaded_asset($p->thumbnail_img),
            ];
        });

        return response()->json([
            'success'  => true,
            'products' => $mapped,
        ]);
    }


    
}
