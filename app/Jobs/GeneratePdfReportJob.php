<?php

namespace App\Jobs;

use Mpdf\Mpdf;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Upload;
use App\Models\PdfReport;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use App\Services\PdfContentService;

class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;   

    protected $data;
    protected $filename;
    protected $whatsAppWebService;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $filename)
    {
        $this->data = $data;
        $this->filename = $filename;
        $this->whatsAppWebService = new WhatsAppWebService();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {        
        $client_name = $this->data['client_name'];
        $search      = $this->data['search'];
        $group       = $this->data['group'];
        $category    = $this->data['category'];
        $brand       = $this->data['brand'];
        $user_id     = $this->data['user_id'];
        $type        = $this->data['type'];
        $inhouse     = $this->data['inhouse'];
        
        $user        = User::where('id', $user_id)->first();
        $client_name = $user->name;
        // $discount = $user->discount;
        $discount    = $user->discount == "" ? 0 : $user->discount;

        /**
         * 🔹 1) PDF CONTENT / POSTER BLOCK LOAD KARO
         * pdf_type ko apne hisaab se set karo (e.g. 'price_list' ya 'invoice')
         */
        $pdfContentService = new PdfContentService();
        $pdfContentBlock   = $pdfContentService->buildBlockForType('price_list'); // <<-- yahan apna pdf_type

        $posterTopHtml    = '';
        $posterBottomHtml = '';

        if (!empty($pdfContentBlock)) {
            // Invoice wala Blade partial use kar rahe hain
            $rendered  = view('backend.sales.partials.pdf_content_block', [
                'block' => $pdfContentBlock,
            ])->render();

            $placement = $pdfContentBlock['placement'] ?? 'last';

            if ($placement === 'first') {
                // Sirf 1st page, header ke neeche
                $posterTopHtml = $rendered;
            } elseif ($placement === 'last') {
                // Sirf last page, footer ke upar
                $posterBottomHtml = $rendered;
            }
        }

        // ---------- 2) HEADER & FOOTER DEFINE ----------
        $header = '<table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="text-align: right; position: relative;">
                        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display: block;" />
                    </td>
                </tr>
            </table>';
        
        $footer = '<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left" class="col">
                <tbody>
                    <tr>
                        <td style="height: 55px; text-align: center; color: #174e84; font-family: Arial; font-weight: bold;">
                            All prices are NET Prices, and all the products in the PDF are available.
                        </td>
                    </tr>
                    <tr bgcolor="#174e84">
                        <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                            Mazing Business Price List for - '.$client_name.' ('.date('d-m-Y h:i:s A').')
                        </td>
                    </tr>
                </tbody>
            </table>';
        
        $htmlHeader = '
                <table width="100%" style="border-collapse: collapse; position: relative; top: 50px; left: 32px; margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">SN</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">PART NO</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">IMAGE</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">ITEM</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">GROUP</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">CATEGORY</th>
                            <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">NET PRICE</th>
                        </tr>
                    </thead>
                <tbody>';

        // ---------- 3) MPDF INIT + 1st PAGE POSTER ----------
        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->setHTMLHeader($header);
        $mpdf->SetMargins(0, 10, 40, 10);
        $mpdf->setHTMLFooter($footer);
        $mpdf->AddPageByArray(['size' => 'A4']);

        // ⭐ 1st page: header already auto-draw ho chuka hoga. Ab header ke neeche poster:
        if ($posterTopHtml !== '') {
            $mpdf->WriteHTML($posterTopHtml);
        }

        // Ab table ka header likho
        $mpdf->WriteHTML($htmlHeader);

        // ---------- 4) DATA FETCH ----------
        $inhouseCondition = "";
        if ($inhouse === '1') {
            // Include only inhouse products
            $inhouseCondition = "AND `products`.`part_no` COLLATE utf8mb3_general_ci IN (
                                    SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                                )";
        } elseif ($inhouse === '2') {
            // Exclude inhouse products
            $inhouseCondition = "AND `products`.`part_no` COLLATE utf8mb3_general_ci NOT IN (
                                    SELECT `part_no` COLLATE utf8mb3_general_ci FROM `products_api`
                                )";
        }

        $results = DB::select(DB::raw("
            SELECT 
                `products`.`id`, 
                `part_no`, 
                `brand_id`, 
                `category_groups`.`name` as `group_name`, 
                `categories`.`name` as `category_name`, 
                `group_id`, 
                `category_id`, 
                `products`.`name`, 
                `thumbnail_img`, 
                `products`.`slug`, 
                `min_qty`, 
                `mrp`
            FROM 
                `products`
            INNER JOIN 
                `categories` 
                ON `products`.`category_id` = `categories`.`id`
            INNER JOIN 
                `category_groups` 
                ON `categories`.`category_group_id` = `category_groups`.`id`
            WHERE 
                `products`.`name` LIKE '%$search%'
                $group $category $brand 
                AND `published` = 1 
                AND `current_stock` > 0
                AND `approved` = 1
                $inhouseCondition
            ORDER BY 
                CASE 
                    WHEN `category_groups`.`id` = 1 THEN 0  -- Power Tools group priority
                    WHEN `category_groups`.`id` = 8 THEN 1  -- Cordless Tools group priority
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
        "));

        $resultsArray = json_decode(json_encode($results), true);

        $count = 1;

        foreach ($resultsArray as $key => $row) {
            $thumbnail = Upload::find($row['thumbnail_img']);
            $photo_url = $thumbnail ? env('UPLOADS_BASE_URL') . '/' . $thumbnail->file_name : env('UPLOADS_BASE_URL') . '/assets/img/placeholder.jpg';

            $net_price  = ceil_price((100 - $discount) * $row['mrp'] / 100);
            $list_price = ceil_price($net_price * 131.6 / 100);
            $price      = $type == 'net' ? format_price_in_rs($net_price) : format_price_in_rs($list_price);

            // Fetch the necessary data beforehand
            $cashAndCarryItem = DB::table('products')->where('part_no', $row['part_no'])->value('cash_and_carry_item');
            $isNoCreditItem   = ($cashAndCarryItem == 1 && Auth::check() && Auth::user()->credit_days > 0);
            $isFastDispatch   = DB::table('products_api')->where('part_no', $row['part_no'])->where('closing_stock', '>', 0)->distinct()->exists();

            // Generate the HTML row
            $htmlRow = '
                <tr style="height: 75px;">
                    <td width="5%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$count.'</td>
                    <td width="10%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">
                        '.htmlspecialchars($row['part_no']).'
                        '.($isNoCreditItem ? 
                            '<br/><span style="display: inline-block; margin-top: 5px; padding: 2px 5px; background-color: #dc3545; color: #fff; font-size: 10px; border-radius: 3px;">No Credit Item</span>' 
                            : '').'
                    </td>
                    <td width="7%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">
                        <img src="'.$photo_url.'" alt="" style="width: 80px;">
                    </td>
                    <td width="32%" style="border: 2px solid #000; text-align: left; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.2;">
                        <a href="'.route('product', ['slug' => htmlspecialchars($row['slug'])]).'" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit;">
                            '.htmlspecialchars($row['name']).'
                        </a>
                        '.($isFastDispatch ? 
                            '<div style="margin-top: 5px;">
                                <img src="'.public_path('uploads/fast_dispatch.jpg').'" alt="Fast Delivery" style="width: 68px; height: 17px; border-radius: 3px;">
                            </div>' 
                            : '').'
                    </td>
                    <td width="15%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.htmlspecialchars($row['group_name']).'</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.htmlspecialchars($row['category_name']).'</td>
                    <td width="15%" style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 12px;">  '.$price.'</td>
                </tr>';

            // Write each row separately
            $mpdf->WriteHTML($htmlRow);
            $count++;
        }      

        // ---------- 5) TABLE CLOSE + LAST PAGE POSTER ----------
        $htmlFooter = '</tbody></table>';
        $mpdf->WriteHTML($htmlFooter);

        // Sirf jab placement_type = 'last' hoga tab yahan print hoga
        if ($posterBottomHtml !== '') {
            $mpdf->WriteHTML($posterBottomHtml);
        }

        // ---------- 6) SAVE & WHATSAPP ----------
        $pdfPath = public_path('pdfs/');
        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 0755, true);
        }
        $output = $mpdf->Output($pdfPath . '/' . $this->filename, 'F');
        
        $pdfPath = 'https://mazingbusiness.com/public/pdfs/'.$this->filename;

        PdfReport::where('filename', $this->filename)->update([
            'path'   => $pdfPath,
            'status' => 'completed'
        ]);

        $this->sendMessage($user_id, $pdfPath);
    }


    public function sendMessage($user_id, $pdfPath)
    {
        $user= User::where('id', $user_id)->first();
        $to = str_replace('+91','',$user->phone);
        // $to = '9804722029';
        // Define the URL and file name for the document
        $customer_name = $user->company_name;
        $getManagerDetails = User::where('id', $user->manager_id)->first();
        $manager_phone = str_replace('+91','',$getManagerDetails->phone);
        $pathParts = explode('/', $pdfPath);
        $button_variable = array_pop($pathParts);
        // $image_url = 'https://mazingbusiness.com/public/assets/img/istockphoto-1263032734-612x612.jpg';
        $image_url = 'https://mazingbusiness.com/public/assets/img/1000105696.jpg';

        $media = $this->whatsAppWebService->uploadMedia($pdfPath);
        if ($this->checkLinkExistence($pdfPath)) {
            // Define the template data with document component
            $templateData = [
                'name' => 'utility_quickorder_pricelist_pdf', // Don't change this template name utility_items
                'language' => 'en_US', 
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            // ['type' => 'image', 'image' => ['link' => $image_url]],
                             ['type' => 'document', 'document' => ['filename' => 'Price List','id' => $media['media_id']]],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $customer_name],
                            ['type' => 'text', 'text' => $manager_phone],
                        ],
                    ],
                    // [
                    //     'type' => 'button',
                    //     'sub_type' => 'url',
                    //     'index' => '0',
                    //     'parameters' => [
                    //         [
                    //             "type" => "text",
                    //             "text" => $button_variable // Replace $button_text with the actual Parameter for the button.
                    //         ],
                    //     ],
                    // ],
                ],
            ];
        }else{
            return view('errors.link_expire');
        }

        // Send the template message using the WhatsApp web service
        $response = $this->whatsAppWebService->sendTemplateMessage($to, $templateData); // Send msg to customer
        $response = $this->whatsAppWebService->sendTemplateMessage('9709555576', $templateData); // Send msg to Amir
        $response = $this->whatsAppWebService->sendTemplateMessage('9894753728', $templateData); // Send msg to Burhanuddin        
        $response = $this->whatsAppWebService->sendTemplateMessage($manager_phone, $templateData); // Send msg to Customer's manager
        return response()->json($response);
    }

    public function checkLinkExistence($url)
    {
        // Use get_headers to check if the remote file exists
        $headers = @get_headers($url);

        // Check if the response code is 200 OK
        return $headers && strpos($headers[0], '200') !== false;
    }

    public function handle_backup()
    {        
        $client_name = $this->data['client_name'];
        $search = $this->data['search'];
        $group = $this->data['group'];
        $category = $this->data['category'];
        $brand = $this->data['brand'];
        $user_id = $this->data['user_id'];
        $type = $this->data['type'];
        // die;
        $user= User::where('id', $user_id)->first();
        $client_name = $user->name;
        // $discount = $user->discount;
        $discount = $user->discount == "" ? 0 : $user->discount;
        // Prepare the HTML content
        $htmlContent = '
            <table width="100%" style="border-collapse: collapse; position: relative; top: 50px; left: 32px; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">SN</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">PART NO</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">IMAGE</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">ITEM</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">GROUP</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">CATEGORY</th>
                        <th style="border: 2px solid #000; text-align: center; font-family: Arial, Helvetica, sans-serif; color: #174e84; padding-top: 7px; padding-bottom: 7px;">NET PRICE</th>
                    </tr>
                </thead>
                <tbody>';

        // $results = DB::select(DB::raw("SELECT `products`.`id`, `part_no`, `brand_id`, `category_groups`.`name` as `group_name`, `categories`.`name` as `category_name`, `group_id`, `category_id`, `products`.`name`, `thumbnail_img`, `products`.`slug`, `min_qty`, `mrp` from `products` INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id` INNER JOIN `category_groups` on `categories`.`category_group_id` = `category_groups`.`id` WHERE products.name LIKE '%$search%' $group $category $brand AND `published` = 1 AND `current_stock` = 1 and `approved` = 1 order by `category_groups`.`name` asc, `categories`.`name` asc, CASE WHEN products.name LIKE '%opel%' THEN 0 ELSE 1 END, CAST(products.mrp AS UNSIGNED) ASC"));
        
        $results = DB::select(DB::raw("SELECT `products`.`id`, `part_no`, `brand_id`, `category_groups`.`name` as `group_name`, `categories`.`name` as `category_name`, `group_id`, `category_id`, `products`.`name`, `thumbnail_img`, `products`.`slug`, `min_qty`, `mrp` from `products` INNER JOIN `categories` ON `products`.`category_id` = `categories`.`id` INNER JOIN `category_groups` on `categories`.`category_group_id` = `category_groups`.`id` WHERE products.name LIKE '%$search%' $group $category $brand AND `published` = 1 AND `current_stock` = 1 and `approved` = 1 order by CASE WHEN `products`.`name` LIKE '%opel%' THEN 0 ELSE 1 END, 
        `products`.`name` ASC, CAST(`products`.`mrp` AS UNSIGNED) ASC"));
        

        $resultsArray = json_decode(json_encode($results), true);

        $count = 1;
        foreach($resultsArray as $key=>$row){              
            // Assuming 'uploads' table has 'filename' and 'path' columns
            $thumbnail = Upload::find($row['thumbnail_img']);
            // Check if the records are found
            if (!$thumbnail) {
                $thumbnailUrl = null; // Handle not found case
                $photo_url = "https://mazingbusiness.com/public/assets/img/placeholder.jpg";
            } else {
                // Generate the URL using the route to display the image
                $thumbnailUrl = $thumbnail->file_name;
                $photo_url = env('UPLOADS_BASE_URL') . '/' . $thumbnailUrl;
            }
            
            $net_price = ceil((100-$discount) * $row['mrp'] / 100);
            $net_price = number_format($net_price,2, '.', '');

            $list_price = $net_price * 131.6 / 100;
            $list_price = number_format($list_price,2, '.', '');

            if($type == 'net'){
                $price = $net_price;
            }else{
                $price = $list_price;
            }
            $htmlContent .='<tr style="height: 75px;">
                            <td width="5%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$count.'</td>
                            <td width="10%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['part_no'].'</td>
                            <td width="7%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;"><img src="'.$photo_url.'" alt="" style="width: 80px;"></td>
                            <td width="32%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['name'].'</td>
                            <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['group_name'].'</td>
                            <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$row['category_name'].'</td>
                            <td width="15%" style="border: 2px solid #000; text-align: center;  font-family: Arial, Helvetica, sans-serif; font-size: 12px;">'.$price.'</td>
                        </tr>';
            $count++;
        }        
        $htmlContent .= '
                </tbody>
            </table>';

        // Define header and footer
        $header = '<table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="text-align: right; position: relative;">
                        <img src="https://mazingbusiness.com/public/assets/img/pdfHeader.png" width="100%" alt="Header Image" style="display: block;" />
                    </td>
                </tr>
            </table>';
        
        $footer = '<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left" class="col">
                <tbody>
                    <tr>
                        <td style="height: 55px; text-align: center; color: #174e84; font-family: Arial; font-weight: bold;">
                            All prices are NET Prices, and all the products in the PDF are available.
                        </td>
                    </tr>
                    <tr bgcolor="#174e84">
                        <td style="height: 40px; text-align: center; color: #fff; font-family: Arial; font-weight: bold;">
                            Mazing Business Price List for - '.$client_name.' ('.date('d-m-Y h:i:s A').')
                        </td>
                    </tr>
                </tbody>
            </table>';

        $mpdf = new \Mpdf\Mpdf(['format' => 'A4']);
        $mpdf->setHTMLHeader($header);
        $mpdf->SetMargins(0, 10, 40, 10); // Set margins to 10mm on all sides
        // Set header content with explicit height
        $mpdf->setHTMLFooter($footer);
        // Set auto page break
        $mpdf->SetAutoPageBreak(true, 30); // Enable auto page break with a margin of 30mm
        $mpdf->AddPageByArray(['size' => 'A4']);
        // Add HTML content
        $mpdf->WriteHTML($htmlContent);
        //$output = $mpdf->Output('', 'S');

        $pdfPath = public_path('pdfs/');
        if (!file_exists($pdfPath)) {
            mkdir($pdfPath, 0755, true);
        }
        $output = $mpdf->Output($pdfPath . '/' . $this->filename, \Mpdf\Output\Destination::FILE);
        // $output = $mpdf->Output($pdfPath . '/' . $this->filename, 'F');
        // $mpdf->Output($pdfPath . $this->filename, 'F');
        // $pdfPath = 'https://mazingbusiness.com/public/download/pdf/'.$fileName;

        // Storage::put("public/pdfs/{$this->filename}", $output);

        // Update the database record
        PdfReport::where('filename', $this->filename)->update([
            'path' => 'https://mazingbusiness.com/public/pdfs/'.$this->filename,
            'status' => 'completed'
        ]);
        return response()->json(['status' => 'success', 'message' => 'PDF generated and saved successfully.']);
    }
}