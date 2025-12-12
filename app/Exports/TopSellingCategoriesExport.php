<?php
namespace App\Exports;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TopSellingCategoriesExport implements FromCollection, WithHeadings, WithStyles
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $sort_search = $this->request->input('search', null);
        $user = auth()->user();

        $query = User::query()
            ->with([
                'warehouse:id,name',
                'manager:id,name',
                'address_by_party_code:id,city,user_id,acc_code,due_amount,overdue_amount',
                'total_due_amounts'
            ])
            ->whereIn('user_type', ['customer'])
            ->whereNotNull('email_verified_at');

        if (!in_array($user->id, ['180', '25606', '169']) && $user->user_type != 'admin') {
            $query->where('manager_id', $user->id);
        }

        if ($sort_search) {
            $query->where(function ($q) use ($sort_search) {
                $q->where('party_code', 'like', "%$sort_search%")
                    ->orWhere('phone', 'like', "%$sort_search%")
                    ->orWhere('name', 'like', "%$sort_search%")
                    ->orWhereHas('address_by_party_code', fn($subQuery) => $subQuery->where('city', 'like', "%$sort_search%"));
            });
        }

        $users = $query->get();
        $exportData = [];

        foreach ($users as $user) {
            $orderIds = Order::where('user_id', $user->id)->pluck('id');

            $categorySpending = OrderDetail::select(
                    'products.category_id',
                    DB::raw('SUM(order_details.price) as total_spent'),
                    DB::raw('MAX(order_details.created_at) as latest_purchase_date')
                )
                ->join('products', 'order_details.product_id', '=', 'products.id')
                ->whereIn('order_details.order_id', $orderIds)
                ->groupBy('products.category_id')
                ->orderByDesc('total_spent')
                ->get();

            $user->total_categories_amount = $categorySpending->sum('total_spent');
            $topCategories = $categorySpending->take(5);

            $categoryDetails = [];
            foreach ($topCategories as $item) {
                $categoryName = Category::find($item->category_id)->name ?? 'Unknown';
                $categoryDetails[] = [
                    "category_name" => $categoryName,
                    "total_spent" => $item->total_spent,
                    "latest_purchase_date" => $item->latest_purchase_date
                ];
            }

            $address = Address::where('user_id', $user->id)->first();

            $exportData[] = [
                'Company Name'                => $user->name,
                'Party Code'                  => $user->party_code,
                'Manager Name'                => isset($user->manager) ? $user->manager->name : 'Unassigned', // ✅ Added Manager
                'Due Amount'                  => $address->due_amount ?? '₹0.00',
                'Overdue Amount'              => $address->overdue_amount ?? '₹0.00',
                'Total Categories Amount'     => $user->total_categories_amount ?? 0,
                'Last Purchased Category Date'=> optional($categorySpending->first())->latest_purchase_date ?? 'N/A',
                'Top 5 Categories'            => json_encode($categoryDetails, JSON_UNESCAPED_UNICODE), // 🔹 Convert array to JSON
            ];
        }

        // 🔹 Sort Data in Descending Order Based on "Total Categories Amount"
        $sortedData = collect($exportData)->sortByDesc('Total Categories Amount')->values();

        return $sortedData;
    }

    public function headings(): array
    {
        return [
            "Company Name",
            "Party Code",
            "Manager Name", 
            "Due Amount",
            "Overdue Amount",
            "Total Categories Amount",
            "Last Purchased Category Date",
            "Top 5 Categories (JSON Format)"
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Make first row (headings) bold
            1 => ['font' => ['bold' => true]],
        ];
    }
}
