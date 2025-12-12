<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\ProductWarehouse;
use App\Models\State;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller {
  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_all_warehouses'])->only('index');
    $this->middleware(['permission:add_warehouse'])->only('create', 'store');
    $this->middleware(['permission:edit_warehouse'])->only('edit', 'update');
    $this->middleware(['permission:delete_warehouse'])->only('destroy');
  }
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request) {
    $sort_search = null;
    $warehouses  = Warehouse::with('city', 'state')->orderBy('name', 'asc');
    if ($request->has('search')) {
      $sort_search = $request->search;
      $warehouses  = $warehouses->where('name', 'like', '%' . $sort_search . '%');
    }
    $warehouses = $warehouses->paginate(15);
    return view('backend.product.warehouses.index', compact('warehouses', 'sort_search'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    $states = State::where('status', 1)->where('country_id', 101)->get();
    return view('backend.product.warehouses.create', compact('states'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {
    $warehouse                       = new Warehouse;
    $warehouse->name                 = $request->name;
    $warehouse->address              = $request->address;
    $warehouse->city_id              = $request->city_id;
    $warehouse->state_id             = $request->state_id;
    $warehouse->pincode              = $request->pincode;
    $warehouse->inhouse_saleszing_id = $request->inhouse_saleszing_id;
    $warehouse->seller_saleszing_id  = $request->seller_saleszing_id;
    $warehouse->service_states       = implode(',', $request->service_states);
    $warehouse->phone                = $request->phone;
    $warehouse->save();
    flash(translate('Warehouse has been inserted successfully'))->success();
    return redirect()->route('warehouses.index');
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit(Request $request, $id) {
    $warehouse = Warehouse::findOrFail($id);
    $states    = State::where('status', 1)->where('country_id', 101)->get();
    $cities    = City::where('status', 1)->whereIn('state_id', $states->pluck('id'))->get();
    return view('backend.product.warehouses.edit', compact('warehouse', 'cities', 'states'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $warehouse                       = Warehouse::findOrFail($id);
    $warehouse->name                 = $request->name;
    $warehouse->address              = $request->address;
    $warehouse->city_id              = $request->city_id;
    $warehouse->state_id             = $request->state_id;
    $warehouse->pincode              = $request->pincode;
    $warehouse->phone                = $request->phone;
    $warehouse->inhouse_saleszing_id = $request->inhouse_saleszing_id;
    $warehouse->seller_saleszing_id  = $request->seller_saleszing_id;
    $warehouse->service_states       = implode(',', $request->service_states);
    $markups                         = [];
    $count                           = 0;
    foreach ($request->warehouses as $wh) {
      array_push($markups, ["warehouse_id" => $wh, "markup" => $request->markups[$count++]]);
    }
    $warehouse->markup = $markups;
    $warehouse->save();
    flash(translate('Warehouse has been updated successfully'))->success();
    return back();
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id) {
    $warehouse = Warehouse::findOrFail($id);
    User::where('warehouse_id', $warehouse->id)->delete();
    ProductWarehouse::where('warehouse_id', $warehouse->id)->delete();
    Warehouse::destroy($id);
    flash(translate('Warehouse has been deleted successfully'))->success();
    return redirect()->route('warehouses.index');

  }
}
