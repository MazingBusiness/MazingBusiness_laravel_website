<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\CategoryTranslation;
use App\Models\Product;
use App\Models\OwnBrandCategory;
use App\Models\OwnBrandCategoryGroup;

use App\Utility\CategoryUtility;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_product_categories'])->only('index');
    $this->middleware(['permission:add_product_category'])->only('create');
    $this->middleware(['permission:edit_product_category'])->only('edit');
    $this->middleware(['permission:delete_product_category'])->only('destroy');
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request) {


    $sort_search = null;
    // $categories  = Category::orderBy('order_level', 'desc');
    // if ($request->has('search')) {
    //   $sort_search = $request->search;
    //   $categories  = $categories->where('name', 'like', '%' . $sort_search . '%');
    // }
    // $categories = $categories->paginate(15);
    // $categories = Category::orderByRaw("CASE WHEN name = 'Power Tools' THEN 0 ELSE 1 END")
    //                   ->orderBy('order_level', 'desc');
    $categories = Category::leftJoin('uploads as banner_upload', 'categories.banner', '=', 'banner_upload.id')
                      ->leftJoin('uploads as icon_upload', 'categories.icon', '=', 'icon_upload.id')
                      ->select('categories.*', 'banner_upload.file_name as banner_image', 'icon_upload.file_name as icon_image')
                      ->orderByRaw("CASE WHEN categories.name = 'Power Tools' THEN 0 ELSE 1 END")
                      ->orderBy('categories.order_level', 'desc');
                      



    if ($request->has('search')) {
        $sort_search = $request->search;
        $categories = $categories->where('name', 'like', '%' . $sort_search . '%');
    }

    $categories = $categories->paginate(15);
    // echo "<pre>";
    // print_r($categories);
    // die();
    return view('backend.product.categories.index', compact('categories', 'sort_search'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    $categories = Category::where('parent_id', 0)
      ->with('childrenCategories')
      ->get();
    $category_groups = CategoryGroup::orderBy('name', 'asc')->get();
    return view('backend.product.categories.create', compact('categories', 'category_groups'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {
    $category              = new Category;
    $category->name        = $request->name;
    $category->order_level = 0;
    if ($request->order_level != null) {
      $category->order_level = $request->order_level;
    }
    if ($request->category_group_id != "0") {
      $category->category_group_id = $request->category_group_id;
    }
    $category->banner           = $request->banner;
    $category->icon             = $request->icon;
    $category->meta_title       = $request->meta_title;
    $category->meta_description = $request->meta_description;
    $category->meta_keywords    = $request->meta_keywords;
    $category->page_description = $request->page_description;
    if ($request->parent_id != "0") {
      $category->parent_id = $request->parent_id;
      $parent              = Category::find($request->parent_id);
      $category->level     = $parent->level + 1;
    }
    if ($request->slug != null) {
      $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->slug));
    } else {
      $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->name)) . '-' . Str::random(5);
    }
    if ($request->markup != null) {
      $category->markup = $request->markup;
    }
    if ($request->has('linked_categories')) {
      $category->linked_categories = implode(',', $request->linked_categories);
    }
    $category->save();
    $category->attributes()->sync($request->filtering_attributes);
    $category_translation       = CategoryTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'category_id' => $category->id]);
    $category_translation->name = $request->name;
    $category_translation->save();
    flash(translate('Category has been inserted successfully'))->success();
    return redirect()->route('categories.index');
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id) {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit(Request $request, $id) {
    $lang       = $request->lang;
    $category   = Category::findOrFail($id);
    $categories = Category::where('parent_id', 0)
      ->with('childrenCategories')
      ->whereNotIn('id', CategoryUtility::children_ids($category->id, true))->where('id', '!=', $category->id)
      ->orderBy('name', 'asc')
      ->get();
    $category_groups = CategoryGroup::orderBy('name', 'asc')->get();
    return view('backend.product.categories.edit', compact('category', 'categories', 'category_groups', 'lang'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $category = Category::findOrFail($id);
    if ($request->lang == env("DEFAULT_LANGUAGE")) {
      $category->name = $request->name;
    }
    if ($request->order_level != null) {
      $category->order_level = $request->order_level;
    }
    if ($request->category_group_id != "0") {
      $category->category_group_id = $request->category_group_id;
    }
    $category->banner           = $request->banner;
    $category->icon             = $request->icon;
    $category->meta_title       = $request->meta_title;
    $category->meta_keywords    = $request->meta_keywords;
    $category->meta_description = $request->meta_description;
    $category->page_description = $request->page_description;
    $previous_level             = $category->level;

    if ($request->parent_id != "0") {
      $category->parent_id = $request->parent_id;

      $parent          = Category::find($request->parent_id);
      $category->level = $parent->level + 1;
    } else {
      $category->parent_id = 0;
      $category->level     = 0;
    }

    if ($category->level > $previous_level) {
      CategoryUtility::move_level_down($category->id);
    } elseif ($category->level < $previous_level) {
      CategoryUtility::move_level_up($category->id);
    }

    if ($request->slug != null) {
      $category->slug = strtolower($request->slug);
    } else {
      $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->name)) . '-' . Str::random(5);
    }

    if ($request->markup != null) {
      $category->markup = $request->markup;
    }
    if ($request->linked_categories != null) {
      $category->linked_categories = implode(',', $request->linked_categories);
    }
    $category->save();
    $category->attributes()->sync($request->filtering_attributes);

    $category_translation       = CategoryTranslation::firstOrNew(['lang' => $request->lang, 'category_id' => $category->id]);
    $category_translation->name = $request->name;
    $category_translation->save();

    Cache::forget('featured_categories');
    flash(translate('Category has been updated successfully'))->success();
    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $category = Category::findOrFail($id);
    $category->attributes()->detach();

    // Category Translations Delete
    foreach ($category->category_translations as $key => $category_translation) {
      $category_translation->delete();
    }

    foreach (Product::where('category_id', $category->id)->get() as $product) {
      $product->category_id = null;
      $product->save();
    }

    CategoryUtility::delete_category($id);
    Cache::forget('featured_categories');

    flash(translate('Category has been deleted successfully'))->success();
    return redirect()->route('categories.index');
  }

  public function updateFeatured(Request $request) {
    $category           = Category::findOrFail($request->id);
    $category->featured = $request->status;
    $category->save();
    Cache::forget('featured_categories');
    return 1;
  }

  public function ownBrandCategories(Request $request) {
    $sort_search = null;
    $categories = OwnBrandCategory::orderBy('name', 'desc');
    if ($request->has('search')) {
        $sort_search = $request->search;
        $categories = $categories->where('name', 'like', '%' . $sort_search . '%');
    }
    $categories = $categories->paginate(15);
    return view('backend.product.categories.ownBrandCategories', compact('categories', 'sort_search'));
  }

  public function ownBrandCategoryCreate() {
    $category_groups = OwnBrandCategoryGroup::orderBy('name', 'asc')->get();
    return view('backend.product.categories.ownBrandCategoryCreate', compact('category_groups'));
  }

  public function submmitOwnBrandCategory(Request $request) {
    $category              = new OwnBrandCategory;
    $category->name        = $request->name;
    
    if ($request->category_group_id != "0") {
      $category->category_group_id = $request->category_group_id;
    }
    $category->banner           = $request->banner;
    $category->icon             = $request->icon;
    $category->meta_title       = $request->meta_title;
    $category->meta_description = $request->meta_description;
    $category->meta_keywords    = $request->meta_keywords;
    $category->page_description = $request->page_description;
    if ($request->slug != null) {
      $category->slug = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')));
    } else {
      $category->slug = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')) . '-' . Str::random(5));
    }
    
    $category->save();
    // $category_translation = CategoryTranslation::firstOrNew(['lang' => env('DEFAULT_LANGUAGE'), 'category_id' => $category->id]);
    // $category_translation->name = $request->name;
    // $category_translation->save();
    flash(translate('Category has been inserted successfully'))->success();
    return redirect()->route('categories.ownBrandCategories');
  }

  public function ownBrandCategoryEdit(Request $request, $id) {
    $lang       = $request->lang;
    $category   = OwnBrandCategory::findOrFail($id);
    $category_groups = OwnBrandCategoryGroup::orderBy('name', 'asc')->get();
    return view('backend.product.categories.ownBrandCategoryEdit', compact('category', 'category_groups', 'lang'));
  }

  public function ownBrandCategoryUpdate(Request $request, $id) {
    $category = OwnBrandCategory::findOrFail($id);
    if ($request->lang == env("DEFAULT_LANGUAGE")) {
      $category->name = $request->name;
    }
    
    if ($request->category_group_id != "0") {
      $category->category_group_id = $request->category_group_id;
    }
    $category->banner           = $request->banner;
    $category->icon             = $request->icon;
    $category->meta_title       = $request->meta_title;
    $category->meta_keywords    = $request->meta_keywords;
    $category->meta_description = $request->meta_description;
    $category->page_description = $request->page_description;
    

    if ($request->slug != null) {
      $category->slug = strtolower($request->slug);
    } else {
      $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->name)) . '-' . Str::random(5);
    }    
    $category->save();

    Cache::forget('featured_categories');
    flash(translate('Category has been updated successfully'))->success();
    return redirect()->route('categories.ownBrandCategories');
  }

  public function ownBrandCategoryDelete($id) {
    $category = OwnBrandCategory::findOrFail($id);
    // $category_group->attributes()->detach();
    $category->delete(); // Soft deletes the record
    
    Cache::forget('featured_categories');
    flash(translate('Category has been deleted successfully'))->success();
    return redirect()->route('categories.ownBrandCategories');
  }

}
