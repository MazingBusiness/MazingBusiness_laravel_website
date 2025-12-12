<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use App\Models\WarrantyClaim;
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

// Services
use App\Services\WhatsAppWebService;

class CronJobController extends Controller
{
    public function startCronJob(Request $request)
    {
        try {
            $cronJobs = CronJob::where('run_at','<=',now())->where('status','0')->orderBy('run_at','ASC')->get();
            $cronJobRunTime = CronJobRunTime::orderBy('id','ASC')->first();
            if ($cronJobRunTime) {
                $cronJobRunTime->update([
                    'run_time' => now()->addMinutes(2), // saved as Y-m-d H:i:s
                ]);
            }
            foreach($cronJobs as $cronJob){
                if($cronJob->notification_id != NULL){
                    $endpoint  = 'https://mazingbusiness.com/mazing_business_react/api/user/send-push-notification-bulk';
                    $n = PushNotification::select('id','title','body','type','action','item_id','image','user_id')
                        ->find($cronJob->notification_id);
                    if (!$n) continue;

                    $userIds = collect(explode(',', (string) $n->user_id))
                        ->map(fn($v) => (int) trim($v))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (empty($userIds)) continue;

                    $actionSlug = Str::afterLast(ltrim((string) $n->action, '/'), '/');
                    $baseParams = [
                        'image' => $n->image, 
                        'title' => $n->title,
                        'body'  => $n->body,
                        'notification_id'  => $n->id,
                        'data'  => [
                            'type'   => $n->type,
                            'action' => $actionSlug,
                            'id'     => (string) $n->item_id,
                        ],
                    ];
                    foreach (array_chunk($userIds, 200) as $chunk) {
                        $params = $baseParams + ['user_ids' => $chunk];
                        $res = Http::timeout(30)->get($endpoint, $params);
                        if (!$res->ok()) {
                            \Log::error('Push bulk failed', [
                                'notification_id' => $n->id,
                                'status'          => $res->status(),
                                'body'            => $res->body(),
                            ]);
                        }
                    }
                }
                $cronJob->update(['status' => '1']);
            }
            $response = ['res' => true, 'msg' => 'Successfully send the notification.'];
            return $response;
            // echo "<pre>"; print_r($cronJobs); die;
            // 24181,24182,24183,24184,24185,24186,24187,24839,24910,24946,24967,24977,24978,24980,25009,25013,25020,25023,25032,25033,25182,25184,25185,25186,25289,25326,25328,25330,25340,25343,25346,25347,25352,25387,25390,25394,25418,25462,25463,25481,25487,25574,25575,25579,25592,25593,25594,25595,25607,25658,25712,25713,25718,26008,26010,26014,26015,26017,26099,26146,26161,26162,26232,26264,26265,26273,26275,26302,26307,26313,26323,26326,26368,26379,26408,26436,26448,26549,26603,26628,26651,26692,26700,26715,26742,26797,26799,26809,26866,26897,26903,26928,26953,26956,26988,26989,27009,27091,27213,27224,27273,27325,27330,27346,27384,27385,27386,27387,27388,27389,27390,27391,27393,27395,27396,27397,27398,27399,27400,27401,27402,27403,27404,27405,27406,27408,27409,27410,27411,27412,27415,27426,27436,27457,27537,27601,27653,27663
        } catch (\Throwable $e) {
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
        // return view('backend.warranty.claims.pending', compact('claims'));
    }

}
