<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use App\Models\User;
use App\Models\Address;
use Config;
use Hash;
use PDF;
use Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsAppWebService;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendWhatsAppMessagesJob;
use App\Http\Controllers\InvoiceController;
class DispatchDataController extends Controller
{

private function getManagerPhone($managerId)
    {
      $managerData = DB::table('users')
          ->where('id', $managerId)
          ->select('phone')
          ->first();

      return $managerData->phone ?? 'No Manager Phone';  // Default in case manager phone is not found
    }

    private function getCityName($dispatchId) {
        // Extract the city code from the dispatch ID
        preg_match('/[A-Z]{3}/', $dispatchId, $matches);

        // Check if a match was found
        if (!empty($matches)) {
            $cityCode = $matches[0];

            // Map city codes to full city names
            $cityMap = [
                'MUM' => 'Mumbai',
                'DEL' => 'Delhi',
                'KOL' => 'Kolkata',
                // Add more city codes and names here if needed
            ];

            // Return the corresponding city name or a default message
            return $cityMap[$cityCode] ?? 'Unknown City';
        }

        return 'Invalid Dispatch ID';
    }
   public function getWarehouseFromDispatchId($dispatchId)
   {
        // Extract the warehouse code from the dispatch_id
        if (strpos($dispatchId, 'KOL') !== false) {
            return 'Kolkata';
        } elseif (strpos($dispatchId, 'MUM') !== false) {
            return 'Mumbai';
        } elseif (strpos($dispatchId, 'DEL') !== false) {
            return 'Delhi';
        } else {
            return 'Unknown';
        }
   }

    public function index(Request $request)
    {


        // Access control: Allow only specific users
        $allowedUserIds = [1,180, 169, 25606]; // List of allowed user IDs
        if (!in_array(auth()->user()->id, $allowedUserIds)) {
            // Redirect or abort if the user is not allowed
            return abort(403, 'Unauthorized action.'); // You can customize the response
        }
        // Sorting parameters
        $sort = $request->input('sort', 'dispatch_data.id'); // Default sort by dispatch_data.id
        $direction = $request->input('direction', 'desc'); // Default direction descending

        // Validate sort column to prevent SQL injection
        $validSortColumns = [
            'dispatch_data.id',
            'dispatch_data.dispatch_id',
            'dispatch_data.party_code',
            'orders.code',
            'orders.created_at',
            'addresses.company_name'
        ];
        if (!in_array($sort, $validSortColumns)) {
            $sort = 'dispatch_data.id'; // Fallback to a default valid column
        }

        // Initialize the query with joins to fetch data
        $query = DB::table('dispatch_data')
            ->leftJoin('orders', 'dispatch_data.order_id', '=', 'orders.id')
            ->leftJoin('addresses', 'dispatch_data.party_code', '=', 'addresses.acc_code')
            ->select(
                'dispatch_data.*',
                'orders.code as order_code',
                'orders.created_at as created_at',
                'addresses.company_name as address_company_name'
            )
            ->orderBy($sort, $direction); // Order by the specified column and direction

        // Fetch all rows and add warehouse information
        $data = $query->get();

        foreach ($data as $row) {
            $row->warehouse = $this->getWarehouseFromDispatchId($row->dispatch_id);
        }

        // Filter by search term (if provided)
        if ($request->has('search') && $request->search != '') {
            $searchTerm = strtolower($request->search);
            $data = $data->filter(function ($row) use ($searchTerm) {
                return str_contains(strtolower($row->dispatch_id), $searchTerm)
                    || str_contains(strtolower($row->item_name), $searchTerm)
                    || str_contains(strtolower($row->part_no), $searchTerm)
                    || str_contains(strtolower($row->party_code), $searchTerm)
                    || str_contains(strtolower($row->order_code), $searchTerm)
                    || str_contains(strtolower($row->address_company_name), $searchTerm)
                    || str_contains(strtolower($row->warehouse), $searchTerm); // Filter by warehouse
            });
        }

        // Group the data by dispatch_id
        $groupedData = $data->groupBy('dispatch_id');


        // Paginate the grouped data
        $perPage = 10; // Set the number of groups per page
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $pagedData = $groupedData->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $pagination = new LengthAwarePaginator(
            $pagedData,
            $groupedData->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(), // Preserve query parameters in pagination
            ]
        );

        // Pass the paginated data and sorting parameters to the view
        return view('backend.dispatch_data.index', [
            'data' => $pagedData,
            'pagination' => $pagination,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }





    public function updateBilledQuantity(Request $request)
    {
        $request->validate([
            'dispatch_id' => 'required|string',
            'part_no' => 'required|string',
            'billed_qty' => 'required|numeric|min:0',
        ], [
            'dispatch_id.required' => 'The dispatch ID is required.',
            'part_no.required' => 'The part number is required.',
            'billed_qty.required' => 'The billed quantity is required.',
            'billed_qty.numeric' => 'The billed quantity must be a number.',
        ]);


        // Update the dispatch_data table
        $updated = DB::table('dispatch_data')
            ->where('dispatch_id', $request->input('dispatch_id'))
            ->where('part_no', $request->input('part_no'))
            ->update([
                'billed_qty' => $request->input('billed_qty'),
                'manually_update_item' => true
            ]);

        if ($updated) {
            return response()->json(['success' => true, 'message' => 'Billed quantity updated successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Failed to update billed quantity.'], 400);
    }

     public function cancelItem(Request $request)
    {
       
        $request->validate([
            'dispatch_id' => 'required|string|exists:dispatch_data,dispatch_id',
            'part_no' => 'required|string|exists:dispatch_data,part_no',
        ]);

        try {
            // Update the column in the database
            DB::table('dispatch_data')
                ->where('dispatch_id', $request->dispatch_id)
                ->where('part_no', $request->part_no)
                ->update(['manually_cancel_item' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Item has been successfully canceled.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel the item. Please try again.',
            ], 500);
        }
    }




    public function generateDispatchPDF($orderId, $partyCode, $dispatchId)
    {
        try {
            // Decrypt the dispatch_id
            $dispatchId = Crypt::decrypt($dispatchId);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid dispatch ID.'], 400);
        }



        // Fetch dispatch data
        $dispatchData = DB::table('dispatch_data')
            ->join('products', 'dispatch_data.product_id', '=', 'products.id')
            // ->where('dispatch_data.is_processed', false)
            ->where('dispatch_data.order_id', $orderId)
            ->where('dispatch_data.party_code', $partyCode)
            ->where('dispatch_data.dispatch_id', $dispatchId)
            ->select(
                'dispatch_data.dispatch_id',
                'dispatch_data.part_no',
                'dispatch_data.item_name',
                'dispatch_data.order_qty',
                'dispatch_data.billed_qty',
                'dispatch_data.rate',
                'dispatch_data.bill_amount',
                'products.slug'
            )
            ->get();




        if ($dispatchData->isEmpty()) {
            return response()->json(['error' => 'No dispatch data found for the specified parameters.'], 404);
        }

        // Fetch user details
        $userDetails = DB::table('users')
            ->where('party_code', $partyCode)
            ->select('company_name', 'phone', 'party_code')
            ->first();

        if (!$userDetails) {
            return response()->json(['error' => 'User not found for the provided party code.'], 404);
        }

        // Fetch order details
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->select('code', 'created_at')
            ->first();

        // Prepare data for the PDF
        $pdfData = [
            'dispatchData' => $dispatchData,
            'orderId' => $orderId,
            'partyCode' => $partyCode,
            'userDetails' => $userDetails,
            'order' => $order,
            'dispatchId' => $dispatchId,
        ];

        // Generate the PDF
        $pdf = PDF::loadView('backend.invoices.dispatch_product', $pdfData);

        // Define the file name
        $fileName = 'dispatch-data-' . $orderId . '-' . uniqid() . '.pdf';
        // Return the PDF as a download
        return $pdf->download($fileName);
    }



    public function senddispatchpdf($orderId, $partyCode, $dispatchId)
    {
         $groupId = uniqid('group_', true);
        try {
            // Decrypt the dispatch_id
            $dispatchId = Crypt::decrypt($dispatchId);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid dispatch ID.'], 400);
        }

        // Fetch dispatch data
        $dispatchData = DB::table('dispatch_data')
            ->join('products', 'dispatch_data.product_id', '=', 'products.id')
            // ->where('dispatch_data.is_processed', false)
            ->where('dispatch_data.order_id', $orderId)
            ->where('dispatch_data.party_code', $partyCode)
            ->where('dispatch_data.dispatch_id', $dispatchId)
            ->select(
                'dispatch_data.dispatch_id',
                'dispatch_data.part_no',
                'dispatch_data.item_name',
                'dispatch_data.order_qty',
                'dispatch_data.billed_qty',
                'dispatch_data.rate',
                'dispatch_data.bill_amount',
                'products.slug'
            )
            ->get();

        if ($dispatchData->isEmpty()) {
            return response()->json(['error' => 'No dispatch data found for the specified parameters.'], 404);
        }

        // Fetch user details
        $userDetails = DB::table('users')
            ->where('party_code', $partyCode)
            ->select('company_name', 'phone', 'party_code','manager_id')
            ->first();

        if (!$userDetails) {
            return response()->json(['error' => 'User not found for the provided party code.'], 404);
        }

        $manager_phone= $this->getManagerPhone($userDetails->manager_id);

        // Fetch order details
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->select('code', 'created_at')
            ->first();

        // Prepare data for the PDF
        $pdfData = [
            'dispatchData' => $dispatchData,
            'orderId' => $orderId,
            'partyCode' => $partyCode,
            'userDetails' => $userDetails,
            'order' => $order,
            'dispatchId' => $dispatchId,
        ];

        // Generate the PDF
        $pdf = PDF::loadView('backend.invoices.dispatch_product', $pdfData);

        // Define the file name
        $fileName = 'dispatch-data-' . $orderId . '-' . uniqid() . '.pdf';

        $filePath = public_path('approved_products_pdf/' . $fileName);

        // Save the PDF to the specified directory
        $pdf->save($filePath);

        // Generate the public URL
        $publicUrl = url('public/approved_products_pdf/' . $fileName);
            // Return the PDF as a download

        $cityName = $this->getCityName($dispatchId);

        // whatsapp sending code start 
          $templateData = [
                    'name' => 'utiltiy_product_dispatch', // Replace with your template name, e.g., 
                    'language' => 'en_US', // Replace with your desired language code
                    'components' => [
                        [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => 'document', 'document' => ['link' => $publicUrl,'filename' => basename($publicUrl),]],
                            ],
                        ],
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $userDetails->company_name],
                                ['type' => 'text', 'text' => $order->code],
                                ['type' => 'text', 'text' => $cityName],
                                ['type' => 'text', 'text' => $order->created_at],
                                ['type' => 'text', 'text' => $manager_phone],

                            ],
                        ],
                    ],
                ];


             // Update is_processed to true for all dispatched items
        foreach ($dispatchData as $item) {
            DB::table('dispatch_data')
                ->where('dispatch_id', $item->dispatch_id)
                ->where('part_no', $item->part_no)
                ->update(['is_processed' => true]);
        }
        $to = [$userDetails->phone,$manager_phone];
        $this->whatsAppWebService = new WhatsAppWebService();

        foreach ($to as $recipient) {
            $jsonResponse = $this->whatsAppWebService->sendTemplateMessage($recipient, $templateData);
        }

        return response()->json(['success' => true,'message'=>'PDF sent successfully via WhatsApp.']);
               
    }


}