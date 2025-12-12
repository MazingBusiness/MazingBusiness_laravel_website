<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookLog;

class PaymentController extends Controller
{
    public function __construct() {
        // Staff Permission Check
        $this->middleware(['permission:seller_payment_history'])->only('payment_histories');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    // public function index()
    // {
    //     $payments = Payment::where('seller_id', Auth::user()->seller->id)->paginate(9);
    //     return view('seller.payment_history', compact('payments'));
    // }

    public function webhook(Request $request)
	{
		// Check if the request method is POST
		if ($request->isMethod('post')) {
			// Check if the content type is 'text/plain'
			$contentType = $request->header('Content-Type');

			if ($contentType === 'text/plain') {
				// Get the raw POST data
				$rawPostData = $request->getContent();
				
				// If the data is encoded (like Base64), you may want to decode it
				// Check if the data is Base64 encoded
				if (base64_decode($rawPostData, true) !== false) {
					// Decode the Base64 data
					$decodedData = base64_decode($rawPostData);
				} else {
					// Handle if it's not Base64 or any other encoding type
					$decodedData = $rawPostData;
				}

				// Log the raw POST data to a file
				\File::append(storage_path('logs/callback_logs.txt'), $decodedData . PHP_EOL);
				
				// Log the raw POST data into the webhook_logs table
				DB::table('webhook_logs')->insert([
					'payload' => $decodedData,
					'timestamp' => now(), // Adds a timestamp for the insert
				]);

				return response()->json(['message' => 'Callback received successfully.']);
			} else {
				return response('Invalid content type. Only text/plain is supported.', 400)
					   ->header('Content-Type', 'text/plain');
			}
		} else {
			return response('Only POST requests are allowed.', 405)
				   ->header('Content-Type', 'text/plain');
		}
	}



    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function payment_histories(Request $request)
    {
        $payments = Payment::orderBy('created_at', 'desc')->paginate(15);
        return view('backend.sellers.payment_histories.index', compact('payments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find(decrypt($id));
        $payments = Payment::where('seller_id', $user->id)->orderBy('created_at', 'desc')->get();
        if($payments->count() > 0){
            return view('backend.sellers.payment', compact('payments', 'user'));
        }
        flash(translate('No payment history available for this seller'))->warning();
        return back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
