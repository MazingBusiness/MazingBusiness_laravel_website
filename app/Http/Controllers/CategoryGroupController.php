<?php

namespace App\Http\Controllers;

use App\Models\CategoryGroup;
use App\Models\OwnBrandCategoryGroup;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryGroupController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_product_category_groups'])->only('index');
    $this->middleware(['permission:add_product_category_groups'])->only('create');
    $this->middleware(['permission:edit_product_category_groups'])->only('edit');
    $this->middleware(['permission:delete_product_category_groups'])->only('destroy');
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request) {
    $sort_search     = null;
    $category_groups = CategoryGroup::orderBy('order', 'asc');
    if ($request->has('search')) {
      $sort_search     = $request->search;
      $category_groups = $category_groups->where('name', 'like', '%' . $sort_search . '%');
    }
    $category_groups = $category_groups->paginate(15);
    return view('backend.product.categories.groups.index', compact('category_groups', 'sort_search'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    return view('backend.product.categories.groups.create');
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {
    $category_group        = new CategoryGroup;
    $category_group->name  = $request->name;
    $category_group->order = 0;
    if ($request->order != null) {
      $category_group->order = $request->order;
    }
    $category_group->banner           = $request->banner;
    $category_group->icon             = $request->icon;
    $category_group->meta_title       = $request->meta_title;
    $category_group->meta_description = $request->meta_description;
    if ($request->slug != null) {
      $category_group->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->slug));
    } else {
      $category_group->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->name)) . '-' . Str::random(5);
    }
    $category_group->save();
    flash(translate('Category Group has been inserted successfully'))->success();
    return redirect()->route('category-groups.index');
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
    $lang           = $request->lang;
    $category_group = CategoryGroup::findOrFail($id);
    return view('backend.product.categories.groups.edit', compact('category_group', 'lang'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $category_group = CategoryGroup::findOrFail($id);
    if ($request->order != null) {
      $category_group->order = $request->order;
    }
    $category_group->banner           = $request->banner;
    $category_group->icon             = $request->icon;
    $category_group->meta_title       = $request->meta_title;
    $category_group->meta_description = $request->meta_description;

    if ($request->slug != null) {
      $category_group->slug = strtolower($request->slug);
    } else {
      $category_group->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $request->name)) . '-' . Str::random(5);
    }
    $category_group->save();
    Cache::forget('featured_categories');
    flash(translate('Category Group has been updated successfully'))->success();
    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $category_group = CategoryGroup::findOrFail($id);
    $category_group->attributes()->detach();
    Cache::forget('featured_categories');
    flash(translate('Category Group has been deleted successfully'))->success();
    return redirect()->route('categories.group.index');
  }

  public function updateFeatured(Request $request) {
    $category_group           = CategoryGroup::findOrFail($request->id);
    $category_group->featured = $request->status;
    $category_group->save();
    Cache::forget('featured_categories');
    return 1;
  }

  public function ownBrandCategoryGroups(Request $request) {
    $sort_search     = null;
    $category_groups = OwnBrandCategoryGroup::orderBy('name', 'asc');
    if ($request->has('search')) {
      $sort_search     = $request->search;
      $category_groups = $category_groups->where('name', 'like', '%' . $sort_search . '%');
    }
    $category_groups = $category_groups->paginate(15);
    return view('backend.product.categories.groups.ownBrandCategoryGroups', compact('category_groups', 'sort_search'));
  }
  public function ownBrandCategoryGroupsCreate() {
    return view('backend.product.categories.groups.ownBrandCategoryGroupsCreate');
  }
  public function submmitOwnBrandCategoryGroups(Request $request) {
    $category_group        = new OwnBrandCategoryGroup;
    $category_group->name  = $request->name;
    $category_group->banner           = $request->banner;
    $category_group->icon             = $request->icon;
    $category_group->meta_title       = $request->meta_title;
    $category_group->meta_description = $request->meta_description;
    if ($request->slug != null) {
      $category_group->slug = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')));
    } else {
      $category_group->slug = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')) . '-' . Str::random(5));
    }
    $category_group->save();
    flash(translate('Category Group has been inserted successfully'))->success();
    return redirect()->route('category-groups.ownBrandCategoryGroups');
  }
  public function ownBrandCategoryGroupsEdit(Request $request, $id) {
    $lang           = $request->lang;
    $category_group = OwnBrandCategoryGroup::findOrFail($id);
    return view('backend.product.categories.groups.ownBrandCategoryGroupsEdit', compact('category_group', 'lang'));
  }
  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function ownBrandCategoryGroupsUpdate(Request $request, $id) {
    $category_group = OwnBrandCategoryGroup::findOrFail($id);
    $category_group->banner           = $request->banner;
    $category_group->icon             = $request->icon;
    $category_group->meta_title       = $request->meta_title;
    $category_group->meta_description = $request->meta_description;

    if ($request->slug != null) {
      $category_group->slug = strtolower($request->slug);
    } else {
      $category_group->slug = strtolower(preg_replace('/[^A-Za-z0-9\-]/', '', str_replace([' ', '"', "'", '(', ')', '/', '\\', '.'], '-', $request->name ?? '')) . '-' . Str::random(5));
    }
    $category_group->save();
    flash(translate('Category Group has been updated successfully'))->success();
    return redirect()->route('category-groups.ownBrandCategoryGroups');
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function ownBrandCategoryGroupDelete($id) {
    $category_group = OwnBrandCategoryGroup::findOrFail($id);
    // $category_group->attributes()->detach();
    $category_group->delete(); // Soft deletes the record
    
    Cache::forget('featured_categories');
    flash(translate('Category Group has been deleted successfully'))->success();
    return redirect()->route('category-groups.ownBrandCategoryGroups');
  }

}
