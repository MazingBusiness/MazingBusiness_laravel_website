<?php

namespace App\Http\Controllers;

use App\Models\WarrantyClaim;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\WarrantyClaimDetail;

use App\Models\InvoiceOrder;
use App\Models\InvoiceOrderDetail;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;

use App\Models\SubOrder;
use App\Models\SubOrderDetail;
use App\Models\ResetProduct;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Barcode;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Upload;
use App\Models\Address;
use App\Models\PushNotification;
use App\Models\CronJob;
use App\Models\CronJobRunTime;
use Illuminate\Support\Facades\Auth;

// Services
use App\Services\WhatsAppWebService;

class NotificationAndCronJobController extends Controller
{
    public function getNotifications(Request $request)
    {
        try {
            $notifications = PushNotification::orderBy('id','DESC')->paginate(15);
            // Work on the current page’s collection
            $items = $notifications->getCollection();

            // Collect IDs by type (unique to avoid N+1)
            $productIds  = $items->where('type', 'product')->pluck('item_id')->unique()->filter()->values();
            $categoryIds = $items->where('type', 'category')->pluck('item_id')->unique()->filter()->values();
            $brandIds    = $items->where('type', 'brand')->pluck('item_id')->unique()->filter()->values();

            // Fetch names in bulk
            // If your Product uses translations, fetch the models and call getTranslation('name')
            $productNames  = $productIds->isNotEmpty()
                ? Product::whereIn('id', $productIds)->pluck('name', 'id')   // change to ->get()->keyBy('id') if you need getTranslation
                : collect();

            $categoryNames = $categoryIds->isNotEmpty()
                ? Category::whereIn('id', $categoryIds)->pluck('name', 'id')
                : collect();

            $brandNames    = $brandIds->isNotEmpty()
                ? Brand::whereIn('id', $brandIds)->pluck('name', 'id')
                : collect();

            // Attach derived names to each record (also provide a generic item_name)
            $notifications->setCollection(
                $items->map(function ($n) use ($productNames, $categoryNames, $brandNames) {
                    $n->product_name  = null;
                    $n->category_name = null;
                    $n->brand_name    = null;

                    if ($n->type === 'product') {
                        // If using translations: $n->product_name = optional($products->get($n->item_id))->getTranslation('name');
                        $n->product_name = isset($productNames[$n->item_id]) ? $productNames[$n->item_id] : null;
                    } elseif ($n->type === 'category') {
                        $n->category_name = isset($categoryNames[$n->item_id]) ? $categoryNames[$n->item_id] : null;
                    } elseif ($n->type === 'brand') {
                        $n->brand_name = isset($brandNames[$n->item_id]) ? $brandNames[$n->item_id] : null;
                    }

                    // Unified label you can use in Blade
                    $n->item_name = $n->product_name ?: $n->category_name ?: $n->brand_name;

                    return $n;
                })
            );

            return view('backend.notification_and_cronjob.getNotifications', compact('notifications'));
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
        // return view('backend.warranty.claims.pending', compact('claims'));
    }

    public function addNotifications(Request $request)
    {
        try {
            $branches = Warehouse::where('active','1')->get();
            return view('backend.notification_and_cronjob.notification', compact('branches'));
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
        // return view('backend.warranty.claims.pending', compact('claims'));
    }

    public function getManagersByWarehouse(Request $request)
    {
        $warehouseId = $request->query('warehouse_id');
        $q = User::join('staff', 'users.id', '=', 'staff.user_id')->where('staff.role_id', 5);
        if ($warehouseId && $warehouseId !== 'all') {
            $q->where('users.warehouse_id', $warehouseId);
        }
        $managers = $q->select('users.id', 'users.name')
                    ->orderBy('users.name')
                    ->get();

        return response()->json($managers);
    }

    public function categories()
    {
        return response()->json(
            Category::orderBy('name')->get(['id','name'])
        );
    }

    public function brands()
    {
        return response()->json(
            Brand::orderBy('name')->get(['id','name'])
        );
    }

    public function findByPartNo(Request $request)
    {
        $partNo = trim((string) $request->query('part_no', ''));

        if (strlen($partNo) < 7) {
            return response()->json(['ok' => false, 'message' => 'Min 7 chars'], 422);
        }
        $p = Product::where('part_no', $partNo)->where('current_stock','1')->first();
        if (!$p) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }
        // Name accessor in your model is often getTranslation('name', app()->getLocale()) — use raw if you prefer
        $name = method_exists($p, 'getTranslation') ? $p->getTranslation('name') : ($p->name ?? '');
        return response()->json([
            'ok'      => true,
            'id'      => (int) $p->id,
            'name'    => $name,
            'part_no' => $partNo,
        ]);
    }

    public function submitNotifications(Request $request){
        try {
            // echo "<pre>"; print_r($request->all()); die;
            $notificationInsertArray = array();
            $notificationInsertArray['title'] = $request->title;
            $notificationInsertArray['body'] = $request->body;
            $notificationInsertArray['type'] = $request->type;
            if($notificationInsertArray['type'] == "product"){
                $notificationInsertArray['action'] = "/product/product-details";
            }elseif($notificationInsertArray['type'] == "category"){
                $notificationInsertArray['action'] = "/product/cetrgory-products";
            }elseif($notificationInsertArray['type'] == "brand"){
                $notificationInsertArray['action'] = "/product/brand-products";
            }elseif($notificationInsertArray['type'] == "offers"){
                $notificationInsertArray['action'] = "/product/valid-offers";
            }elseif($notificationInsertArray['type'] == "cart"){
                $notificationInsertArray['action'] = "/cart";
            }
            $notificationInsertArray['image'] = $request->photos;
            $notificationInsertArray['branch'] = $request->branch;
            $notificationInsertArray['manager'] = $request->manager;
            if($request->branch != 'all' AND $request->manager == 'all'){
                $userIds = User::where('warehouse_id', $request->branch)->pluck('id')->toArray();
                $notificationInsertArray['user_id'] = implode(',',$userIds);
            }elseif($request->branch == 'all' AND $request->manager != 'all'){
                $userIds = User::where('manager_id', $request->manager)->pluck('id')->toArray();
                $notificationInsertArray['user_id'] = implode(',',$userIds);
            }elseif($request->branch != 'all' AND $request->manager != 'all'){
                $userIds = User::where('warehouse_id', $request->branch)->where('manager_id', $request->manager)->pluck('id')->toArray();
                $notificationInsertArray['user_id'] = implode(',',$userIds);

            }else{
                $userIds = User::where('user_type','customer')->distinct()->pluck('id')->toArray();
                $notificationInsertArray['user_id'] = implode(',',$userIds);
            }
            if(isset($request->product_id) AND $request->product_id != '')
            {
                $notificationInsertArray['item_id'] = $request->product_id;
            }
            if(isset($request->category_id) AND $request->category_id != '')
            {
                $notificationInsertArray['item_id'] = $request->category_id;
            }
            if(isset($request->brand_id) AND $request->brand_id != '')
            {
                $notificationInsertArray['item_id'] = $request->brand_id;
            }
            if(isset($request->photos) AND $request->photos != ""){
                $getPhoto = Upload::where('id',$request->photos)->first();
                $notificationInsertArray['image'] = "https://mazingbusiness.com/public/".$getPhoto->file_name;
            }
            if(isset($request->pop_up_message) AND $request->pop_up_message != ""){
                $notificationInsertArray['pop_up_message'] = $request->pop_up_message;
                $notificationInsertArray['show_on_screen'] = implode(',',$request->show_on_screen);
                $notificationInsertArray['to_date'] = $request->to_date;
            }
            $notificationInsertArray['date_time'] = $request->date_time;

            // Insert & redirect        
            $pn = PushNotification::create($notificationInsertArray);
            if(!isset($request->pop_up_message) OR $request->pop_up_message == ""){
                $cronInsert = array();
                $cronInsert['notification_id'] = $pn->id;
                $cronInsert['name'] = 'Push Notification:'.$request->title;
                $cronInsert['run_at'] = $request->date_time;
                CronJob::create($cronInsert);
            }
            return redirect()->route('getNotifications') ->with('success', "Notification created.");
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Failed to create notification: '.$e->getMessage());
        }       
    }

    public function deleteNotification(Request $request){
        CronJob::where('notification_id',$request->id)->delete();
        PushNotification::where('id',$request->id)->delete();
        return redirect()->route('getNotifications') ->with('error', "Notification deleted.");
    }


    public function getCronJobs(Request $request)
    {
        try {
            $cronJobRunTime = CronJobRunTime::orderBy('id','ASC')->first();
            $cronJobs = CronJob::where('status','0')->orderBy('run_at','ASC')->paginate(15);
            return view('backend.notification_and_cronjob.cronJobs', compact('cronJobs','cronJobRunTime'));
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
        // return view('backend.warranty.claims.pending', compact('claims'));
    }

}
