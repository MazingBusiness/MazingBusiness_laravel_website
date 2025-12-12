<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\User;
use App\Models\Manager41Challan;
use App\Models\Manager41OrderLogistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class Manager41OrderLogisticsController extends Controller
{
    /**
     * Show Add Logistic form for a Manager-41 challan.
     */
    public function create(string $encryptedChallanId)
    {
        $challanId = Crypt::decrypt($encryptedChallanId);

        /** @var Manager41Challan $challan */
        $challan = Manager41Challan::with(['user', 'sub_order', 'order_warehouse'])
            ->findOrFail($challanId);

        // For UI context (optional)
        $customerName  = $challan->user->company_name ?? $challan->user->name ?? 'Customer';
        $challanNo     = $challan->challan_no;
        $warehouseName = $challan->order_warehouse->name ?? '-';
        $invoiceNo     = null; // optional: you may fill if you map challan->invoice later

        return view('backend.order_logistics.manager41.add', compact(
            'encryptedChallanId',
            'challan',
            'customerName',
            'challanNo',
            'warehouseName',
            'invoiceNo'
        ));
    }

    /**
     * Save logistics for a Manager-41 challan.
     */
    public function store(Request $request, string $encryptedChallanId)
    {


        $challanId = Crypt::decrypt($encryptedChallanId);

        /** @var Manager41Challan $challan */
        $challan = Manager41Challan::with(['user', 'sub_order'])->findOrFail($challanId);

        // Basic validation (+ invoice_no optional)
        $validated = $request->validate([
            'invoice_no'         => ['nullable', 'string', 'max:50'],
            'transport_name'     => ['required', 'string', 'max:150'],
            'lr_date'            => ['required', 'date'],
            'lr_no'              => ['required', 'string', 'max:100'],
            'no_of_boxes'        => ['required', 'integer', 'min:0'],
            'lr_amount'          => ['required', 'numeric', 'min:0'],
            'attachments.*'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf'],
            'invoice_copy_upload'=> ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $partyCode = $challan->user->party_code ?? null;

        // Upload base
        $basePath = public_path('uploads/cw_acetools');
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0755, true);
        }

        // Multiple attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . uniqid() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $file->move($basePath, $fileName);
                $attachments[] = url('public/uploads/cw_acetools/' . $fileName);
            }
        }

        // Single invoice copy
        $invoiceCopyUrl = null;
        if ($request->hasFile('invoice_copy_upload')) {
            $f = $request->file('invoice_copy_upload');
            $name = time() . '_' . uniqid() . '_' . preg_replace('/\s+/', '_', $f->getClientOriginalName());
            $f->move($basePath, $name);
            $invoiceCopyUrl = url('public/uploads/cw_acetools/' . $name);
        }

        // Accept user-entered invoice_no if provided (trim)
        $invoiceNoInput = trim((string) ($validated['invoice_no'] ?? '') ?: '');

        Manager41OrderLogistic::create([
            'challan_id'          => $challan->id,
            'challan_no'          => $challan->challan_no,
            'party_code'          => $partyCode,
            'order_no'            => $challan->sub_order->order_no ?? $challan->challan_no, // fallback
            'invoice_no'          => $invoiceNoInput ?: null,
            'transport_name'      => $validated['transport_name'],
            'lr_date'             => $validated['lr_date'],
            'lr_no'               => $validated['lr_no'],
            'no_of_boxes'         => $validated['no_of_boxes'],
            'payment_type'        => null, // optional
            'lr_amount'           => $validated['lr_amount'],
            'attachment'          => implode(',', $attachments),
            'invoice_copy_upload' => $invoiceCopyUrl,
            'wa_is_processed'     => 1,
            'add_status'          => 1,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // return redirect()->back()->with('success', 'Logistic record added successfully for challan ' . $challan->challan_no . '.');
         return redirect()
        ->route('order.allChallan')
        ->with('success', 'Logistics updated successfully.');
    }



    /** Show Edit form for an existing Manager-41 logistic row */
    public function edit(string $encryptedId)
    {
       

        $id = Crypt::decrypt($encryptedId);

        /** @var Manager41OrderLogistic $logistic */
        $logistic = Manager41OrderLogistic::findOrFail($id);

        // Safe explode current attachments
        $attachments = [];
        if (!empty($logistic->attachment)) {
            $attachments = array_values(array_filter(array_map('trim', explode(',', $logistic->attachment))));
        }

        // Header meta (from challan)
        $challanNo = $logistic->challan_no ?? '-';
        $customer  = '-';
        $warehouse = '-';

        if (!empty($logistic->challan_id)) {
            $challan = Manager41Challan::with(['user', 'order_warehouse'])->find($logistic->challan_id);
            if ($challan) {
                $challanNo = $challan->challan_no ?? $challanNo;
                $customer  = $challan->user->company_name ?? $challan->user->name ?? $customer;
                $warehouse = $challan->order_warehouse->name ?? $warehouse;
            }
        }

        $encryptedId = $encryptedId; // pass through to form route

        return view('backend.order_logistics.manager41.edit', compact(
            'logistic',
            'attachments',
            'encryptedId',
            'challanNo',
            'customer',
            'warehouse'
        ));
    }

    /**
     * Update logic
     * route: manager41.order.logistics.update
     */
    public function update(Request $request, string $encryptedId)
    {
        $id = Crypt::decrypt($encryptedId);

        /** @var Manager41OrderLogistic $logistic */
        $logistic = Manager41OrderLogistic::findOrFail($id);

        // Validate core fields (do NOT validate remove_indexes type so we can accept array OR CSV)
        $validated = $request->validate([
            'invoice_no'          => ['nullable', 'string', 'max:50'],
            'transport_name'      => ['required', 'string', 'max:150'],
            'lr_date'             => ['required', 'date'],
            'lr_no'               => ['required', 'string', 'max:100'],
            'no_of_boxes'         => ['required', 'integer', 'min:0'],
            'lr_amount'           => ['required', 'numeric', 'min:0'],
            'attachments.*'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'invoice_copy_upload' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            // 'remove_indexes' intentionally not type-validated to allow array or CSV
        ]);

        // Prepare upload base dir
        $basePath = public_path('uploads/cw_acetools');
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0755, true);
        }

        // ----- Existing attachments array (ordered oldest->newest as stored)
        $current = [];
        if (!empty($logistic->attachment)) {
            $current = array_values(array_filter(array_map('trim', explode(',', $logistic->attachment))));
        }

        // ----- Collect remove indexes (supports both: array and CSV string)
        $removeParam = $request->input('remove_indexes'); // can be array OR string OR null
        $removeIdx = [];

        if (is_array($removeParam)) {
            // from name="remove_indexes[]"
            $removeIdx = collect($removeParam)->map(fn($v) => (int)$v)->unique()->sortDesc()->values()->all();
        } elseif (is_string($removeParam) && strlen(trim($removeParam)) > 0) {
            // from name="remove_indexes" CSV like "0,2,5"
            $removeIdx = collect(explode(',', $removeParam))
                ->map(fn($v) => (int)trim($v))
                ->unique()->sortDesc()->values()->all();
        }

        // ----- Remove selected items (sort DESC to avoid index-shift confusion)
        foreach ($removeIdx as $idx) {
            if (isset($current[$idx])) {
                unset($current[$idx]);
            }
        }
        $current = array_values($current); // reindex

        // ----- New uploads (prepend newest first)
        $new = [];
        $files = $request->file('attachments', []); // returns [] if not present
        foreach ($files as $file) {
            if (!$file) continue;
            $fileName = time().'_'.uniqid().'_'.preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $file->move($basePath, $fileName);
            $new[] = url('public/uploads/cw_acetools/'.$fileName);
        }
        $finalAttachments = array_merge($new, $current); // newest first

        // ----- Invoice copy upload (replace if provided)
        $invoiceCopyUrl = $logistic->invoice_copy_upload;
        if ($request->hasFile('invoice_copy_upload')) {
            $f = $request->file('invoice_copy_upload');
            $name = time().'_'.uniqid().'_'.preg_replace('/\s+/', '_', $f->getClientOriginalName());
            $f->move($basePath, $name);
            $invoiceCopyUrl = url('public/uploads/cw_acetools/'.$name);
        }

        // ----- Persist
        $logistic->invoice_no          = ($validated['invoice_no'] ?? null) !== null
            ? (trim($validated['invoice_no']) ?: null)
            : null;
        $logistic->transport_name      = $validated['transport_name'];
        $logistic->lr_date             = $validated['lr_date'];
        $logistic->lr_no               = $validated['lr_no'];
        $logistic->no_of_boxes         = $validated['no_of_boxes'];
        $logistic->lr_amount           = $validated['lr_amount'];
        $logistic->attachment          = implode(',', $finalAttachments); // keep as CSV (empty string if none)
        $logistic->invoice_copy_upload = $invoiceCopyUrl;
        $logistic->save();

        return redirect()
            ->route('manager41.order.logistics.edit', $encryptedId)
            ->with('success', 'Logistic record updated. Removed: '.count($removeIdx).' | Added: '.count($new));
    }

}
