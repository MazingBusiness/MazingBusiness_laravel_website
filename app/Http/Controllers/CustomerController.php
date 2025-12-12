<?php

namespace App\Http\Controllers;

use App\Models\Pincode;
use App\Models\Staff;
use App\Models\User;
use App\Models\Address;
use App\Models\City;
use App\Models\State;
use App\Utility\WhatsAppUtility;
use Hash;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppWebService;
use App\Http\Controllers\ZohoController;

function debug_to_console($data) {
  $output = $data;
  if (is_array($output))
      $output = implode(',', $output);

  echo "<script>console.log('Debug Objects: " . $output . "' );</script>";
}

class CustomerController extends Controller {
  protected $WhatsAppWebService;

  public function __construct() {
    // Staff Permission Check
    $this->middleware(['permission:view_all_customers'])->only('index');
    $this->middleware(['permission:login_as_customer'])->only('login');
    $this->middleware(['permission:ban_customer'])->only('ban');
    // $this->middleware(['permission:add_new_customer'])->only('store');
    $this->middleware(['permission:edit_customer'])->only('edit', 'update');
    $this->middleware(['permission:delete_customer'])->only('destroy');
  }


 private function isActingAs41Manager(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Normalize
        $title = strtolower(trim((string) $user->user_title));
        $type  = strtolower(trim((string) $user->user_type));

        // Exactly check for manager_41 on current login
        if ($title === 'manager_41' || $type === 'manager_41') {
            return true;
        }

        // (Optional) tolerate common variants
        $aliases = ['41_manager'];
        return in_array($title, $aliases, true);
    }
 public function latest_index(Request $request)
{
    $sort_search = $request->input('search', null);
    $filter      = $request->input('filter', null);
    $user        = Auth::user();

    // Detect 41 Manager (any stored variant)
    $title        = strtolower((string) $user->user_title);
    $is41Manager  = in_array($title, ['manager_41','41 manager','41_manager','41manager'], true);

    // Fetch staffUsers: Managers with role_id 5
    $staffUsers = User::join('staff', 'users.id', '=', 'staff.user_id')
        ->where('staff.role_id', 5)
        ->where('users.banned', 0)
        ->select('users.*')
        ->get();

    $query = User::query()
        ->with([
            'warehouse:id,name',
            'manager:id,name',
            'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
            'total_due_amounts'
        ])
        ->whereIn('user_type', ['customer', 'warehouse'])
        ->whereNotNull('email_verified_at');

    // ✅ Full-access users: admin, head managers (IDs), and 41 Manager
    $fullAccess = ($user->user_type === 'admin')
        || in_array($user->id, [180, 25606, 169], true)
        || $is41Manager;

    if (!$fullAccess) {
        if ($user->id == 178) {
            // Hatim sees his own + Hussain's
            $query->whereIn('manager_id', [$user->id, 26786]);
        } else {
            // Regular manager sees only own customers
            $query->where('manager_id', $user->id);
        }
    }

    // Search
    if ($sort_search) {
        $query->where(function ($q) use ($sort_search) {
            $q->where('party_code', 'like', "%$sort_search%")
              ->orWhere('phone', 'like', "%$sort_search%")
              ->orWhere('name', 'like', "%$sort_search%")
              ->orWhere('gstin', 'like', "%$sort_search%")
              ->orWhere('company_name', 'like', "%$sort_search%")
              ->orWhereHas('warehouse', fn($sub) => $sub->where('name', 'like', "%$sort_search%"))
              ->orWhereHas('manager', fn($sub) => $sub->where('name', 'like', "%$sort_search%"))
              ->orWhereHas('address_by_party_code', fn($sub) => $sub->where('city', 'like', "%$sort_search%"));
        });
    }

    // Filters
    // if ($filter) {
    //     $query->when($filter === 'approved', fn($q) => $q->where('banned', '0'))
    //           ->when($filter === 'un_approved', fn($q) => $q->where('banned', '1'));
    // }
    if ($filter) {
        $query->when($filter === 'approved', function ($q) {
            $q->where('banned', '0');
        })->when($filter === 'un_approved', function ($q) {
            $q->where('banned', '1')
              ->whereNull('manager_id')                // ✅ manager_id IS NULL
              ->where(function ($qq) {                 // ✅ discount NULL or empty
                  $qq->whereNull('discount')
                     ->orWhere('discount', '');
              })
              ->reorder()                              // clear any previous orderBys
              ->orderBy('users.created_at', 'asc');    // ✅ ascending by created_at
        });
    }
    if ($request->filled('warehouse')) {
        $query->whereIn('warehouse_id', $request->input('warehouse'));
    }
    if ($request->filled('manager')) {
        $query->whereIn('manager_id', $request->input('manager'));
    }
    if ($request->filled('city')) {
        $query->whereHas('address_by_party_code', fn($q) => $q->where('city', $request->input('city')));
    }
    if ($request->filled('discount')) {
        $query->where('discount', $request->input('discount'));
    }

    // Sorting (unchanged)
    $sort_by    = $request->input('sort_by', 'company_name');
    $sort_order = $request->input('sort_order', 'asc');
    $query->when($sort_by === 'manager_name',   fn($q) => $q->orderBy('manager.name', $sort_order))
          ->when($sort_by === 'warehouse_name', fn($q) => $q->orderBy('warehouse.name', $sort_order))
          ->when($sort_by === 'city',           fn($q) => $q->orderBy('address_by_party_code.city', $sort_order))
          ->orderBy($sort_by, $sort_order);

    // Paginate
    $users = $query->paginate(15);

    // Dropdown data
    $cities = DB::table('addresses')->distinct()->pluck('city');
    $discounts = User::whereNotNull('discount')->where('discount', '!=', '')->distinct()->pluck('discount');

    $actingAs41 = $this->isActingAs41Manager();

    return view('backend.customer.customers.index', compact(
        'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'cities', 'discounts', 'staffUsers','actingAs41'
    ));
}
public function index(Request $request)
{
    $sort_search = $request->input('search', null);
    $filter      = $request->input('filter', null);
    $user        = Auth::user();

    // Detect 41 Manager (any stored variant)
    $title       = strtolower((string) $user->user_title);
    $is41Manager = in_array($title, ['manager_41', '41 manager', '41_manager', '41manager'], true);

    // Fetch staffUsers: Managers with role_id 5
    $staffUsers = User::join('staff', 'users.id', '=', 'staff.user_id')
        ->where('staff.role_id', 5)
        ->where('users.banned', 0)
        ->select('users.*')
        ->get();

    $query = User::query()
        ->with([
            'warehouse:id,name',
            'manager:id,name',
            'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
            'total_due_amounts'
        ])
        ->whereIn('user_type', ['customer', 'warehouse'])
        ->whereNotNull('email_verified_at');

    // ✅ Full-access users: admin, head managers (IDs), and 41 Manager
    $fullAccess = ($user->user_type === 'admin')
        || in_array($user->id, [180, 25606, 169], true)
        || $is41Manager;

    if (!$fullAccess) {
        if ($user->id == 178) {
            // Hatim sees his own + Hussain's
            $query->whereIn('manager_id', [$user->id, 26786]);
        } else {
            // Regular manager sees only own customers
            $query->where('manager_id', $user->id);
        }
    }

    // Search
    if ($sort_search) {
        $query->where(function ($q) use ($sort_search) {
            $q->where('party_code', 'like', "%{$sort_search}%")
              ->orWhere('phone', 'like', "%{$sort_search}%")
              ->orWhere('name', 'like', "%{$sort_search}%")
              ->orWhere('gstin', 'like', "%{$sort_search}%")
              ->orWhere('company_name', 'like', "%{$sort_search}%")
              ->orWhereHas('warehouse', fn($sub) => $sub->where('name', 'like', "%{$sort_search}%"))
              ->orWhereHas('manager', fn($sub) => $sub->where('name', 'like', "%{$sort_search}%"))
              ->orWhereHas('address_by_party_code', fn($sub) => $sub->where('city', 'like', "%{$sort_search}%"));
        });
    }

    // Filters (NO orderBy here)
    if ($filter) {
        $query->when($filter === 'approved', function ($q) {
            $q->where('banned', '0');
        })->when($filter === 'un_approved', function ($q) {
            $q->where('banned', '1')
              ->whereNull('manager_id') // manager_id must be NULL
              ->where(function ($qq) {  // discount must be NULL or empty
                  $qq->whereNull('discount')
                     ->orWhere('discount', '');
              });
        });
    }

    // Exclude rejected users (banned = 2)
    $query->where('banned', '!=', 2);

    if ($request->filled('warehouse')) {
        $query->whereIn('warehouse_id', $request->input('warehouse'));
    }
    if ($request->filled('manager')) {
        $query->whereIn('manager_id', $request->input('manager'));
    }
    if ($request->filled('city')) {
        $query->whereHas('address_by_party_code', fn($q) => $q->where('city', $request->input('city')));
    }
    if ($request->filled('discount')) {
        $query->where('discount', $request->input('discount'));
    }

    // ---- Sorting (single place) ----
    // Default: un_approved => created_at asc, otherwise company_name asc
    $defaultSortBy = ($filter === 'un_approved') ? 'created_at' : 'company_name';
    $sort_by       = $request->input('sort_by', $defaultSortBy);
    $sort_order    = $request->input('sort_order', 'asc');

    // safe user columns that live on users table
    $safeUserCols = ['company_name', 'party_code', 'discount', 'phone', 'gstin', 'created_at'];

    $query
        // special "virtual" sorts that require relations (existing behaviour kept)
        ->when($sort_by === 'manager_name',   fn($q) => $q->orderBy('manager.name', $sort_order))
        ->when($sort_by === 'warehouse_name', fn($q) => $q->orderBy('warehouse.name', $sort_order))
        ->when($sort_by === 'city',           fn($q) => $q->orderBy('address_by_party_code.city', $sort_order))
        // fallback: users.* columns only
        ->when(in_array($sort_by, $safeUserCols), fn($q) => $q->orderBy("users.$sort_by", $sort_order));

    // Paginate
    $users = $query->paginate(15);

    // Dropdown data
    $cities    = DB::table('addresses')->distinct()->pluck('city');
    $discounts = User::whereNotNull('discount')->where('discount', '!=', '')->distinct()->pluck('discount');

    $actingAs41 = $this->isActingAs41Manager();

    return view('backend.customer.customers.index', compact(
        'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'cities', 'discounts', 'staffUsers', 'actingAs41'
    ));
}



public function backup_index(Request $request)
{
    $sort_search = $request->input('search', null);
    $filter = $request->input('filter', null);
    $user = Auth::user();

    // Fetch staffUsers: Managers with role_id 5
    // $staffUsers = User::whereHas('roles', function ($query) {
    //     $query->where('role_id', 5);
    // })->where('banned', 0)->select('id', 'name')->get();
    $staffUsers = User::join('staff', 'users.id', '=', 'staff.user_id')
            ->where('staff.role_id', 5)
            ->where('users.banned', 0)
            ->select('users.*')
            ->get();

    $query = User::query()
        ->with([
            'warehouse:id,name',
            'manager:id,name',
            'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
            'total_due_amounts' // ✅ Eager loading the new relationship
        ])
        ->whereIn('user_type', ['customer', 'warehouse'])
        ->whereNotNull('email_verified_at');

    // Apply user-specific logic
    if (in_array($user->id, ['180', '25606', '169'])) {
        // Head managers see all users
    } elseif ($user->id == 178) {
    // Hatim sees his own customers + Hussain's customers
    $query->whereIn('manager_id', [$user->id, 26786]);
    }elseif ($user->user_type != 'admin') {
        $query->where('manager_id', $user->id);
    }

    // Apply search filters
    if ($sort_search) {
        $query->where(function ($q) use ($sort_search) {
            $q->where('party_code', 'like', "%$sort_search%")
                ->orWhere('phone', 'like', "%$sort_search%")
                ->orWhere('name', 'like', "%$sort_search%")
                ->orWhere('gstin', 'like', "%$sort_search%")
                ->orWhere('company_name', 'like', "%$sort_search%")
                ->orWhereHas('warehouse', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                ->orWhereHas('manager', fn($subQuery) => $subQuery->where('name', 'like', "%$sort_search%"))
                ->orWhereHas('address_by_party_code', fn($subQuery) => $subQuery->where('city', 'like', "%$sort_search%"));
        });
    }

    if ($filter) {
        $query->when($filter === 'approved', fn($q) => $q->where('banned', '0'))
            ->when($filter === 'un_approved', fn($q) => $q->where('banned', '1'));
    }

    if ($request->filled('warehouse')) {
        $query->whereIn('warehouse_id', $request->input('warehouse'));
    }

    if ($request->filled('manager')) {
        $query->whereIn('manager_id', $request->input('manager'));
    }

    if ($request->filled('city')) {
        $query->whereHas('address_by_party_code', fn($q) => $q->where('city', $request->input('city')));
    }

    if ($request->filled('discount')) {
        $query->where('discount', $request->input('discount'));
    }

    // Dynamic sorting
    $sort_by = $request->input('sort_by', 'company_name');
    $sort_order = $request->input('sort_order', 'asc');
    $query->when($sort_by === 'manager_name', fn($q) => $q->orderBy('manager.name', $sort_order))
        ->when($sort_by === 'warehouse_name', fn($q) => $q->orderBy('warehouse.name', $sort_order))
        ->when($sort_by === 'city', fn($q) => $q->orderBy('address_by_party_code.city', $sort_order))
        ->orderBy($sort_by, $sort_order);

    // Paginate results
    $users = $query->paginate(15);

    // Retrieve distinct cities
    $cities = DB::table('addresses')->distinct()->pluck('city');

    // Retrieve distinct discounts
    $discounts = User::whereNotNull('discount')
        ->where('discount', '!=', '')
        ->distinct()
        ->pluck('discount');

    return view('backend.customer.customers.index', compact(
        'users', 'sort_search', 'filter', 'sort_by', 'sort_order', 'cities', 'discounts', 'staffUsers'
    ));
}




// public function get_cities_by_manager(Request $request)
// {
//     $managerId = $request->input('manager_id');

//     // Fetch distinct cities
//     $cities = Address::join('users', 'addresses.user_id', '=', 'users.id')
//         ->where('users.manager_id', $managerId)
//         ->whereNotNull('addresses.city') // Ensure city is not null
//         ->where('addresses.city', '!=', '') // Ensure city is not an empty string
        
//         ->distinct()
//         ->pluck('addresses.city');

//     return response()->json($cities);
// }

public function get_cities_by_manager(Request $request)
{
    // Convert comma-separated manager IDs to an array
    $managerIds = explode(',', $request->input('manager_id'));

    // Fetch distinct cities based on multiple managers
    $cities = Address::join('users', 'addresses.user_id', '=', 'users.id')
        ->whereIn('users.manager_id', $managerIds) // Filter by multiple manager IDs
        ->whereNotNull('addresses.city') // Ensure city is not null
        ->where('addresses.city', '!=', '') // Ensure city is not an empty string
        ->distinct()
        ->pluck('addresses.city');

    return response()->json($cities);
}


public function get_cities_by_manager_statement(Request $request)
{
    $managerId = $request->input('manager_id');

    // Fetch distinct cities
    $cities = Address::join('users', 'addresses.user_id', '=', 'users.id')
        ->where('users.manager_id', $managerId)
        ->whereNotNull('addresses.city') // Ensure city is not null
        ->where('addresses.city', '!=', '') // Ensure city is not an empty string
        ->where('addresses.due_amount', '>', 0)
        ->distinct()
        ->pluck('addresses.city');

    return response()->json($cities);
}

// public function get_manager_by_warehouse(Request $request)
// {
//     // Fetch managers based on the selected warehouse
//     $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
//     ->where('staff.role_id', 5)
//     ->where('users.warehouse_id', $request->warehouse_id)  // Apply condition on users table
//     ->select('users.*')
//     ->get();


//     return response()->json($managers); // Return managers as JSON response
// }


public function get_manager_by_warehouse(Request $request)
{
    // Convert comma-separated warehouse IDs to an array
    $warehouseIds = explode(',', $request->warehouse_id);

    // Fetch managers based on the selected warehouse(s)
    $managers = User::join('staff', 'users.id', '=', 'staff.user_id')
        ->where('staff.role_id', 5)
        ->whereIn('users.warehouse_id', $warehouseIds) // Apply condition for multiple warehouses
        ->select('users.*')
        ->get();

    return response()->json($managers); // Return managers as JSON response
}




  public function editFinancialInfo($customer_id)
  {
      // Retrieve the customer by ID
      $customer = User::findOrFail($customer_id);
      

      // Pass the customer to the view for editing financial info
      return view('backend.customer.customers.edit_financial_info', compact('customer'));
  }

  // public function updateFinancialInfo(Request $request, $customer_id)
  // {
  //     // Retrieve the customer by ID
  //     $user = User::findOrFail($customer_id);
  
  //     // Validate the request data
  //     $request->validate([
  //         'credit_limit' => 'required|numeric|min:0',
  //         'credit_days' => 'required|integer|min:0',
  //         'discount' => 'required|numeric|min:0|max:100',
  //     ]);
  
  //     // Update the customer financial information
  //     $user->credit_limit = $request->credit_limit;
  //     $user->credit_days = $request->credit_days;
  //     $user->discount = $request->discount;
  //     $user->save();
  
  //     // Redirect back to the previous page with a success message
  //     return redirect()->back()->with('success', 'Customer financial info updated successfully!');
  // }

  public function updateFinancialInfo(Request $request, $customer_id)
{
    // Retrieve the customer by ID
    $customer = User::findOrFail($customer_id);

    $messages = [
        'credit_limit.required' => 'Please enter the credit limit.',
        'credit_limit.numeric' => 'Credit limit must be a valid number.',
        'credit_limit.min' => 'Credit limit cannot be negative.',
        
        'credit_days.required' => 'Please specify the credit days.',
        'credit_days.integer' => 'Credit days must be an integer.',
        'credit_days.min' => 'Credit days cannot be negative.',
        
        'discount.required' => 'Discount is required.',
        'discount.numeric' => 'Discount must be a valid number.',
        'discount.min' => 'Discount cannot be less than 0%.',
        'discount.max' => 'Discount cannot be greater than 24%.',
    ];

    // Validate the request data (without manager_id validation)
    $request->validate([
        'credit_limit' => 'required|numeric|min:0',
        'credit_days' => 'required|integer|min:0',
        'discount' => 'required|numeric|min:0|max:24', // Validation for discount between 0 and 24
    ], $messages);

    // Update the customer financial information
    $customer->credit_limit = $request->credit_limit;
    $customer->credit_days = $request->credit_days;
    $customer->discount = $request->discount;

    // Update the manager_id if provided
    if ($request->has('staff_user_id')) {
        $customer->manager_id = $request->staff_user_id;
    }

    $customer->save();

    // Push the party_code to the Salezing API
    $result = [];
    $result['party_code'] = $customer->party_code;

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/client-push', $result);

    // Check if API call is successful (optional)
    if ($response->successful()) {
        return redirect()->back()->with('success', '😊 Customer financial info updated and pushed to Salezing API successfully!');
    } else {
        return redirect()->back()->with('error', '😭 Customer financial info updated but failed to push to Salezing API.');
    }
}



  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create() {
    $user = Auth::user();
    $managers = Staff::with('user:id,name')->where('role_id', 5)->get();
    $states = State::where('status', 1)->get();
    // echo "<pre>"; print_r($user);die;
    return view('backend.customer.customers.create', compact('user', 'managers','states'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request) {
    
    try {
        // Append country code to the mobile number
        $request->merge(['mobile' => '+91' . $request->mobile]);

        // Logging the mobile number for debugging
        \Log::info('Mobile: ' . $request->mobile);

        // Validation rules based on the presence of GSTIN
        if ($request->gstin) {
          $validatedData = $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'manager_id'   => 'required|exists:staff,user_id',
                'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
                'company_name' => 'required|string|max:255',
                'address'      => 'required|string',
                'address2'     => 'required|string',
                'pincode'      => 'required',
                'email'        => 'unique:users|email',
                'mobile'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
                'gstin'        => 'required|string|size:15|unique:users',
                'credit_days'        => 'nullable',
                'credit_limit'        => 'nullable',

            ]);
        } else {
            $request->validate([
                'warehouse_id' => 'required|exists:warehouses,id',
                'manager_id'   => 'required|exists:staff,user_id',
                'name'         => 'required|string|max:255|regex:/^[a-zA-Z\s]*$/',
                'company_name' => 'required|string|max:255',
                'address'      => 'required|string',
                'address2'     => 'required|string',
                'city'         => 'required|string',
                'email'        => 'required|unique:users|email',
                'mobile'       => 'required|unique:users,phone|regex:/^\+91[0-9]{10}$/',
                'aadhar_card'  => 'required|string|size:12',
                'pincode'      => 'required|numeric|digits:6',
                // 'pincode'      => 'required|numeric|digits:6|exists:pincodes,pincode',
                'credit_days'        => 'nullable',
                'credit_limit'        => 'nullable',
            ]);
        }
        // Debug user data before creation
        $user = $request->all();
        
        $pincode = Pincode::where('pincode', $request->pincode)->first();  
        //new code

        if (!$pincode) {
          
          // Retrieve the state by its ID from the form
          $state = State::find($request->state); // Assuming `state` in the request contains the state ID

          // Check if the state exists, if not, return an error (optional, based on your logic)
          if (!$state) {
              return redirect()->back()->withErrors(['state' => 'State not found']);
          }
         
          // Create a new city if it doesn't exist, using default cost and status
          
          $city = City::firstOrCreate(
              ['name' => $request->city],
              ['state_id' => $state->id, 'cost' => 0.00, 'status' => 1]
          );
         
        
          // Create a new pincode entry
          $pincode = Pincode::create([
              'pincode' => $request->pincode,
              'city' => $city->name,
              'state' => $state->name,
          ]);
         

          // $state = State::where('name', $pincode->state)->first();
         
      } else {
        
          // Retrieve the state based on the existing pincode's state
          $state = State::where('name', $pincode->state)->first();
      }
        //new code end

        \Log::info('User Data: ' . json_encode($user));
        // die;
        // Create user
        $user = $this->createUser($user);
    
     
        // If user creation is successful, modify the response status
        if ($user) {
            //SENDING WHATSAPP MESSAGE CODE START
            // **********************Message sending to Client************************ //
              $to = $user->phone;
              $templateData = [
                  'name' => 'utility_registration_template', 
                  'language' => 'en_US', 
                  'components' => [
                      [
                          'type' => 'body',
                          'parameters' => [
                              ['type' => 'text','text' => $user->company_name],
                              ['type' => 'text','text' => str_replace("+91", "", $user->phone)],
                              ['type' => 'text','text' => $user->verification_code]
                          ],
                      ]
                  ],
              ];

            $this->WhatsAppWebService=new WhatsAppWebService();
            $response = $this->WhatsAppWebService->sendTemplateMessage($to, $templateData);

                // **********************Message sending to sub Manager ************************ //
                $user_city = DB::table('users')
                  ->join('addresses', 'users.id', '=', 'addresses.user_id')
                  ->where('users.id', $user->id)
                  ->value('addresses.city');

                $getManager = User::where('id',$user->manager_id)->first();
                $to=$getManager->phone;

                $subManagerTemplate = [
                  'name' => 'sub_manager_notification', 
                  'language' => 'en_US', 
                  'components' => [
                      [
                          'type' => 'body',
                          'parameters' => [
                              ['type' => 'text','text' => $getManager->name],
                              ['type' => 'text','text' => $user->company_name],
                              ['type' => 'text','text' => $user->phone],
                              ['type' => 'text','text' => $user->gstin],
                              ['type' => 'text','text' => $user->state],
                              ['type' => 'text','text' => $user_city]
                          ],
                      ]
                  ],
              ];

              $this->WhatsAppWebService=new WhatsAppWebService();
              $response = $this->WhatsAppWebService->sendTemplateMessage($to, $subManagerTemplate);
            //SENDING WHATSAPP MESSAGE CODE END
            return redirect()->route('customer.create')->with('success', 'Customer created successfully!');
        } else {
            return redirect()->route('customer.create')->with('error', 'There was a problem creating the user.');
        }
               
        // return response()->json($response);
    } catch (ValidationException $e) {
        // Log the validation error messages
        \Log::error('Validation Errors: ', $e->errors());

        // Return validation errors as JSON
        return response()->json([
            'status' => 'Error',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        // Log any other exceptions
        \Log::error('An error occurred: ' . $e->getMessage());

        return response()->json([
            'status' => 'Error',
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
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
  public function edit($id) {
    $user = User::findOrFail($id);
    return view('backend.customer.customers.edit', compact('user'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id) {
    $user               = User::findOrFail($id);
    $shipper_allocation = [];
    $loop               = 0;
    foreach ($user->shipper_allocation as $shipper) {
      $shipper_allocation[]                      = $shipper;
      $shipper_allocation[$loop]['carrier_id']   = $request->carrier_id[$loop];
      $shipper_allocation[$loop]['carrier_name'] = $request->carrier_name[$loop];
      $loop++;
    }
    $user->shipper_allocation = $shipper_allocation;
    $user->save();
    flash(translate('Customer details updated successfully'))->success();
    return redirect()->route('customers.index');
  }

  public function approveOwnBrand(Request $request) {
    $user = User::where('id',$request->user_id)->first();
    $user->admin_approved_own_brand = 1;
    $user->profile_type = $request->profile_type;
    $user->save();
    flash(translate('Customer\'s OWN brand has approved.'))->success();
    return redirect()->route('customers.ownBrandCustomer');
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */

  public function reject($id)
  {
        $user = \App\Models\User::findOrFail($id);


        // allow reject only if currently unapproved (banned = 1)
        if ((int)$user->banned !== 1) {
            return back()->with('error', 'Only unapproved customers can be rejected.');
        }

        $user->banned = 2; // rejected
        $user->save();

        return back()->with('success', 'Customer rejected successfully.');
  }
  public function destroy($id) {



    $customer = User::findOrFail($id);
    $customer->customer_products()->delete();

    User::destroy($id);
    flash(translate('Customer has been deleted successfully'))->success();
    return redirect()->route('customers.index');
  }

  public function bulk_customer_delete(Request $request) {
    if ($request->id) {
      foreach ($request->id as $customer_id) {
        $customer = User::findOrFail($customer_id);
        $customer->customer_products()->delete();
        $this->destroy($customer_id);
      }
    }

    return 1;
  }

  // public function login($id) {

  //   $staff_id = Auth::user()->id;

  //   session()->put('staff_id', $staff_id);
  //   $ses_staff_id = session()->get('staff_id');


  //   $user = User::findOrFail(decrypt($id));
  //   session()->put('cl_name', $user->name);

  //   auth()->login($user, true);
  //   return redirect()->route('products.quickorder');
  // }


  public function login($id)
  {
        $staff_id = Auth::id();                  // current (manager/staff) id
        session()->put('staff_id', $staff_id);   // keep who is impersonating

        $user = User::findOrFail(decrypt($id));  // customer
        session()->put('cl_name', $user->name);

        auth()->login($user, true);              // switch to customer

        // 👇 re-put to be bulletproof after session regenerate
        session()->put('staff_id', $staff_id);
        session()->save();

        return redirect()->route('products.quickorder');
  }


  public function impexLoginFromAdmin($id) {
    $staff_id = Auth::user()->id;
    return redirect('https://impex.mazingbusiness.com/users/login-from-admin/'.base64_encode(decrypt($id)+11121984).'/'.base64_encode($staff_id+11121984).'/'.Auth::user()->name);
  }

  public function impexLogin(string $id) { 
    try {
      // echo decrypt($id);die;
      $user = User::findOrFail(base64_decode($id)-11121984);
      Auth::login($user);
      return redirect()->route('products.quickorder');
    } catch (\Exception $e) {
        // Handle the error (log it, debug it, etc.)
        echo 'Error: ' . $e->getMessage();
        die;
    }
  }
  public function switch_back_from_impex($staff_id) {
    $staff_id = base64_decode($staff_id)-11121984;  
    $user = User::findOrFail($staff_id);
    auth()->login($user, true);
    return redirect()->route('customers.ownBrandCustomer');
  }
  public function switch_back() {

    $staff_id = session()->get('staff_id');
    session()->forget('staff_id');

  
    $user = User::findOrFail($staff_id);
    auth()->login($user, true);
    return redirect()->route('customers.index');
  }

  protected function createUser(array $data) {
    $pincode      = Pincode::where('pincode', $data['pincode'])->first();
    $lastcustomer = User::where('user_type', 'customer')->where('warehouse_id', $data['warehouse_id'])->orderBy('id', 'desc')->orderBy('id', 'desc')->first();
    //echo "<pre>"; print_r($lastcustomer);
    if ($lastcustomer) {
      $party_code = 'OPEL0' . $data['warehouse_id'] . str_pad(((int) substr($lastcustomer->party_code, -5) + 1), 5, '0', STR_PAD_LEFT);
    } else {
      $party_code = 'OPEL0' . $data['warehouse_id'] . '00001';
    }
    
    $getManager = User::where('id',$data['manager_id'])->first();
    $data['password'] =  substr($getManager->name, 0, 1).substr($data['mobile'], -4);
    
    debug_to_console($party_code);
    debug_to_console(json_encode($pincode));
    debug_to_console(json_encode($data));
    
    if ($data['gstin']) {
      try {
          $user = User::create([
              'name'                   => $data['name'],
              'company_name'           => $data['company_name'],
              'phone'                  => $data['mobile'],
              'email'                  => $data['email'],
              'password'               => Hash::make($data['password']),
              'address'                => $data['address'],
              'gstin'                  => $data['gstin'],
              'aadhar_card'            => $data['aadhar_card'],
              'postal_code'            => $data['pincode'],
              'city'                   => $pincode->city,
              'state'                  => $pincode->state,
              'country'                => 'India',
              'warehouse_id'           => $data['warehouse_id'],
              'manager_id'             => $getManager->id,
              'party_code'             => $party_code,
              'virtual_account_number' => $party_code,
              'discount'               => $data['discount'],
              'user_type'              => 'customer',
              'banned'                 => false,
              'gst_data'               => $data['gst_data'],
              'verification_code'      => $data['password'],
              'email_verified_at'      => date("Y-m-d H:i:s"),
              'credit_limit'           => $data['credit_limit'],
              'credit_days'      => $data['credit_days'],
          ]);
               
          // Convert JSON to array          
          $gstDataArray = json_decode($data['gst_data'], true);          
          $gstDataArray = $gstDataArray['taxpayerInfo'];
          

          $pincode = Pincode::where('pincode', $gstDataArray['pradr']['addr']['pncd'])->first();
          $state = State::where('name', $pincode->state)->first();
          $cityData = City::where('name', $pincode->city)->first();          
          
          if(!isset($city->id)){
            $cityData = City::create([
              'name'               => $pincode->city,
              'state_id'           => $state->id
            ]);
            $city = $cityData->id;
          }else{
            $city = $cityData->id;
          }         
          
          // $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'] . ', ' .$gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst'];
          $cmp_address = $gstDataArray['pradr']['addr']['bnm']. ', '.$gstDataArray['pradr']['addr']['st'] . ', ' .$gstDataArray['pradr']['addr']['loc'];
          $cmp_address2 = $gstDataArray['pradr']['addr']['bno'] . ', ' .$gstDataArray['pradr']['addr']['dst']; 
          
          $address = Address::create([
              'user_id'=>$user->id,
              'acc_code'=>$party_code,
              'company_name'=> $gstDataArray['tradeNam'],
              'address' => trim($cmp_address,' ,'),
              'address_2' => trim($cmp_address2,' ,'),
              'gstin'=> $gstDataArray['gstin'],
              'country_id' => '101',
              'state_id'=>$state->id,
              'city_id'=> (int)$city,
              'city'=>$gstDataArray['pradr']['addr']['dst'],
              'longitude'=> $gstDataArray['pradr']['addr']['lt'],
              'latitude'=> $gstDataArray['pradr']['addr']['lg'],
              'postal_code'=> $gstDataArray['pradr']['addr']['pncd'],
              'phone'=> $data['mobile'],
              'set_default'=> 1
          ]);
          

          if(isset($gstDataArray['adadr']) AND count($gstDataArray['adadr']) > 0){
            $count = 10;
            foreach($gstDataArray['adadr'] as $key=>$value){                          
              $party_code =$user->party_code.$count;
              $address = $value['addr'];
              $pincode = Pincode::where('pincode', $address['pncd'])->first();
              $state = State::where('name', $pincode->state)->first();              
              $city = City::where('name', $pincode->city)->first();
              
              if(!isset($city->id)){
                $city_create = City::create([
                  'name'                   => $pincode->city,
                  'state_id'           => $state->id
                ]);
                $city = $city_create->id;
              }else{
                $city = $city->id;
              }
              // $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'] . ', ' .$address['bno'] . ', ' .$address['dst'];
              $cmp_address = $address['bnm']. ', '.$address['st'] . ', ' .$address['loc'];
              $cmp_address2 = $address['bno'] . ', ' .$address['dst'];
              Address::create([
                  'user_id'=>$user->id,
                  'acc_code'=>$party_code,
                  'company_name'=> $gstDataArray['tradeNam'],
                  'address' => trim($cmp_address,' ,'),
                  'address_2' => trim($cmp_address2,' ,'),
                  'gstin'=> $gstDataArray['gstin'],
                  'country_id' => '101',
                  'state_id'=>$state->id,
                  'city_id'=> (int)$city,
                  'city'=>$address['dst'],
                  'longitude'=> $address['lt'],
                  'latitude'=> $address['lg'],
                  'postal_code'=> $address['pncd'],
                  'phone'=> $data['mobile'],
                  'set_default'=> 0
              ]);
              $count++;
            }
          }
          
          \Log::info('Default Addresss: ' . json_encode($address));
          
      } catch (\Exception $e) {
          debug_to_console($e->getMessage());
          // Log::error($e->getMessage());
          // You can also log the stack trace
          // Log::error($e->getTraceAsString());
      }
    } else {
      $user = User::create([
        'name'                   => $data['name'],
        'company_name'           => $data['company_name'],
        'phone'                  => $data['mobile'],
        'email'                  => $data['email'],
        'password'               => Hash::make($data['password']),
        'address'                => $data['address'],
        'gstin'                  => null,
        'aadhar_card'            => $data['aadhar_card'],
        'postal_code'                => $data['pincode'],
        'city'                   => $pincode->city,
        'state'                  => $pincode->state,
        'country'                => 'India',
        'warehouse_id'           => $data['warehouse_id'],
        'party_code'             => $party_code,
        'virtual_account_number' => $party_code,
        'user_type'              => 'customer',
        'manager_id'             => $getManager->id,
        'banned'                 => false,
        'verification_code'      => $data['password'],
        'email_verified_at'      => date("Y-m-d H:i:s"),
        'discount'               => $data['discount'],
        'credit_limit'           => $data['credit_limit'],
        'credit_days'            => $data['credit_days'],

      ]);

      $pincode = Pincode::where('pincode', $data['pincode'])->first();
      $city = City::where('name', $pincode->city)->first();
      if(isset($city) AND $city->id != ""){
        $city = $city->id;
      }else{
        $city= 0;
      }
      $state = State::where('name', $pincode->state)->first();
      $cmp_address = $data['address'];
      $cmp_address2 = $data['address2'];
      $address = Address::create([
          'user_id'=>$user->id,
          'acc_code'=>$party_code,
          'company_name'=> $data['company_name'],
          'address' => $cmp_address,
          'address_2' => $cmp_address2,
          'gstin'=> null,
          'country_id' => '101',
          'state_id'=>$state->id,
          'city_id'=> $city,
          'city'=> $data['city'],
          'longitude'=> null,
          'latitude'=> null,
          'postal_code'=> $data['pincode'],
          'phone'=> $data['mobile'],
          'set_default'=> 1
      ]);

    }
    
    // Push User data to Salezing
    $result=array();
    $result['party_code']= $user->party_code;
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->post('https://mazingbusiness.com/api/v2/client-push', $result);

    // ✅ Call Zoho function directly
    $zoho = new ZohoController();
    $res= $zoho->createNewCustomerInZoho($user->party_code); // pass the party_code

    return $user;
  }

  public function ban($id, $manager_id = null) {
    $user = User::findOrFail(decrypt($id));
    if ($user->banned == 1) {
      $user->banned = 0;
      if ($manager_id) {
        $user->manager_id = $manager_id;
      }
      WhatsAppUtility::accountRegistered($user, $user->address);
      flash(translate('Customer Approved Successfully'))->success();
    } else {
      $user->banned = 1;
      flash(translate('Customer Banned Successfully'))->success();
    }
    $user->save();
    return back();
  }

  public function ownBrandCustomer(Request $request) {
    $sort_search = null;
    $filter = null;
    $user = Auth::user();
    
    if ($user) {
        $user_type = $user->user_type;
        $user_id = $user->id;
    }
    
    if( $user_id == '180' || $user_id == '25606' || $user_id == '169'){
        $users = User::whereIn('user_type', ['customer', 'impx_customer'])
                      ->whereNotNull('email_verified_at')
                      ->where('own_brand','1')
                      ->orderBy('created_at', 'desc');
    } else if($user_type != 'admin') {
        $users = User::whereIn('user_type', ['customer', 'impx_customer'])
                      ->whereNotNull('email_verified_at')
                      ->where('manager_id', $user_id)
                      ->where('own_brand','1')
                      ->orderBy('created_at', 'desc');
    } else {
        $users = User::whereIn('user_type', ['customer', 'impx_customer'])
                      ->whereNotNull('email_verified_at')
                      ->where('own_brand','1')
                      ->orderBy('created_at', 'desc');
    }
    
    if ($request->has('search')) {
        $sort_search = $request->search;
        $filter = $request->filter;

        if($filter == 'approved'){
            $users->where('admin_approved_own_brand', '1');
        } elseif($filter == 'un_approved'){
            $users->where('admin_approved_own_brand', '0');
        }
        
        $users->where(function ($q) use ($sort_search) {
            $q->where('party_code', 'like', '%' . $sort_search . '%')
              ->orWhere('phone', 'like', '%' . $sort_search . '%')
              ->orWhere('name', 'like', '%' . $sort_search . '%')
              ->orWhere('gstin', 'like', '%' . $sort_search . '%')  // Added search by GSTIN
              ->orWhere('company_name', 'like', '%' . $sort_search . '%')  // Added search by Company Name
              ->where('own_brand','1');
        });
    }
    
    $users = $users->paginate(15);
   
    return view('backend.customer.customers.ownCustomers', compact('users', 'sort_search','filter'));
  }
}
