<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoiceDetail;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\WhatsAppWebService; // <-- make sure this exists & is imported

class ManagerClientPurchaseNotify extends Controller
{
    /**
     * Dynamic, multi-manager flow.
     * Route idea:
     * Route::get('/notify/manager-clients', [ManagerClientPurchaseNotify::class, 'generateAll']);
     * Optional: ?manager_id=180 to limit to one manager.
     */
    public function generateAll(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @ini_set('max_execution_time', '900');

        // Ensure public/pdfs exists
        $outputDir = public_path('pdfs');
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // ===== Global step: 7-day purchased part_nos (normalized) =====
        $purchase7 = $this->getPurchasePartNos7d();
        if (empty($purchase7)) {
            return response()->json([
                'ok'      => false,
                'results' => [],
                'message' => 'No products purchased in the last 7 days.',
            ]);
        }

        // Keep only those having current_stock >= 1
        $purchase7WithStock = $this->filterByCurrentStock($purchase7);
        if (empty($purchase7WithStock)) {
            return response()->json([
                'ok'      => false,
                'results' => [],
                'message' => 'No 7-day purchased products have current_stock >= 1.',
            ]);
        }

        // ===== Pick managers =====
        $managerId = (int) $request->query('manager_id', 0);
        $managersQuery = User::query()->where('user_type', 'staff');
        if ($managerId > 0) {
            $managersQuery->where('id', $managerId);
        }
        $managers = $managersQuery->get(['id','name','phone']);

        $results  = [];
        $ts       = Carbon::now()->format('Ymd_His');

        foreach ($managers as $manager) {
            // Clients for this manager (exclude staff accounts)
            $clients = User::query()
                ->where('manager_id', $manager->id)
                ->where(function ($q) {
                    $q->whereNull('user_type')->orWhere('user_type','!=','staff');
                })
                ->get(['id','name','company_name','phone','state','party_code']);

            $clientSections = [];

            foreach ($clients as $client) {
                // User-wise distinct part_nos from SubOrderDetails
                $subOrderPartNos  = $this->getSubOrderPartNosForUser($client->id);
                // Pre-closed part_nos (pre_closed_status = 1)
                $preClosedPartNos = $this->getPreClosedPartNosForUser($client->id);

                // Intersections with 7-day purchase set (with stock)
                $a1 = array_values(array_intersect($purchase7WithStock, $subOrderPartNos));
                $a2 = array_values(array_intersect($purchase7WithStock, $preClosedPartNos));

                // Final set = union (unique)
                $finalParts = array_values(array_unique(array_merge($a1, $a2)));
                if (empty($finalParts)) {
                    continue;
                }

                // Build rows for this client
                $rows = $this->buildRowsForUserAndParts($client->id, $finalParts);
                if (empty($rows)) {
                    continue;
                }

                $addr = $client->address_by_party_code()->first();

                $clientSections[] = [
                    'client' => [
                        'id'           => $client->id,
                        'name'         => $client->name,
                        'company_name' => $client->company_name,
                        'phone'        => $client->phone,
                        'state'        => $client->state,
                        'party_code'   => $client->party_code,
                        'address'      => [
                            'company_name' => $addr->company_name ?? '',
                            'address'      => $addr->address ?? '',
                            'address_2'    => $addr->address_2 ?? '',
                            'postal_code'  => $addr->postal_code ?? '',
                            'city'         => $addr->city ?? '',
                            'state'        => $addr->state ?? '',
                        ],
                    ],
                    'rows' => $rows,
                ];
            }

            // If nothing matched for this manager → no PDF
            if (empty($clientSections)) {
                $results[] = [
                    'manager_id'   => $manager->id,
                    'manager_name' => $manager->name,
                    'pdf'          => null,
                    'client_count' => 0,
                    'note'         => 'No matching clients/products; PDF not generated.',
                ];
                continue;
            }

            // Render single PDF per manager
            $fileName = 'manager_' . $manager->id . '_' . $ts . '.pdf';
            $filePath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

            $pdf = Pdf::loadView('backend.notify.manager_client_purchase_notify', [
                'manager'        => $manager,
                'clientSections' => $clientSections,
                'aceAddress'     => [
                    'line1' => 'ACE TOOLS PRIVATE LIMITED',
                    'line2' => 'Building No./Flat No.: Khasra No. 58/15,',
                    'line3' => 'Pal Colony, Village Rithala,Delhi',
                    'line4' => 'New Delhi - 110085',
                ],
            ])->setPaper('a4','portrait');

            file_put_contents($filePath, $pdf->output());

            $relativePdf = 'public/pdfs/' . $fileName;

            // ===== WhatsApp send (document header) =====
            $waResp = $this->sendManagerPdfOnWhatsApp($manager, $relativePdf);

            $results[] = [
                'manager_id'   => $manager->id,
                'manager_name' => $manager->name,
                'pdf'          => $relativePdf,   // includes "public/" prefix as requested
                'client_count' => count($clientSections),
                'note'         => 'Generated successfully.',
                'whatsapp'     => $waResp,
            ];
        }

        return response()->json([
            'ok'      => true,
            'results' => $results,
        ]);
    }

    /* =========================
       Helper functions
       ========================= */

    protected function normPart(?string $pn): string
    {
        return strtoupper(trim((string) $pn));
    }

    /** Step-1: 7-day purchase distinct normalized part_nos */
    protected function getPurchasePartNos7d(): array
    {
        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay()->toDateString();

        $partNos = DB::table('purchase_invoice_details as pid')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pid.purchase_invoice_id')
            ->whereDate('pi.seller_invoice_date', '>=', $sevenDaysAgo)
            ->pluck('pid.part_no')
            ->filter()
            ->map(fn($pn) => $this->normPart($pn))
            ->unique()
            ->values()
            ->all();

        return $partNos ?: [];
    }

    /** Step-2: keep only those with products.current_stock >= 1 (returns normalized part_nos) */
    protected function filterByCurrentStock(array $partNosNorm): array
    {
        if (empty($partNosNorm)) return [];

        $rows = DB::table('products')
            ->select(DB::raw('UPPER(TRIM(part_no)) as pnorm'))
            ->whereIn(DB::raw('UPPER(TRIM(part_no))'), $partNosNorm)
            ->where('current_stock', '>=', 1)
            ->pluck('pnorm')
            ->all();

        return array_values(array_unique(array_map('strval', $rows)));
    }

    /** SubOrderDetails (distinct normalized part_nos) for a user */
    protected function getSubOrderPartNosForUser(int $userId): array
    {
        $rows = DB::table('sub_order_details as sod')
            ->join('sub_orders as so', 'so.id', '=', 'sod.sub_order_id')
            ->join('products as p', 'p.id', '=', 'sod.product_id')
            ->where('so.user_id', $userId)
            ->distinct()
            ->pluck('p.part_no')
            ->filter()
            ->map(fn($pn) => $this->normPart($pn))
            ->unique()
            ->values()
            ->all();

        return $rows ?: [];
    }

    /** Pre-closed (pre_closed_status = 1) distinct normalized part_nos for a user */
    protected function getPreClosedPartNosForUser(int $userId): array
    {
        $rows = DB::table('sub_order_details as sod')
            ->join('sub_orders as so', 'so.id', '=', 'sod.sub_order_id')
            ->join('products as p', 'p.id', '=', 'sod.product_id')
            ->where('so.user_id', $userId)
            ->where('sod.pre_closed_status', 1)
            ->distinct()
            ->pluck('p.part_no')
            ->filter()
            ->map(fn($pn) => $this->normPart($pn))
            ->unique()
            ->values()
            ->all();

        return $rows ?: [];
    }

    /**
     * Build final rows:
     * - name from products
     * - stock = SUM(ProductWarehouse.qty) by part_no
     * - last_purchased = MAX(orders.created_at) in last 1 year
     * - qty_year            = SUM(order_details.quantity) in last 1 year
     * - qty_sold_year       = SUM(invoice_order_details.billed_qty) in last 1 year
     * - qty_preclosed_year  = SUM(sub_order_details.pre_closed) in last 1 year
     */
    protected function buildRowsForUserAndParts(int $userId, array $finalPartsNorm): array
    {
        if (empty($finalPartsNorm)) return [];

        // Product names & display part_no
        $productRows = Product::query()
            ->whereIn(DB::raw('UPPER(TRIM(part_no))'), $finalPartsNorm)
            ->get(['part_no', 'name']);

        $nameByNorm = [];
        $displayPNByNorm = [];
        foreach ($productRows as $p) {
            $norm = $this->normPart($p->part_no);
            $nameByNorm[$norm]      = $p->name ?? $p->part_no;
            $displayPNByNorm[$norm] = (string) $p->part_no;
        }

        // Stock = SUM(ProductWarehouse.qty)
        $stockRows = ProductWarehouse::query()
            ->select(DB::raw('UPPER(TRIM(part_no)) as pnorm'), DB::raw('SUM(qty) as total_qty'))
            ->whereIn(DB::raw('UPPER(TRIM(part_no))'), $finalPartsNorm)
            ->groupBy('pnorm')
            ->get();

        $stockByNorm = [];
        foreach ($stockRows as $r) {
            $stockByNorm[(string)$r->pnorm] = (float) $r->total_qty;
        }

        $oneYearAgo = Carbon::now()->subYear()->startOfDay();

        // Orders → qty_year, last_purchased
        $aggOrders = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->where('o.user_id', $userId)
            ->whereDate('o.created_at', '>=', $oneYearAgo)
            ->whereIn(DB::raw('UPPER(TRIM(p.part_no))'), $finalPartsNorm)
            ->groupBy(DB::raw('UPPER(TRIM(p.part_no))'))
            ->get([
                DB::raw('UPPER(TRIM(p.part_no)) as pnorm'),
                DB::raw('SUM(od.quantity) as qty_year'),
                DB::raw('MAX(o.created_at) as last_purchased_at'),
            ]);

        $ordersAggByNorm = [];
        foreach ($aggOrders as $row) {
            $ordersAggByNorm[$row->pnorm] = [
                'qty_year'       => (float) $row->qty_year,
                'last_purchased' => $row->last_purchased_at ? Carbon::parse($row->last_purchased_at)->format('d-m-Y') : '',
            ];
        }

        // Invoices → billed_qty
        $aggInvoices = DB::table('invoice_order_details as iod')
            ->join('invoice_orders as io', 'io.id', '=', 'iod.invoice_order_id')
            ->where('io.user_id', $userId)
            ->whereDate('io.created_at', '>=', $oneYearAgo)
            ->whereIn(DB::raw('UPPER(TRIM(iod.part_no))'), $finalPartsNorm)
            ->groupBy(DB::raw('UPPER(TRIM(iod.part_no))'))
            ->get([
                DB::raw('UPPER(TRIM(iod.part_no)) as pnorm'),
                DB::raw('SUM(iod.billed_qty) as qty_sold_year'),
            ]);

        $soldAggByNorm = [];
        foreach ($aggInvoices as $row) {
            $soldAggByNorm[$row->pnorm] = (float) $row->qty_sold_year;
        }

        // SubOrders → pre_closed
        $aggPreclosed = DB::table('sub_order_details as sod')
            ->join('sub_orders as so', 'so.id', '=', 'sod.sub_order_id')
            ->join('products as p', 'p.id', '=', 'sod.product_id')
            ->where('so.user_id', $userId)
            ->whereDate('so.created_at', '>=', $oneYearAgo)
            ->whereIn(DB::raw('UPPER(TRIM(p.part_no))'), $finalPartsNorm)
            ->groupBy(DB::raw('UPPER(TRIM(p.part_no))'))
            ->get([
                DB::raw('UPPER(TRIM(p.part_no)) as pnorm'),
                DB::raw('SUM(COALESCE(sod.pre_closed,0)) as qty_preclosed_year'),
            ]);

        $preclosedAggByNorm = [];
        foreach ($aggPreclosed as $row) {
            $preclosedAggByNorm[$row->pnorm] = (float) $row->qty_preclosed_year;
        }

        // Build rows
        $rows = [];
        $sn   = 1;
        $sortedNorms = $finalPartsNorm;
        sort($sortedNorms);

        foreach ($sortedNorms as $norm) {
            $rows[] = [
                'sn'                 => $sn++,
                'part_no'            => $displayPNByNorm[$norm] ?? $norm,
                'name'               => $nameByNorm[$norm] ?? $displayPNByNorm[$norm] ?? $norm,
                'stock'              => $stockByNorm[$norm] ?? 0.0,
                'last_purchased'     => $ordersAggByNorm[$norm]['last_purchased'] ?? '',
                'qty_year'           => $ordersAggByNorm[$norm]['qty_year'] ?? 0.0,      // OrderDetails.quantity
                'qty_sold_year'      => $soldAggByNorm[$norm] ?? 0.0,                     // InvoiceOrderDetails.billed_qty
                'qty_preclosed_year' => $preclosedAggByNorm[$norm] ?? 0.0,                // SubOrderDetails.pre_closed
            ];
        }

        return $rows;
    }

    /* =========================
       WhatsApp helpers
       ========================= */

    /**
     * Build a public HTTPS URL for the PDF.
     * Input  : "public/pdfs/manager_...pdf" or "pdfs/manager_...pdf"
     * Output : "https://your-domain.com/public/pdfs/manager_...pdf"
     */
    protected function makePublicUrl(string $relativePath): string
    {
        $path = ltrim($relativePath, '/');
        if (!str_starts_with($path, 'public/')) {
            $path = 'public/' . $path;
        }
        return rtrim(url('/'), '/') . '/' . $path; // uses APP_URL
    }

    /**
     * Send WhatsApp template with a document header (the manager’s PDF).
     * Template: manager_client_purchase_notify
     * Body param(1): manager name
     */
    protected function sendManagerPdfOnWhatsApp(User $manager, string $relativePdfPath): array
    {
        try {
            // Basic phone normalization (assumes India if 10 digits)
            $raw = preg_replace('/\D+/', '', (string)($manager->phone ?? ''));
            if ($raw === '') {
                return ['ok' => false, 'message' => 'Manager phone missing'];
            }
            if (strlen($raw) === 10) {
                $raw = '91' . $raw;
            }
            $to = '+' . ltrim($raw, '+');
            //$to = '+919894753728';

            $pdfUrl       = $this->makePublicUrl($relativePdfPath);
            $templateName = 'manager_client_purchase_notify';

            $templateData = [
                'name'      => $templateName,
                'language'  => 'en_US',
                'components'=> [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link'     => $pdfUrl,
                                    'filename' => basename($relativePdfPath),
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $manager->name],
                        ],
                    ],
                ],
            ];

            $wa   = new WhatsAppWebService();
            $resp = $wa->sendTemplateMessage($to, $templateData);

            return [
                'ok'      => (bool)($resp['ok'] ?? true),
                'request' => ['to' => $to, 'template' => $templateName, 'pdf' => $pdfUrl],
                'resp'    => is_array($resp) ? $resp : ['raw' => $resp],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
