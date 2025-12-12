<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


use Mpdf\Mpdf;
use App\Models\User;
use App\Models\NewArrival;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Upload;
use App\Models\OfferProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Services\WhatsAppWebService;
use App\Models\Category;
use Carbon\Carbon;
use App\Models\CategoryPricelistUpload;

class NewArrivalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 300; // Laravel will retry the job 3 times automatically
    public $timeout = 0;  // Job will run until completion, no timeout
    public $groupId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($groupId)
    {
        //
       ini_set('memory_limit', '-1'); // No memory limit
       ini_set('pcre.backtrack_limit', 10000000); // Increase to 10 million
       ini_set('pcre.recursion_limit', 10000000); // Increase recursion limit
       $this->groupId = $groupId;
    }

     private function getManagerPhone($managerId)
    {

      $managerData = User::where('id', $managerId)->select('phone')->first();
      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // $users = User::where('id', 24185)->get();
        $users = User::where('user_type', 'customer')
                 //->where('id','24185')
                 // ->whereRaw('JSON_LENGTH(categories) > 0')
                 ->orderBy('id', 'desc')
                 ->get();
                 // echo "<pre>";
                 // print_r($users->toArray());
                 // die();
        $type = 'net';
        $this->whatsAppWebService = new WhatsAppWebService();
        //$groupId = uniqid('group_', true);

        foreach($users as $user){
            

            $all_categories=json_decode($user->categories);
            if (empty($all_categories) || !is_array($all_categories)) {
                $all_categories = []; // Ensure it is an array
            }

             // Only keep the top 5 categories for PDF generation
            //$topCategories = array_slice($all_categories, 0, 15); // As of now no uses
            // Convert categories array into a comma-separated string for SQL IN clause
            $categoryFilter = implode(',', array_map('intval', $all_categories));
            // Fetch created products within the last 10 days with category & group name

            if (empty($categoryFilter)) {
                $categoryFilter = 'NULL'; // Avoid syntax errors
            }
          

            $discount = $user->discount == "" ? 0 : $user->discount;

           //****** Retrieving Recently Added, Approved, and In-Stock Products from Specific Categories, Sorted by Priority and Price *******//

            $products = DB::select(DB::raw("
                SELECT 
                    `products`.`id`, 
                    `products`.`part_no`, 
                    `products`.`brand_id`, 
                    `category_groups`.`name` as `group_name`, 
                    `categories`.`name` as `category_name`, 
                    `products`.`group_id`, 
                    `products`.`category_id`, 
                    `products`.`name`, 
                    `products`.`thumbnail_img`, 
                    `products`.`slug`, 
                    `products`.`min_qty`, 
                    `products`.`mrp`,
                    `products`.`updated_at`,
                    `products`.`created_at`
                FROM `products`
                INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
                INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
                WHERE `products`.`created_at` >= DATE_SUB(NOW(), INTERVAL 10 DAY) -- ✅ Last 10 days only
                AND `products`.`published` = 1 
                AND `products`.`current_stock` = 1 
                AND `products`.`approved` = 1
                AND `products`.`mrp` > 0 -- ✅ Ensure MRP is greater than 0
                AND `products`.`category_id` IN ($categoryFilter) -- ✅ Only Top 5 Categories
                 ORDER BY 
                    CASE 
                        WHEN `category_groups`.`id` = 1 THEN 0  -- Power Tools group first
                        WHEN `category_groups`.`id` = 8 THEN 1  -- Cordless Tools second
                        ELSE 2 
                    END, 
                    `category_groups`.`name` ASC, 
                    `categories`.`name` ASC, 
                    CASE 
                        WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products first
                        WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                            SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                        ) THEN 1
                        ELSE 2 
                    END, 
                    CAST(`products`.`mrp` AS UNSIGNED) ASC, 
                    `products`.`name` ASC,
                    `products`.`created_at` DESC -- ✅ First, sort by latest created_at
                 LIMIT 1000
            "));
            

            // ⚡ Check if no products found, then get latest 200 products
            if (!is_array($products) || count($products) == 0) {
                $products = $this->getLatestProducts(200);// passing limit
            }
            // ⚡ Still no products? Skip user
            if (!is_array($products) || count($products) == 0) {
                continue;
            }

            $fileName = 'new_arrival_products_' . round(microtime(true) * 1000000) . '.pdf';
            $filePath = public_path('pdfs/' . $fileName);

            // *********** PDF Generation Start *********************//
            try {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'default_font_size' => 10,
                    'default_font' => 'Arial',
                    'margin_left' => 10,
                    'margin_right' => 10,
                    'margin_top' => 40,
                    'margin_bottom' => 20,
                    'margin_header' => 10,
                    'margin_footer' => 10,
                ]);

              // PDF Header
                $mpdf->SetHTMLHeader('
                    <table width="100%" border="0">
                        <tr>
                            <td style="text-align: right;">
                                <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" />
                            </td>
                        </tr>
                    </table>
                ');

                // PDF Footer
                $mpdf->SetHTMLFooter('
                    <table width="100%" border="0">
                        <tr bgcolor="#174e84">
                            <td style="height: 40px; text-align: center; color: #fff; font-weight: bold;">
                                Mazing Business - New Arrivals as of ' . date('d-m-Y h:i:s A') . '
                            </td>
                        </tr>
                    </table>
                ');

                // NEW ARRIVAL Title with Styling
                $html = '<div style="text-align: center; font-size: 22px; font-weight: bold; color: black; margin-bottom: 10px; text-transform: uppercase;">
                            NEW ARRIVAL
                        </div>';

                // Table Header
                $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
                    <thead>
                        <tr style="background-color:#f1f1f1;">
                            <th width="5%">SN</th>
                            <th width="10%">PART NO</th>
                            <th width="10%">IMAGE</th>
                            <th width="30%">ITEM NAME</th>
                            <th width="15%">ITEM GROUP</th>
                            <th width="15%">CATEGORY</th>
                            <th width="15%">NET PRICE</th>
                        </tr>
                    </thead>
                    <tbody>';

                $serialNumber = 1;
                
                foreach ($products as $product) {
                    
                    $thumbnail = $product->thumbnail_img 
                        ? uploaded_asset($product->thumbnail_img) 
                        : asset('uploads/placeholder.jpg');

                    // Check for No Credit Item
                    $isNoCreditItem = Product::where('part_no', $product->part_no)->value('cash_and_carry_item') == 1;
                    $noCreditBadge = $isNoCreditItem
                        ? '<br/><span style="background:#dc3545;color:#fff;font-size:10px;border-radius:3px;padding:2px 5px;">No Credit Item</span>'
                        : '';

                    // Check for Fast Dispatch
                    $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->exists();
                    $fastDispatchImage = public_path('uploads/fast_dispatch.jpg');
                    $fastDispatchBadge = $isFastDispatch
                        ? '<br/><img src="' . $fastDispatchImage . '" alt="Fast Delivery" style="width: 80px; height: 20px; margin-top: 5px;">'
                        : '';
                    $offerProduct = OfferProduct::where('part_no', $product->part_no)->exists();
                    $offerProductImage = asset('public/uploads/offers-icon.png');

                    // Format price
                    // $netPrice = ceil_price((100-$discount) * $product->mrp / 100);
                    // $list_price = ceil_price($netPrice * 131.6 / 100);
                    // $price = $type == 'net' ? format_price_in_rs($netPrice) : format_price_in_rs($list_price);

                    $mrp = is_numeric($product->mrp) ? (float) $product->mrp : 0;
                    $discount = is_numeric($discount) ? (float) $discount : 0;
                    $netPrice = (100 - $discount) * $mrp / 100;
                    $netPrice = is_numeric($netPrice) ? ceil_price($netPrice) : 0;
                    $list_price = $netPrice * 131.6 / 100;
                    $list_price = is_numeric($list_price) ? ceil_price($list_price) : 0;
                    $price = ($type == 'net') ? format_price_in_rs($netPrice) : format_price_in_rs($list_price);

                    // Append rows to table
                   $html .= '<tr>
                        <td width="5%" style="text-align: center;">' . $serialNumber++ . '</td>
                        <td width="10%" style="text-align: center;">
                            ' . htmlspecialchars($product->part_no) . $noCreditBadge . '
                        </td>
                        <td width="10%" style="text-align: center;">
                            <img src="' . htmlspecialchars($thumbnail) . '" style="width: 60px; height: 60px;">
                        </td>
                        <td width="30%" style="text-align: left; font-weight: bold;">
                            <a href="' . route('product', ['slug' => $product->slug]) . '" target="_blank">' . htmlspecialchars($product->name) . '</a>' . $fastDispatchBadge;

                        if ($offerProduct) {
                            $html .= '<br><img src="' . $offerProductImage . '" alt="Offer Product"
                                      style="width: 68px; height: 20px; margin-top: 5px; border-radius: 3px;">';
                        }

                        $html .= '</td>
                                    <td width="15%" style="text-align: center;">' . htmlspecialchars($product->group_name ?? 'N/A') . '</td>
                                    <td width="15%" style="text-align: center;">' . htmlspecialchars($product->category_name ?? 'N/A') . '</td>
                                    <td width="15%" style="text-align: center;">' . $price . '</td>
                                </tr>';

                }


                $html .= '</tbody></table>';

                // Write content to PDF
                $mpdf->WriteHTML($html);
                $mpdf->Output($filePath, 'F');
            // ***********PDF Generation End *********************//

            // ***************************whatsapp code start ***************  

                 $pdfUrl=url('public/pdfs/' . $fileName);
                 $document_file_name=basename($pdfUrl);
                 $media_id = $this->whatsAppWebService->uploadMedia($pdfUrl);
                 $managerPhone=$this->getManagerPhone($user->manager_id);

                 //$media=$this->whatsAppWebService->uploadMedia($pdfUrl);

                $templateData = [
                    'name' => 'new_arrival_products',
                    'language' => 'en_US', 
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['filename' => $document_file_name,'id' => $media_id['media_id']]],
                               
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $user->name],  // Customer Name
                                ['type' => 'text', 'text' => $managerPhone]  // Manager Phone
                            ],
                        ],
                       
                    ]
                ];

                DB::table('wa_sales_queue')->insert([
                    'group_id' => $this->groupId,'callback_data' => $templateData['name'],
                    'recipient_type' => 'individual',
                     'to_number' => $user->phone ?? '',
                     //'to_number' => '7044300330',
                     'file_name' => 'new_arrival_product',
                     'type' => 'template','file_url' => $pdfUrl,
                    'content' => json_encode($templateData),'status' => 'pending',
                    'created_at' => now(),'updated_at' => now()
                ]);

                //For tracking 
                NewArrival::insert([
                    'user_id' => $user->id,
                    'file_id' => $media_id['media_id'],
                    'file_url' => $pdfUrl,
                ]);

                 // Delete the PDF file after queuing the message
                if (file_exists($filePath)) {
                    unlink($filePath); // This will delete the file
                }
            // **********************************whatsapp code end *****************************

            } catch (\Exception $e) {
                return response()->json(['error' => 'PDF generation failed', 'message' => $e->getMessage()], 500);
            }
            
         }
         SendWhatsAppMessagesJob::dispatch($this->groupId);
        //SendWhatsAppMessagesJob::dispatch($groupId);
        // return response()->json([
        //     'success' => true,
        //     'message' => 'PDF generated & sent to whatsapp successfully',
        //     // 'pdf_url' => url('public/pdfs/' . $fileName)
        // ]);
        // return response()->download($filePath);
    }



    private function getLatestProducts($limit = 200)
    {
        return DB::select(DB::raw("
            SELECT 
                `products`.`id`, 
                `products`.`part_no`, 
                `products`.`brand_id`, 
                `category_groups`.`name` as `group_name`, 
                `categories`.`name` as `category_name`, 
                `products`.`group_id`, 
                `products`.`category_id`, 
                `products`.`name`, 
                `products`.`thumbnail_img`, 
                `products`.`slug`, 
                `products`.`min_qty`, 
                `products`.`mrp`,
                `products`.`updated_at`,
                `products`.`created_at`
            FROM `products`
            INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
            INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
            WHERE `products`.`created_at` >= DATE_SUB(NOW(), INTERVAL 10 DAY) -- ✅ Last 10 days only
            AND `products`.`published` = 1 
            AND `products`.`current_stock` = 1 
            AND `products`.`approved` = 1
            AND `products`.`mrp` > 0 -- ✅ Ensure MRP is greater than 0
            ORDER BY 
                    CASE 
                        WHEN `category_groups`.`id` = 1 THEN 0  -- Power Tools group first
                        WHEN `category_groups`.`id` = 8 THEN 1  -- Cordless Tools second
                        ELSE 2 
                    END, 
                    `category_groups`.`name` ASC, 
                    `categories`.`name` ASC, 
                    CASE 
                        WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products first
                        WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                            SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                        ) THEN 1
                        ELSE 2 
                    END, 
                    CAST(`products`.`mrp` AS UNSIGNED) ASC, 
                    `products`.`name` ASC,
                    `products`.`created_at` DESC -- ✅ First, sort by latest created_at
             LIMIT $limit
        "));
    }
}
