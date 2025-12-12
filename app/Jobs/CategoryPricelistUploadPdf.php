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
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Upload;
use App\Models\OfferProduct;
use App\Models\CategoryPricelistUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Services\WhatsAppWebService;
use App\Models\Category;
use Carbon\Carbon;

class CategoryPricelistUploadPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 100; // Laravel will retry the job 3 times automatically
    public $timeout = 0;  // Job will run until completion, no timeout

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
         ini_set('memory_limit', '-1'); // No memory limit
       ini_set('pcre.backtrack_limit', 10000000); // Increase to 10 million
        ini_set('pcre.recursion_limit', 10000000); // Increase recursion limit
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
    $batchSize = 1; // Process 10 users per job
    $processedUsers = 0; // Counter for processed users
    $groupId = uniqid('group_', true);

    do {
        $users = User::where('user_type', 'customer')
             ->where('id', '24198') 
            ->where('last_sent_category_updated', '0') // Fetch only unprocessed users
            ->whereRaw('JSON_LENGTH(categories) > 0')
            ->orderBy('id', 'desc')
            ->limit($batchSize)
            ->get();

        if ($users->isEmpty()) {
            break;
        }

        foreach ($users as $user) {
            if ($processedUsers >= 1) { 
                break 2; // Stop job after processing 10 users
            }

            $this->whatsAppWebService = new WhatsAppWebService();
            $categories = json_decode($user->categories, true) ?? [];
            $lastSentCategories = json_decode($user->last_sent_categories, true) ?? [];
            $allProducts = [];
            $addedCategories = [];
            $requiredProductCount = 200;

            if (!array_diff($categories, $lastSentCategories)) {
                User::where('id', $user->id)->update([
                    'last_sent_categories' => json_encode([]),
                    'last_sent_category_updated' => '1' // Mark as processed
                ]);
                continue; // Move to the next user
            }

            foreach ($categories as $categoryId) {
                if (in_array($categoryId, $lastSentCategories)) {
                    continue;
                }

                $offset = 0;
                do {
                    if (count($allProducts) >= $requiredProductCount) {
                        break 2; // Stop processing categories if already reached 200
                    }

                    $products = DB::select(DB::raw("
                        SELECT `products`.`id`, `part_no`, `brand_id`, 
                            `category_groups`.`name` as `group_name`, 
                            `categories`.`name` as `category_name`, `group_id`, 
                            `category_id`, `products`.`name`, `thumbnail_img`, 
                            `products`.`slug`, `min_qty`, `mrp`
                        FROM `products`
                        INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id`
                        INNER JOIN `category_groups` ON `categories`.`category_group_id` = `category_groups`.`id`
                        WHERE `products`.`category_id` = :categoryId
                        AND `published` = 1 
                        AND `current_stock` = 1 
                        AND `approved` = 1 
                        AND `num_of_sale` > 0
                        ORDER BY 
                            CASE WHEN `category_groups`.`id` = 1 THEN 0  
                                 WHEN `category_groups`.`id` = 8 THEN 1  
                                 ELSE 2 
                            END, 
                            `category_groups`.`name` ASC, 
                            `categories`.`name` ASC, 
                            CASE 
                                WHEN `products`.`name` LIKE '%opel%' THEN 0  -- Opel products priority
                                WHEN `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                                        SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                                    ) THEN 1
                                    ELSE 2 
                            END, 
                            CASE 
                                WHEN `products`.`name` LIKE '%opel%' THEN 0  
                                ELSE 1 
                            END, 
                            CAST(`products`.`mrp` AS UNSIGNED) ASC, 
                            `products`.`name` ASC
                        LIMIT 50 OFFSET :offset
                    "), ['categoryId' => $categoryId, 'offset' => $offset]);

                   

                    if (empty($products)) {
                        break;
                    }

                    $offset += 50;
                    $addedCategories[] = $categoryId;

                    foreach ($products as $product) {
                        if (count($allProducts) >= $requiredProductCount) {
                            break 2; 
                        }

                        $mrp = is_numeric($product->mrp) ? (float) $product->mrp : 0;
                        $discount = isset($user->discount) && is_numeric($user->discount) ? (float) $user->discount : 0;
                        $calculated_price = (100 - $discount) * $mrp / 100;
                        $product->price = format_price_in_rs(ceil_price($calculated_price));

                        $allProducts[] = $product;
                    }

                } while (count($products) === 50);
            }

            $allProducts = array_slice($allProducts, 0, $requiredProductCount);

            if (empty($allProducts)) {
                User::where('id', $user->id)->update([
                    'last_sent_categories' => json_encode([]),
                    'last_sent_category_updated' => '1' // Mark as processed
                ]);
                continue; // Move to next user
            }

            $lastSentCategories = array_values(array_unique(array_merge($lastSentCategories, $addedCategories)));
            User::where('id', $user->id)->update([
                'last_sent_categories' => json_encode($lastSentCategories),
                'last_sent_category_updated' => '1' // Mark as processed
            ]);

            $fileName = time() . '_top_categories_' . $user->id . '.pdf';
            $filePath = public_path('pdfs/' . $fileName);
            $mpdf = new Mpdf([
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

            // **Set PDF Header**
                $mpdf->SetHTMLHeader('
                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                                    <tr><td style="text-align: right;">
                                        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" />
                                    </td></tr>
                                </table>
                ');

                // **Set PDF Footer**
                $mpdf->SetHTMLFooter('
                    <table width="100%" border="0" cellpadding="0" cellspacing="0">
                        <tr bgcolor="#174e84">
                            <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                                Mazing Business Price List - ' . date('d-m-Y h:i:s A') . '
                            </td>
                        </tr>
                    </table>
                ');

            $html = '<table width="100%" border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th width="5%">SN</th>
                            <th width="10%">Part No</th>
                            <th  width="10%">Image</th>
                            <th width="30%">Item Name</th>
                            <th width="15%">Item Group</th>
                            <th width="15%">Category</th>
                            <th width="15%">Net Price</th>
                        </tr>
                    </thead>
                    <tbody>
                    ';

            $serialNumber = 1;
             foreach ($allProducts as $product) {
                    $thumbnail = Upload::find($product->thumbnail_img);
                    $photoUrl = $thumbnail ? asset('public/' . $thumbnail->file_name) : asset('public/assets/img/placeholder.jpg');

                        // Check for "No Credit Item" tag
                    $isNoCreditItem = Product::where('part_no', $product->part_no)->value('cash_and_carry_item') == 1;


                    // Check for "Fast Dispatch" tag
                    $isFastDispatch = DB::table('products_api')->where('part_no', $product->part_no)->exists();
                    $offerProduct = OfferProduct::where('part_no', $product->part_no)->exists();
                    $fastDispatchImage = asset('public/uploads/fast_dispatch.jpg');
                    $offerProductImage = asset('public/uploads/offers-icon.png');

                    $html .= '<tr style="height: 75px;">
                        <td width="5%" style="border: 2px solid #000; text-align: center;">' . $serialNumber++ . '</td>
                        <td width="10%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->part_no) . '</td>
                        <td width="7%" style="border: 2px solid #000; text-align: center;"><img src="' . htmlspecialchars($photoUrl) . '" width="80"></td>
                        <td width="32%" style="border: 2px solid #000; text-align: left;">
                            ' . htmlspecialchars($product->name) . '<br>';

                    // Add "No Credit Item" tag
                    if ($isNoCreditItem) {
                        $html .= '<span style="background:#dc3545;color:#fff;font-size:10px;border-radius:3px;padding:2px 5px;">
                                    No Credit Item
                                  </span><br>';
                    }

                    // Add "Fast Dispatch" tag
                    if ($isFastDispatch) {
                        $html .= '<img src="' . $fastDispatchImage . '" alt="Fast Dispatch" 
                                  style="width: 68px; height: 17px; margin-top: 5px; border-radius: 3px;">';
                    }

                    // Add "Offer" tag
                    if ($offerProduct) {
                        $html .= '<br><img src="' . $offerProductImage . '" alt="Fast Dispatch" 
                                  style="width: 68px; height: 20px; margin-top: 5px; border-radius: 3px;">';
                    }

                    $html .= '</td>
                        <td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->group_name) . '</td>
                        <td width="15%" style="border: 2px solid #000; text-align: center;">' . htmlspecialchars($product->category_name) . '</td>
                        <td width="15%" style="border: 2px solid #000; text-align: center;">' . $product->price . '</td>
                    </tr>';
                }

            $html .= '</tbody></table>';
            $mpdf->WriteHTML($html);
            $mpdf->Output($filePath, 'F');

            $pdfUrl = url('public/pdfs/' . $fileName);
            $media_id = $this->whatsAppWebService->uploadMedia($pdfUrl);

            //whatsapp code start
                $managerPhone = $this->getManagerPhone($user->manager_id);
                $templateData = [
                    'name' => 'utility_pricelist_pdf',
                    'language' => 'en_US', 
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['id' => $media_id['media_id'],'filename' => 'Price List Pdf']],
                               
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
                // Insert into wa_sales_queue
                DB::table('wa_sales_queue')->insert([
                    'group_id' => $groupId,
                    'callback_data' => $templateData['name'],'recipient_type' => 'individual',
                     'to_number' => '7044300330',
                    //'to_number' => $user->phone ?? '',
                    'type' => 'template','file_url' => $pdfUrl,
                    'file_name'=>'category_product_pricelist','content' => json_encode($templateData),
                    'status' => 'pending','created_at' => now(),'updated_at' => now()
                ]);
            //whatsapp code end

            CategoryPricelistUpload::insert([
                'user_id' => $user->id,
                'file_id' => $media_id['media_id'],
                'file_url' => $pdfUrl,
            ]);

            $processedUsers++; // Increment the counter
        }

        sleep(60);
    } while (!$users->isEmpty());
}



}
