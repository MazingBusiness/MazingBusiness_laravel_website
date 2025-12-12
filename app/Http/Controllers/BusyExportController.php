<?php

namespace App\Http\Controllers;
use App\Models\InvoiceOrder;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceDetail;
use App\Models\Manager41Challan;
use App\Models\Manager41PurchaseInvoice;
use Illuminate\Http\Request;
use App\Exports\BusyExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use App\Exports\Manager41BusyExport;
use Illuminate\Support\Facades\Schema;
use App\Models\DebitNoteInvoice;
use App\Models\Shop;

class BusyExportController extends Controller
{

    public function manager41BusyExportIndex(Request $request)
    {
        $data = [];

        // Helper to resolve part_no from a challan detail safely
        $resolvePartNo = function ($detail) {
            // (1) direct column on detail
            if (!empty($detail->part_no)) {
                return (string) $detail->part_no;
            }
            // (2) related product (could be model OR collection)
            if ($detail->relationLoaded('product')) {
                $rel = $detail->getRelation('product');
                $prod = $rel instanceof \Illuminate\Support\Collection ? $rel->first() : $rel;
                if ($prod && !empty($prod->part_no)) {
                    return (string) $prod->part_no;
                }
            }
            // (3) fallback by product_id
            if (!empty($detail->product_id)) {
                return (string) \App\Models\Product::where('id', $detail->product_id)->value('part_no');
            }
            return null;
        };

        // ============ SALES from Manager-41 Challans ============
        $challansQuery = Manager41Challan::with([
            'challan_details.product',   // detail lines + product
            'order_warehouse',           // warehouse relation (series)
            'address.state',             // shipping address (party)
        ])
        ->orderBy('created_at', 'asc')
        ->where('invoice_status', 0);

        // Only filter if the column exists
        if (Schema::hasColumn('manager_41_challans', 'busy_exported')) {
            $challansQuery->where('busy_exported', 0);
        }

        $challans = $challansQuery->get();

        foreach ($challans as $ch) {
            if (!$ch->challan_details->count()) {
                continue;
            }

            $items = [];
            foreach ($ch->challan_details as $d) {
                $partNo = $resolvePartNo($d);
                $qty    = (float) ($d->quantity ?? 0);
                $rate   = (float) ($d->rate ?? 0);
                $amount = (float) ($d->final_amount ?? ($qty * $rate));

                $items[] = [
                    'part_no'    => (string) $partNo,
                    'qty'        => $qty,
                    'unit'       => 'Pcs',
                    'list_price' => round($rate, 0),
                    'discount'   => 0,
                    'amount'     => $amount,
                ];
            }

            // Series (warehouse name) & party
            $seriesName = optional($ch->order_warehouse)->name ?? ($ch->warehouse ?: 'N/A');

            $partyCode = optional($ch->address)->acc_code ?? '';
            $partyName = optional($ch->address)->company_name ?? '';

            // Fallback to JSON shipping_address if relation empty
            if (!$partyName) {
                $addr = is_string($ch->shipping_address)
                    ? json_decode($ch->shipping_address, true)
                    : (array) $ch->shipping_address;

                if (is_array($addr)) {
                    $partyCode = $partyCode ?: ($addr['acc_code'] ?? '');
                    $partyName = $partyName ?: ($addr['company_name'] ?? ($addr['company'] ?? ''));
                }
            }

            $data[] = [
                'vch_series'    => $seriesName,
                'vch_bill_date' => optional($ch->created_at)->format('Y-m-d'),
                'vch_type'      => 'Sales',
                'vch_bill_no'   => $ch->challan_no,
                'party_code'    => $partyCode,
                'party_name'    => $partyName,
                'mc_name'       => $seriesName,
                'items'         => $items,
            ];
        }

        // ============ PURCHASE from Manager-41 Purchase Invoices ============
        $purchasesQuery = Manager41PurchaseInvoice::with([
            'purchaseInvoiceDetails',
            'address.state',
            'warehouse',
            'shop',
        ])->orderBy('created_at', 'asc');

        if (Schema::hasColumn('manager_41_purchase_invoices', 'busy_exported')) {
            $purchasesQuery->where('busy_exported', 0);
        }

        $purchases = $purchasesQuery->get();

        foreach ($purchases as $inv) {
            if (!$inv->purchaseInvoiceDetails->count()) {
                continue;
            }

            $items = [];
            foreach ($inv->purchaseInvoiceDetails as $d) {
                $qty   = (float) $d->qty;
                $price = (float) $d->price;
                $tax   = (float) $d->tax;

                // Busy sheet wants tax-inclusive price for purchase lines (your convention)
                $taxed = $price + ($price * $tax / 100);
                $items[] = [
                    'part_no'    => (string) $d->part_no,
                    'qty'        => $qty,
                    'unit'       => 'Pcs',
                    'list_price' => round($taxed, 0),
                    'discount'   => 0,
                    'amount'     => round($taxed * $qty, 2),
                ];
            }

            $vchType   = $inv->purchase_invoice_type === 'customer' ? 'Credit Note' : 'Purchase';
            $series    = optional($inv->warehouse)->name ?? 'N/A';
            $partyCode = '';
            $partyName = '';

            if ($inv->purchase_invoice_type === 'seller') {
                $partyName = optional($inv->shop)->name ?? 'N/A';
                $partyCode = optional($inv->shop)->seller_busy_code ?? '';
            } else {
                $partyName = optional($inv->address)->company_name ?? '';
                $partyCode = optional($inv->address)->acc_code ?? '';
            }

            $data[] = [
                'vch_series'    => $series,
                'vch_bill_date' => optional($inv->created_at)->format('Y-m-d'),
                'vch_type'      => $vchType,
                'vch_bill_no'   => $inv->purchase_no,
                'party_code'    => $partyCode,
                'party_name'    => $partyName,
                'mc_name'       => $series,
                'items'         => $items,
            ];
        }

        // Merge + paginate (same as your original UX)
        $collection  = collect($data);
        $currentPage = (int) $request->get('page', 1);
        $perPage     = 25;

        $paginated = new LengthAwarePaginator(
            $collection->forPage($currentPage, $perPage),
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('backend.busy_export.index', ['data' => $paginated]);
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
    public function index(Request $request)
    {

        if ($this->isActingAs41Manager()) {
            return $this->manager41BusyExportIndex($request);
        }
        $data = [];

        // ✅ SALES: invoice_orders 
        $invoiceOrders = \App\Models\InvoiceOrder::with(['invoice_products', 'address.state', 'warehouse'])
        ->where('busy_exported', 0)
        ->orderBy('created_at', 'asc') // ✅ ascending order
        ->where('invoice_cancel_status', 0)
        ->latest()->get();

        foreach ($invoiceOrders as $order) {
            if (!$order->invoice_products->count()) continue;

            $items = [];
            foreach ($order->invoice_products as $detail) {
                $items[] = [
                    'part_no'    => $detail->part_no,
                    'qty'        => $detail->billed_qty,
                    'unit'       => 'Pcs',
                    'list_price' => round($detail->rate, 0),
                    'discount'   => 0,
                    'amount'     => $detail->billed_amt,
                ];
            }

            $data[] = [
                'vch_series'    => $order->warehouse->name ?? 'N/A', // 🔁 Dynamic Warehouse Name
                'vch_bill_date' => optional($order->created_at)->format('Y-m-d'),
                'vch_type'      => 'Sales',
                'vch_bill_no'   => $order->invoice_no,
                'party_code'    => $order->address->acc_code ?? '',
                'party_name'    => $order->address->company_name ?? '',
                'mc_name'       => $order->warehouse->name ?? '',
                'items'         => $items,
            ];
        }

        // ✅ PURCHASE: purchase_invoices
        $purchaseInvoices = \App\Models\PurchaseInvoice::with(['purchaseInvoiceDetails', 'address.state', 'warehouse'])
        ->where('busy_exported', 0)
        ->orderBy('created_at', 'asc') // ✅ ascending order
        ->latest()->get();

        foreach ($purchaseInvoices as $invoice) {
            if (!$invoice->purchaseInvoiceDetails->count()) continue;

            $items = [];
            foreach ($invoice->purchaseInvoiceDetails as $detail) {
                $items[] = [
                    'part_no'    => $detail->part_no,
                    'qty'        => $detail->qty,
                    'unit'       => 'Pcs',
                    'list_price' => (float) $detail->price + ($detail->price * $detail->tax / 100),
                    'discount'   => 0,
                    'amount'     => round(($detail->price + ($detail->price * $detail->tax / 100)) * $detail->qty, 2),
                ];
            }

            // 🔍 Determine type and party name
            $vch_type = $invoice->purchase_invoice_type === 'customer' ? 'Credit Note' : 'Purchase';

            if ($invoice->purchase_invoice_type === 'seller') {
                $shop = \App\Models\Shop::where('seller_id', $invoice->seller_id)->first();
                $party_name = $shop->name ?? 'N/A';
                $seller_busy_code = $shop->seller_busy_code ?? 'N/A';
            } else {
                $party_name = $invoice->address->company_name ?? '';
            }

            $data[] = [
                'vch_series'    => $invoice->warehouse->name ?? 'N/A', // 🔁 Dynamic Warehouse Name
                'vch_bill_date' => optional($invoice->created_at)->format('Y-m-d'),
                'vch_type'      => $vch_type,
                'vch_bill_no'   => $invoice->purchase_no,
                'party_code'    => $seller_busy_code ?? '',
                'party_name'    => $party_name,
                'mc_name'       => $invoice->warehouse->name ?? '',
                'items'         => $items,
            ];
        }


        // ✅ DEBIT NOTE: debit_note_invoices
        $debitNotes = DebitNoteInvoice::with(['debitNoteInvoiceDetails', 'address.state', 'warehouse'])
            ->where('busy_exported', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($debitNotes as $dn) {
            if (!$dn->debitNoteInvoiceDetails->count()) continue;

            $items = [];
            foreach ($dn->debitNoteInvoiceDetails as $detail) {
                $qty   = (float) $detail->qty;
                $price = (float) $detail->price;            // tax-exclusive
                $tax   = (float) $detail->tax;              // %
                $taxed = $price + ($price * $tax / 100);    // Busy wants tax-inclusive here
                $items[] = [
                    'part_no'    => (string) $detail->part_no,
                    'qty'        => $qty,
                    'unit'       => 'Pcs',
                    'list_price' => round($taxed, 0),
                    'discount'   => 0,
                    'amount'     => round($taxed * $qty, 2),
                ];
            }

            // Party & series (warehouse)
            $series    = optional($dn->warehouse)->name ?? 'N/A';
            $partyName = '';
            $partyCode = '';

            // If debit note is to a seller (vendor return), prefer Shop (busy code) else fallback to seller_info
            if ($dn->debit_note_type === 'seller') {
                $shop      = Shop::where('seller_id', $dn->seller_id)->first();
                $partyName = $shop->name ?? ($dn->seller_info['seller_name'] ?? 'N/A');
                $partyCode = $shop->seller_busy_code ?? '';
            } else {
                // If it's customer-side (rare), use address
                $partyName = optional($dn->address)->company_name ?? '';
                $partyCode = optional($dn->address)->acc_code ?? '';
            }

            $data[] = [
                'vch_series'    => $series,
                'vch_bill_date' => optional($dn->created_at)->format('Y-m-d'),
                'vch_type'      => 'Debit Note',
                'vch_bill_no'   => $dn->debit_note_no,
                'party_code'    => $partyCode,
                'party_name'    => $partyName,
                'mc_name'       => $series,
                'items'         => $items,
            ];
        }


       // return view('backend.busy_export.index', compact('data'));

            // Merge both sales and purchase into a collection
        $collection = collect($data);

        // Manually paginate the combined data
        $currentPage = request()->get('page', 1);
        $perPage = 25;
        $paginatedData = new LengthAwarePaginator(
            $collection->forPage($currentPage, $perPage),
            $collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('backend.busy_export.index', ['data' => $paginatedData]);
    }







    public function exportBusyFormat()
    {

         if ($this->isActingAs41Manager()) {
            return $this->exportManager41BusyFormat();
        }
        
       $export = new BusyExport;

        $filename = 'busyexport_' . now()->format('Ymd_His') . '.xlsx';

        $response = \Maatwebsite\Excel\Facades\Excel::download(
            $export,
            $filename,
            \Maatwebsite\Excel\Excel::XLSX,
            [
                'Pragma'        => 'no-cache',
                'Cache-Control' => 'no-store, must-revalidate',
                'Expires'       => '0',
            ]
        );

        // mark exported
        \App\Models\InvoiceOrder::whereIn('id', $export->getInvoiceOrderIds())->update(['busy_exported' => 1]);
        \App\Models\PurchaseInvoice::whereIn('id', $export->getPurchaseInvoiceIds())->update(['busy_exported' => 1]);
        \App\Models\DebitNoteInvoice::whereIn('id', $export->getDebitNoteInvoiceIds())->update(['busy_exported' => 1]); // ⬅️ NEW

        return $response;
    }

    public function exportManager41BusyFormat()
    {


        $export   = new Manager41BusyExport();
        $filename = 'manager41_busyexport_' . now()->format('Ymd_His') . '.xlsx';

        // Download the file
        $response = Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX, [
            'Pragma'         => 'no-cache',
            'Cache-Control'  => 'no-store, must-revalidate',
            'Expires'        => '0',
        ]);

        // Mark exported rows so we don't export them again
        \App\Models\Manager41Challan::whereIn('id', $export->getChallanIds())
            ->update(['busy_exported' => 1]);

        \App\Models\Manager41PurchaseInvoice::whereIn('id', $export->getPurchaseInvoiceIds())
            ->update(['busy_exported' => 1]);

        return $response;
    }
}

?>