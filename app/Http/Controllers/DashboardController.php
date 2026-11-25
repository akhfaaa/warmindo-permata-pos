<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Ingredient;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        [$startDate, $endDate, $preset] = $this->resolveDateRange($request);

        $paidTransactions = Transaction::with(['items.product', 'user'])
            ->where('status', 'PAID')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        $voidCount = Transaction::where('status', 'VOID')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalOmzet = (float) $paidTransactions->sum('total_amount');
        $totalTxCount = $paidTransactions->count();
        $avgOrderValue = $totalTxCount > 0 ? ($totalOmzet / $totalTxCount) : 0;

        $totalHpp = 0.0;
        foreach ($paidTransactions as $tx) {
            foreach ($tx->items as $item) {
                $totalHpp += (float) $item->item_hpp_snapshot * (int) $item->quantity;
            }
        }

        $grossProfit = $totalOmzet - $totalHpp;
        $grossMarginPercent = $totalOmzet > 0 ? round(($grossProfit / $totalOmzet) * 100, 1) : 0;

        // Rincian Metode Pembayaran
        $tunaiTx = $paidTransactions->where('payment_method', 'TUNAI');
        $qrisTx = $paidTransactions->where('payment_method', 'QRIS');

        $paymentBreakdown = [
            'tunai_amount' => (float) $tunaiTx->sum('total_amount'),
            'tunai_count' => $tunaiTx->count(),
            'tunai_pct' => $totalOmzet > 0 ? round(($tunaiTx->sum('total_amount') / $totalOmzet) * 100, 1) : 0,
            'qris_amount' => (float) $qrisTx->sum('total_amount'),
            'qris_count' => $qrisTx->count(),
            'qris_pct' => $totalOmzet > 0 ? round(($qrisTx->sum('total_amount') / $totalOmzet) * 100, 1) : 0,
        ];

        // Rincian Tipe Pesanan
        $dineInTx = $paidTransactions->where('order_type', 'DINE_IN');
        $takeawayTx = $paidTransactions->where('order_type', 'TAKEAWAY');

        $orderTypeBreakdown = [
            'dine_in_amount' => (float) $dineInTx->sum('total_amount'),
            'dine_in_count' => $dineInTx->count(),
            'takeaway_amount' => (float) $takeawayTx->sum('total_amount'),
            'takeaway_count' => $takeawayTx->count(),
        ];

        // Tren Harian (Omzet & Laba Kotor per Tanggal)
        $dailyGrouped = $paidTransactions->groupBy(fn($tx) => $tx->created_at->format('Y-m-d'));
        $dailyTrend = [];
        $maxDailyOmzet = 0.0;

        foreach ($dailyGrouped as $dateKey => $dayTxs) {
            $dayOmzet = (float) $dayTxs->sum('total_amount');
            $dayHpp = 0.0;
            foreach ($dayTxs as $tx) {
                foreach ($tx->items as $item) {
                    $dayHpp += (float) $item->item_hpp_snapshot * (int) $item->quantity;
                }
            }
            $dayProfit = $dayOmzet - $dayHpp;
            if ($dayOmzet > $maxDailyOmzet) {
                $maxDailyOmzet = $dayOmzet;
            }

            $dailyTrend[] = [
                'date' => Carbon::parse($dateKey)->format('d M'),
                'full_date' => $dateKey,
                'tx_count' => $dayTxs->count(),
                'omzet' => $dayOmzet,
                'hpp' => $dayHpp,
                'profit' => $dayProfit,
            ];
        }

        // Top 5 Menu Terlaris pada Periode Terpilih
        $topProducts = TransactionItem::query()
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(unit_price * quantity) as total_revenue'),
                DB::raw('SUM(item_hpp_snapshot * quantity) as total_cogs')
            )
            ->whereHas('transaction', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'PAID')
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $rev = (float) $row->total_revenue;
                $cogs = (float) $row->total_cogs;
                return [
                    'name' => $row->product?->name ?? 'Menu Dihapus',
                    'qty' => (int) $row->total_qty,
                    'revenue' => $rev,
                    'profit' => $rev - $cogs,
                ];
            });

        // Peringatan Stok Kritis & Log Tutup Kasir Terakhir
        // Peringatan Stok Kritis & Log Tutup Kasir Terakhir
        $minStockColumn = collect(['min_stock', 'minimum_stock', 'min_stock_threshold', 'stock_threshold'])
            ->first(fn(string $col) => Schema::hasColumn('ingredients', $col));

        $lowStockIngredients = $minStockColumn
            ? Ingredient::whereColumn('current_stock', '<=', $minStockColumn)
            ->orderByRaw("(current_stock - {$minStockColumn}) asc")
            ->get()
            : collect();

        $recentShifts = CashShift::with('user')
            ->where('status', 'CLOSED')
            ->latest('closed_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'preset' => $preset,
            'startDateInput' => $startDate->format('Y-m-d'),
            'endDateInput' => $endDate->format('Y-m-d'),
            'kpis' => [
                'omzet' => $totalOmzet,
                'hpp' => $totalHpp,
                'profit' => $grossProfit,
                'margin_pct' => $grossMarginPercent,
                'tx_count' => $totalTxCount,
                'void_count' => $voidCount,
                'avg_order' => $avgOrderValue,
            ],
            'paymentBreakdown' => $paymentBreakdown,
            'orderTypeBreakdown' => $orderTypeBreakdown,
            'dailyTrend' => $dailyTrend,
            'maxDailyOmzet' => $maxDailyOmzet,
            'topProducts' => $topProducts,
            'lowStockIngredients' => $lowStockIngredients,
            'recentShifts' => $recentShifts,
        ]);
    }

    public function exportCsv(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $transactions = Transaction::with(['items.product', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        $filename = sprintf(
            'laporan-warmindo-permata-%s-sd-%s.csv',
            $startDate->format('Ymd'),
            $endDate->format('Ymd')
        );

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM agar karakter Rupiah & kolom terbuka rapi di Microsoft Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No. Nota',
                'Waktu Transaksi',
                'Kasir',
                'Tipe Pesanan',
                'Metode Pembayaran',
                'Status',
                'Rincian Item',
                'Omzet (Rp)',
                'Estimasi HPP (Rp)',
                'Estimasi Laba Kotor (Rp)',
                'Catatan Void',
            ]);

            foreach ($transactions as $tx) {
                $txHpp = 0.0;
                $itemSummaries = [];

                foreach ($tx->items as $item) {
                    $txHpp += (float) $item->item_hpp_snapshot * (int) $item->quantity;
                    $modNames = collect($item->modifiers_json ?? [])->pluck('name')->implode(', ');
                    $label = "{$item->quantity}x " . ($item->product?->name ?? 'Menu');
                    if ($modNames !== '') {
                        $label .= " (+{$modNames})";
                    }
                    $itemSummaries[] = $label;
                }

                $isPaid = $tx->status === 'PAID';
                $omzet = $isPaid ? (float) $tx->total_amount : 0.0;
                $hpp = $isPaid ? round($txHpp, 2) : 0.0;
                $profit = $isPaid ? round($omzet - $hpp, 2) : 0.0;

                fputcsv($handle, [
                    $tx->invoice_number,
                    $tx->created_at->format('Y-m-d H:i:s'),
                    $tx->user?->name ?? '-',
                    $tx->order_type,
                    $tx->payment_method,
                    $tx->status,
                    implode(' | ', $itemSummaries),
                    $omzet,
                    $hpp,
                    $profit,
                    $tx->void_reason ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $preset = $request->query('preset', 'today');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $start = Carbon::parse($request->query('start_date'))->startOfDay();
            $end = Carbon::parse($request->query('end_date'))->endOfDay();
            return [$start, $end, 'custom'];
        }

        return match ($preset) {
            '7days' => [now()->subDays(6)->startOfDay(), now()->endOfDay(), '7days'],
            '30days' => [now()->subDays(29)->startOfDay(), now()->endOfDay(), '30days'],
            default => [now()->startOfDay(), now()->endOfDay(), 'today'],
        };
    }
}
