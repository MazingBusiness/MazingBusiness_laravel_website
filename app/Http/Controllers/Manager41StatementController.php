<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Manager41Challan;
use App\Models\Manager41PurchaseInvoice;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Address;
use App\Jobs\GenerateStatementPdf;

class Manager41StatementController extends Controller
{
    /**
     * Statement page for 41 Manager (filters + table).
     * Same UX as Admin Statement, but locked to the logged-in 41 Manager.
     */
    public function index(Request $request){
        $me = auth()->user();
    
        // Lock to current 41 manager
        $managerId   = (int) ($request->manager_id ?: $me->id);
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : 0;
        $search      = trim((string) $request->search);
        $df          = trim((string) $request->duefilter);
        $sortBy      = $request->get('sort_by');
        $sortOrder   = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
    
        // Managers dropdown = only me
        $managers = User::where('id', $me->id)->select('id','name')->get();
    
        // Warehouses (adjust as you wish)
        $warehouses = Warehouse::where('active', 1)->orderBy('name')->get();
    
        // Base: only my customers
        // Base: addresses that have a Manager-41 snapshot
        $customersQuery = DB::table('users')
            ->join('addresses', 'addresses.user_id', '=', 'users.id')
            ->leftJoin('users as managers', 'managers.id', '=', 'users.manager_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'users.warehouse_id')
            ->whereNotNull('addresses.statement_41_data')
            ->whereRaw("addresses.statement_41_data <> ''"); // non-empty JSON
    
        if ($warehouseId) {
            $customersQuery->where('users.warehouse_id', $warehouseId);
        }
    
        if ($search !== '') {
            $like = "%{$search}%";
            $customersQuery->where(function ($q) use ($like) {
                $q->where('addresses.acc_code','like',$like)
                  ->orWhere('addresses.company_name','like',$like)
                  ->orWhere('addresses.city','like',$like);
            });
        }
    
        // Due / Overdue quick filters
        if ($df === 'due') {
            $customersQuery->whereRaw("CAST(NULLIF(addresses.due_amount,'') AS DECIMAL(12,2)) > 0");
        } elseif ($df === 'overdue') {
            $customersQuery->whereRaw("CAST(NULLIF(addresses.overdue_amount,'') AS DECIMAL(12,2)) > 0");
        }
    
        // Totals before pagination
        $filtered = (clone $customersQuery)->select('addresses.due_amount','addresses.overdue_amount')->get();
    
        $totalDueAmount = $filtered->sum(function ($r) {
            return is_numeric($r->due_amount) ? (float) $r->due_amount : 0.0;
        });
        $totalOverdueAmount = $filtered->sum(function ($r) {
            return is_numeric($r->overdue_amount) ? (float) $r->overdue_amount : 0.0;
        });
    
        // Sorting
        $validSort = ['credit_days','credit_limit','city','due_amount_numeric','overdue_amount_numeric'];
        if ($sortBy && in_array($sortBy, $validSort, true)) {
            if ($sortBy === 'city') {
                $customersQuery->orderByRaw("LOWER(addresses.city) {$sortOrder}");
            } elseif (in_array($sortBy, ['credit_days','credit_limit'], true)) {
                $customersQuery->orderBy("users.{$sortBy}", $sortOrder);
            } elseif ($sortBy === 'due_amount_numeric') {
                $customersQuery->orderByRaw("CAST(NULLIF(addresses.due_amount,'') AS DECIMAL(12,2)) {$sortOrder}");
            } elseif ($sortBy === 'overdue_amount_numeric') {
                $customersQuery->orderByRaw("CAST(NULLIF(addresses.overdue_amount,'') AS DECIMAL(12,2)) {$sortOrder}");
            }
        } else {
            $customersQuery->orderBy('addresses.company_name', 'asc');
        }
    
        // Final list (+ pull statement JSON from address)
        $customers = $customersQuery
            ->select(
                'users.id',
                'users.phone',
                'users.manager_id',
                'users.credit_days',
                'users.credit_limit',
                'addresses.company_name',
                'addresses.acc_code',
                'addresses.city',
                DB::raw("CAST(NULLIF(addresses.due_amount,'') AS DECIMAL(12,2)) as due_amount_numeric"),
                DB::raw("CAST(NULLIF(addresses.overdue_amount,'') AS DECIMAL(12,2)) as overdue_amount_numeric"),
                // 👇 read saved statement JSON; support either column name
                DB::raw("COALESCE(NULLIF(addresses.statement_41_data,''), NULLIF(addresses.statement_41_data,'')) as statement_41_data_json"),
                'managers.name as manager_name',
                'warehouses.name as warehouse_name'
            )
            ->paginate(50)
            ->appends($request->query());
    
        // Attach decoded statement + quick summaries to each row
        $customers->getCollection()->transform(function ($row) {
            $raw = $row->statement_41_data_json ?? '';
            $decoded = [];
            if ($raw !== null && $raw !== '') {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) {
                    $decoded = $tmp;
                }
            }
            // Make it available to Blade/JS
            $row->statement_41 = $decoded;
    
            // Optional helpers: last txn date + closing balance from the JSON
            if (!empty($decoded)) {
                $last = end($decoded);
                $row->statement_41_last_date       = $last['trn_date'] ?? null;
                $row->statement_41_closing_balance = $last['running_balance'] ?? null; // already a string in our saver
            } else {
                $row->statement_41_last_date       = null;
                $row->statement_41_closing_balance = null;
            }
    
            return $row;
        });
    
        // Counts
        $totalCustomerCount = (clone $customersQuery)->count('users.id');
        $totalCustomersWithDueOrOverdue = (clone $customersQuery)
            ->where(function($q){
                $q->whereRaw("CAST(NULLIF(addresses.due_amount,'') AS DECIMAL(12,2)) > 0")
                  ->orWhereRaw("CAST(NULLIF(addresses.overdue_amount,'') AS DECIMAL(12,2)) > 0");
            })
            ->count('users.id');
    
        // Bucket placeholders (if you add later)
        $overdueBucketThreshold   = null;
        $totalOverdueBucketAmount = 0;
        $totalOverdue60Amount     = 0;
        $totalOverdue90Amount     = 0;
        $totalOverdue120Amount    = 0;
    
        return view('backend.manager41.statement', compact(
            'managers',
            'warehouses',
            'customers',
            'totalDueAmount',
            'totalOverdueAmount',
            'totalCustomerCount',
            'totalCustomersWithDueOrOverdue',
            'overdueBucketThreshold',
            'totalOverdueBucketAmount',
            'totalOverdue60Amount',
            'totalOverdue90Amount',
            'totalOverdue120Amount'
        ));
    }

    /**
     * JSON statement (Sales & Purchase only) for a user (used by PDF or other clients).
     */
    public function statementData(Request $request, int $userId){
        // Build the Manager-41 statement
        $rows = (array) ($this->buildManager41Statement($userId) ?? []);
    
        // Format like your sample
        $rows = array_map(function ($r) {
            $r['dramount']        = number_format((float)($r['dramount'] ?? 0), 2, '.', '');
            $r['cramount']        = number_format((float)($r['cramount'] ?? 0), 2, '.', '');
            $r['running_balance'] = number_format((float)($r['running_balance'] ?? 0), 2, '.', '');
            $r['ledgername']      = ($r['ledgername'] ?? '') === '' ? null : $r['ledgername'];
            $r['ledgerid']        = (($r['ledgerid'] ?? '') === '' || (int)$r['ledgerid'] === 0) ? null : $r['ledgerid'];
            return $r;
        }, $rows);
    
        // Optional: restrict to a specific party
        $accCode = trim((string) $request->query('acc_code', ''));
    
        // JSON to persist
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
        // ---- Update ONLY the first address row (earliest id) for this user (and acc_code if provided) ----
        $first = DB::table('addresses')
            ->select('id', 'acc_code')
            ->where('user_id', $userId)
            ->when($accCode !== '', function ($q) use ($accCode) {
                $q->where('acc_code', $accCode);
            })
            ->orderBy('id', 'asc')
            ->first();
    
        if (!$first) {
            return response()->json([
                'success'  => false,
                'message'  => 'No address row found to update for this user.',
                'user_id'  => $userId,
                'acc_code' => $accCode ?: null,
            ], 404);
        }
    
        $affected = DB::table('addresses')
            ->where('id', $first->id)
            ->update(['statement_41_data' => $json]);
    
        return response()->json([
            'success'       => (bool) $affected,
            'saved_to_rows' => (int) $affected,   // 0 or 1
            'updated_row_id'=> $first->id,
            'user_id'       => $userId,
            'acc_code_used' => $accCode !== '' ? $accCode : $first->acc_code,
            'count'         => count($rows),
            'data'          => $rows,
        ]);
    }


    // --- AJAX helpers used by the Blade ---

    public function getCitiesByManager(Request $request)
    {
        $managerId = (int) ($request->manager_id ?: auth()->id());

        $cities = Address::query()
            ->join('users','users.id','=','addresses.user_id')
            ->where('users.manager_id', $managerId)
            ->whereNotNull('addresses.city')
            ->where('addresses.city','!=','')
            ->distinct()
            ->pluck('addresses.city')
            ->values();

        return response()->json($cities);
    }

    // Stub endpoints — wire to your existing logic/services as needed:
    public function getAllUsersData(Request $request)
    {
        // Return users for WhatsApp-all flow respecting filters
        // Implement like your Admin version but constrained to auth()->id()
        return response()->json(['success' => true, 'group_id' => 'group_'.uniqid()]);
    }

    public function processWhatsapp(Request $request)
    {
        // Kick off your WA sender for the provided group_id
        return response()->json(['group_id' => $request->group_id]);
    }

    public function generateStatementPdfBulkChecked(Request $request)
    {
        // Use your existing PDF generator for the selected users/party_codes
        return response()->json(['success' => true]);
    }

    public function sync(Request $request)
    {
        // Call your existing sync code for selected_data
        return response()->json(['success' => true]);
    }

    // === Core builder used by statementData ===
    private function buildManager41Statement(int $userId): array
    {
        $sales = Manager41Challan::query()
            ->where('user_id', $userId)
            ->select(['challan_no','challan_date','grand_total'])
            ->get()
            ->map(function($c){
                return [
                    'trn_no'              => (string) ($c->challan_no ?? ''),
                    'trn_date'            => $c->challan_date ? Carbon::parse($c->challan_date)->toDateString() : '',
                    'vouchertypebasename' => 'Sales',
                    'ledgername'          => '',
                    'ledgerid'            => '',
                    'dramount'            => round((float) ($c->grand_total ?? 0), 2),
                    'cramount'            => 0.0,
                    'narration'           => $c->challan_no ?: '',
                ];
            });

        $purchases = Manager41PurchaseInvoice::query()
            ->with(['purchaseInvoiceDetails','address'])
            ->whereHas('address', fn($q) => $q->where('user_id', $userId))
            ->get()
            ->map(function($inv){
                $total = 0.0;
                foreach ($inv->purchaseInvoiceDetails as $d) {
                    foreach (['gross_amt','billed_amt','final_amount','total_amount','total'] as $k) {
                        if (isset($d[$k]) && $d[$k] !== null) {
                            $total += (float) $d[$k];
                            continue 2;
                        }
                    }
                    $qty  = (float) ($d['billed_qty'] ?? $d['qty'] ?? $d['quantity'] ?? 0);
                    $rate = (float) ($d['rate'] ?? $d['price'] ?? 0);
                    $total += $qty * $rate;
                }

                $trnNo = $inv->purchase_no ?: ($inv->seller_invoice_no ?: '');
                $date  = $inv->seller_invoice_date ? Carbon::parse($inv->seller_invoice_date)->toDateString() : '';

                return [
                    'trn_no'              => (string) $trnNo,
                    'trn_date'            => $date,
                    'vouchertypebasename' => 'Purchase',
                    'ledgername'          => '',
                    'ledgerid'            => '',
                    'dramount'            => 0.0,
                    'cramount'            => round($total, 2),
                    'narration'           => $trnNo ?: '',
                ];
            });

        $rows = $sales->merge($purchases)->all();

        usort($rows, function ($a, $b) {
            return [$a['trn_date'],$a['trn_no']] <=> [$b['trn_date'],$b['trn_no']];
        });

        $running = 0.0;
        foreach ($rows as &$r) {
            $running += ((float)$r['dramount']) - ((float)$r['cramount']);
            $r['running_balance'] = number_format($running, 2, '.', '');
        }
        unset($r);

        return $rows;
    }
    
    public function createPdf(string $party_code)
    {
        // ---- Date window (FY Apr–Mar) with ?from_date & ?to_date overrides ----
        $today       = date('Y-m-d');
        $m           = (int) date('m');
        if ($m >= 4) {
            $from_date = date('Y-04-01');
            $to_date   = date('Y-03-31', strtotime('+1 year'));
        } else {
            $from_date = date('Y-04-01', strtotime('-1 year'));
            $to_date   = date('Y-03-31');
        }
        if (request()->filled('from_date')) $from_date = request('from_date');
        if (request()->filled('to_date'))   $to_date   = request('to_date');
        if ($to_date > $today) $to_date = $today;
    
        // ---- Locate address & user for this party code ----
        $addr = Address::where('acc_code', $party_code)->first();
        if (!$addr) {
            return response()->json(['error' => 'Party not found'], 404);
        }
        $user = User::find($addr->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
    
        // Address bits for the PDF header
        $address     = $addr->address ?? 'Address not found';
        $address_2   = $addr->address_2 ?? '';
        $postal_code = $addr->postal_code ?? '';
    
        // ---- Collect saved snapshots for THIS GSTIN for the same user ----
        $statementBuckets = [];
        $sameGstinAddresses = Address::where('user_id', $user->id)
            ->where('gstin', $addr->gstin)
            ->orderBy('acc_code', 'ASC')
            ->get();
    
        foreach ($sameGstinAddresses as $a) {
            // Prefer statement_41_data, then statement_data_41, then legacy statement_data
            $raw = $a->statement_41_data ?: ($a->statement_data_41 ?: $a->statement_data);
            if (!$raw) continue;
    
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) continue;
    
            // drop closing row(s) — we’ll recompute
            $filtered = array_filter($decoded, function($row){
                return !isset($row['ledgername']) || stripos((string)$row['ledgername'], 'closing C/f...') === false;
            });
    
            $statementBuckets[$a->id] = array_values($filtered);
        }
    
        // ---- Merge + sort by trn_date ----
        $merged = [];
        foreach ($statementBuckets as $rows) {
            $merged = array_merge($merged, $rows);
        }
        usort($merged, function($x, $y){
            return strtotime($x['trn_date']) <=> strtotime($y['trn_date']);
        });
        $statement_data = array_values($merged);
    
        // ---- Recompute running balance + append closing C/f ----
        $balance = 0.0;
        foreach ($statement_data as $i => $row) {
            if (($row['ledgername'] ?? '') === 'Opening b/f...') {
                // opening dr positive, opening cr negative (same convention as your code)
                $balance = ((float)$row['dramount'] != 0.00)
                    ? (float)$row['dramount']
                    : -(float)$row['cramount'];
            } else {
                $balance += (float)$row['dramount'] - (float)$row['cramount'];
            }
            $statement_data[$i]['running_balance'] = $balance;
        }
    
        // push a fresh closing C/f row
        $closing = [
            'trn_no'               => '',
            'trn_date'             => date('Y-m-d'),
            'vouchertypebasename'  => '',
            'ledgername'           => 'closing C/f...',
            'ledgerid'             => '',
            'dramount'             => 0.00,
            'cramount'             => 0.00,
            'narration'            => '',
        ];
        if ($balance >= 0) {
            // keep behavior aligned with your original code
            $closing['cramount'] = (float)$balance;
            $closing['dramount'] = 0.00;
        } else {
            $closing['dramount'] = (float)$balance;
            $closing['cramount'] = 0.00;
        }
        $statement_data[] = $closing;
    
        // ---- Overdue calculation (same logic/shape you had) ----
        $overDueMark   = [];
        $overdueAmount = 0.0;
        $overdueDrOrCr = 'Dr';
    
        if (!empty($statement_data)) {
            $closingRow = array_values(array_filter($statement_data, function($r){
                return ($r['ledgername'] ?? '') === 'closing C/f...';
            }));
            $closingRow = $closingRow[0] ?? null;
            $closingCr  = (float)($closingRow['cramount'] ?? 0);
            $overdueDateFrom = date('Y-m-d', strtotime('-' . ((int)$user->credit_days) . ' days'));
    
            if ($closingCr > 0) {
                $drBefore = 0.0; $crBefore = 0.0;
                $rev = array_reverse($statement_data);
                foreach ($rev as $r) {
                    if (($r['ledgername'] ?? '') === 'closing C/f...') continue;
                    if (strtotime($r['trn_date']) > strtotime($overdueDateFrom)) {
                        $crBefore += (float)$r['cramount'];
                    } else {
                        $drBefore += (float)$r['dramount'];
                        $crBefore += (float)$r['cramount'];
                    }
                }
                $overdueAmount = $tmp = $drBefore - $crBefore;
    
                foreach ($rev as $r) {
                    if (($r['ledgername'] ?? '') === 'closing C/f...') continue;
                    if (strtotime($r['trn_date']) <= strtotime($overdueDateFrom) && $tmp > 0 && (float)$r['dramount'] != 0.00) {
                        $tmp -= (float)$r['dramount'];
                        $d1  = $r['trn_date']; $d2 = $overdueDateFrom;
                        $days = floor(abs(strtotime($d2) - strtotime($d1)) / 86400) . ' days';
                        $overDueMark[] = [
                            'trn_no'         => $r['trn_no'],
                            'trn_date'       => $r['trn_date'],
                            'overdue_by_day' => $days,
                            'overdue_staus'  => ($tmp >= 0) ? 'Overdue' : 'Partial Overdue',
                        ];
                    }
                }
            }
    
            if ($overdueAmount <= 0) { $overdueDrOrCr = 'Cr'; $overdueAmount = 0; }
            else                     { $overdueDrOrCr = 'Dr'; }
        }
    
        // ---- Opening/Closing + due amount + attach overdue marks to rows ----
        $openingBalance = "0"; $openDrOrCr = ""; $closingBalance = "0"; $closeDrOrCr = "";
        $drSum = 0.0; $crSum = 0.0; $dueAmount = 0.0;
    
        $odNos   = array_column($overDueMark, 'trn_no');
        $odStat  = array_column($overDueMark, 'overdue_staus');
        $odDays  = array_column($overDueMark, 'overdue_by_day');
    
        foreach ($statement_data as $k => $row) {
            if (($row['ledgername'] ?? '') === 'Opening b/f...') {
                if ((float)$row['dramount'] != 0.00) {
                    $openingBalance = $row['dramount']; $openDrOrCr = "Dr";
                } else {
                    $openingBalance = $row['cramount']; $openDrOrCr = "Cr";
                }
            } elseif (($row['ledgername'] ?? '') === 'closing C/f...') {
                if ((float)$row['dramount'] != 0.00) { $closingBalance = $row['dramount']; }
                else                                 { $closingBalance = $row['cramount']; }
            }
    
            // attach overdue flags if any
            $idx = array_search($row['trn_no'], $odNos, true);
            if ($idx !== false) {
                $statement_data[$k]['overdue_status'] = $odStat[$idx];
                $statement_data[$k]['overdue_by_day'] = $odDays[$idx];
            } else {
                unset($statement_data[$k]['overdue_status'], $statement_data[$k]['overdue_by_day']);
            }
    
            // compute due amount (exclude closing row)
            if (($row['ledgername'] ?? '') !== 'closing C/f...') {
                $drSum += (float)$row['dramount'];
                $crSum += (float)$row['cramount'];
                $dueAmount = $drSum - $crSum;
            }
        }
        $closeDrOrCr = ($drSum > $crSum) ? 'Dr' : 'Cr';
    
        // ---- Prepare & dispatch PDF job (same view/job you already use) ----
        $fileName = 'statement-' . $party_code . '-' . str_replace('.', '', microtime(true)) . '.pdf';
    
        $payload = [
            'userData'        => $user,
            'party_code'      => $party_code,
            'statementData'   => $statement_data,
            'openingBalance'  => $openingBalance,
            'openDrOrCr'      => $openDrOrCr,
            'closingBalance'  => $closingBalance,
            'closeDrOrCr'     => $closeDrOrCr,
            'form_date'       => $from_date,
            'to_date'         => $to_date,
            'overdueAmount'   => $overdueAmount,
            'overdueDrOrCr'   => $overdueDrOrCr,
            'dueAmount'       => $dueAmount,
            'address'         => $address,
            'address_2'       => $address_2,
            'postal_code'     => $postal_code,
        ];
    
        GenerateStatementPdf::dispatch($payload, $fileName);
    
        return response()->json([
            'pdf_url' => url('public/statements/'.$fileName),
        ]);
    }
}