<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mpdf\Mpdf;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Services\WhatsAppWebService;
use App\Models\Category;


use Carbon\Carbon;

class GenerateTopFiveCategoryPDFJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    public $timeout = 0;  // Job will run until completion, no timeout

    public function __construct()
    {
       ini_set('pcre.backtrack_limit', 10000000); // Increase to 10 million
        ini_set('pcre.recursion_limit', 10000000); // Increase recursion limit
    }

     private function getManagerPhone($managerId)
    {

      $managerData = User::where('id', $managerId)->select('phone')->first();
      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }

    public function handle()
    {
       
        //$allusers = User::where('user_type', 'customer')->get();
        $allusers = User::where('user_type', 'customer')->orderBy('id', 'desc')->get();
        //$customers = User::where('user_type', 'customer') ->orderBy('id', 'desc')->limit(5);
        //$allusers = User::where('id', 24198)->union($customers)->get();


        $groupId = uniqid('group_', true);

        foreach($allusers as $user){
            $user_id = $user->id;
            
            $this->whatsAppWebService = new WhatsAppWebService();

            // Fetch all order IDs for the given user
            $orderIds = Order::where('user_id', $user_id)->pluck('id');

             // Fetch all purchased categories based on total product price (without multiplication)
            $allCategories = OrderDetail::select('products.category_id', DB::raw('SUM(order_details.price) as total_spent'))
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->whereIn('order_details.order_id', $orderIds)
                ->groupBy('products.category_id')
                ->orderByDesc('total_spent')
                ->pluck('products.category_id')
                ->toArray();

            // Store all category IDs in the `users` table as JSON
            User::where('id', $user_id)->update([
                'categories' => json_encode($allCategories),
                'last_sent_category_updated' => '0'
            ]);

            // Only keep the top 5 categories for PDF generation
            $topCategories = array_slice($allCategories, 0, 5);

           

            continue;

            
        }

        // SendWhatsAppMessagesJob::dispatch($groupId);
        return response()->json(['msg'=>'success']);


        return response()->json(['user_id' => $user_id, 'pdf_url' => url('public/pdfs/' . $fileName)]);
    }
}
