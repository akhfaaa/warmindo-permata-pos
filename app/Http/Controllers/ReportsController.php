<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsController extends Controller
{
    private const REPORT_TITLES = [
        'daily_sales'         => 'Laporan Penjualan Harian & Metode Pembayaran',
        'menu_performance'    => 'Laporan Performa & Peringkat Menu Terlaris',
        'cashflow'            => 'Laporan Arus Kas & Laba Rugi Operasional',
        'inventory_valuation' => 'Laporan Valuasi Aset & Mutasi Stok Bahan Baku',
        'cash_shifts'         => 'Laporan Audit Shift & Selisih Laci Kasir',
        'peak_hours'          => 'Laporan Analisis Jam Ramai Transaksi (Peak Hours)',
    ];

    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'daily_sales');
        if (! array_key_exists($activeTab, self::REPORT_TITLES)) {
            $activeTab = 'daily_sales';
        }

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $data = $this->buildReportData($startDate, $endDate);

        return view('reports.index', array_merge($data, [
            'activeTab' => $activeTab,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate'   => $endDate->format('Y-m-d'),
        ]));
    }

    public function exportPdf(Request $request)
    {
        $type = $request->query('type', 'daily_sales');
        if (! array_key_exists($type, self::REPORT_TITLES)) {
            $type = 'daily_sales';
        }

        $orientation = strtolower((string) $request->query('orientation', 'portrait'));
        if (! in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }

        $mode = strtolower((string) $request->query('mode', 'stream'));

        [$startDate, $endDate] = $this->resolveDateRange($request);
        $data = $this->buildReportData($startDate, $endDate);

        $viewData = array_merge($data, [
            'reportType'  => $type,
            'reportTitle' => self::REPORT_TITLES[$type],
            'startDate'   => $startDate->format('Y-m-d'),
            'endDate'     => $endDate->format('Y-m-d'),
            'periodLabel' => $startDate->translatedFormat('d M Y') . ' s/d ' . $endDate->translatedFormat('d M Y'),
            'printedAt'   => now()->translatedFormat('d M Y H:i') . ' WITA',
            'printedBy'   => $request->user()?->name ?? 'Administrator',
            'orientation' => $orientation,
        ]);

        $pdf = Pdf::loadView('reports.pdf', $viewData)
            ->setPaper('a4', $orientation)
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);

        $periodSlug = $startDate->format('Ymd') . '_' . $endDate->format('Ymd');
        $filename = "Warmindo_Permata_{$type}_{$periodSlug}.pdf";

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function exportCsv(Request $request)
    {
        $type = $request->query('type', 'daily_sales');
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $data = $this->buildReportData($startDate, $endDate);

        $periodSlug = $startDate->format('Ymd') . '_' . $endDate->format('Ymd');
        $filename = "laporan_{$type}_{$periodSlug}.csv";

        return response()->streamDownload(function () use ($type, $data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            switch ($type) {
                case 'menu_performance':
                    fputcsv($handle, ['Peringkat', 'Nama Menu', 'Kategori', 'Harga Jual', 'Est. HPP/Porsi', 'Terjual (Porsi)', 'Total Omzet', 'Total Est. HPP', 'Est. Laba Kotor', 'Kontribusi (%)']);
                    foreach ($data['menuReport'] as $idx => $row) {
                        fputcsv($handle, [
                            $idx + 1,
                            $row['name'],
                            $row['category'],
                            $row['price'],
                            $row['unit_hpp'],
                            $row['qty_sold'],
                            $row['revenue'],
                            $row['total_hpp'],
                            $row['gross_profit'],
                            number_format($row['share_pct'], 2, '.', ''),
                        ]);
                    }
                    break;

                case 'cashflow':
                    fputcsv($handle, ['Tanggal', 'Omzet Penjualan', 'Pengeluaran Belanja', 'Selisih Bersih']);
                    foreach ($data['cashflowDaily'] as $row) {
                        fputcsv($handle, [
                            $row['tanggal'],
                            $row['sales'],
                            $row['expenses'],
                            $row['net'],
                        ]);
                    }
                    break;

                case 'inventory_valuation':
                    fputcsv($handle, ['Nama Bahan Baku', 'Satuan', 'Stok Tersedia', 'Batas Minimum', 'Harga Beli/Satuan', 'Nilai Aset Stok (Rp)', 'Masuk Periode Ini', 'Keluar Periode Ini', 'Status']);
                    foreach ($data['inventoryReport'] as $row) {
                        fputcsv($handle, [
                            $row['name'],
                            $row['unit'],
                            $row['current_stock'],
                            $row['min_stock'],
                            $row['unit_cost'],
                            $row['asset_value'],
                            $row['qty_in'],
                            $row['qty_out'],
                            $row['is_low'] ? 'Perlu Belanja' : 'Aman',
                        ]);
                    }
                    break;

                case 'cash_shifts':
                    fputcsv($handle, ['Waktu Buka', 'Waktu Tutup', 'Kasir', 'Modal Awal', 'Kas Sistem', 'Kas Aktual Fisik', 'Selisih Kas', 'Status']);
                    foreach ($data['shiftReport'] as $row) {
                        fputcsv($handle, [
                            $row['opened_at'],
                            $row['closed_at'],
                            $row['cashier_name'],
                            $row['opening_cash'],
                            $row['expected_cash'],
                            $row['actual_cash'],
                            $row['difference'],
                            $row['status'],
                        ]);
                    }
                    break;

                case 'peak_hours':
                    fputcsv($handle, ['Jam Operasional', 'Jumlah Transaksi', 'Porsi Terjual', 'Total Omzet']);
                    foreach ($data['peakHoursReport'] as $row) {
                        fputcsv($handle, [
                            $row['hour_label'],
                            $row['tx_count'],
                            $row['portions'],
                            $row['revenue'],
                        ]);
                    }
                    break;

                case 'daily_sales':
                default:
                    fputcsv($handle, ['Tanggal', 'Nota Sukses', 'Nota Void', 'Porsi Terjual', 'Pemasukan Tunai (CASH)', 'Pemasukan QRIS / Non-Tunai', 'Total Omzet']);
                    foreach ($data['dailySalesReport'] as $row) {
                        fputcsv($handle, [
                            $row['tanggal'],
                            $row['paid_count'],
                            $row['void_count'],
                            $row['portions'],
                            $row['cash_total'],
                            $row['qris_total'],
                            $row['total_omzet'],
                        ]);
                    }
                    break;
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $startInput = $request->query('start_date');
        $endInput   = $request->query('end_date');

        try {
            $startDate = $startInput
                ? Carbon::parse($startInput)->startOfDay()
                : now()->startOfMonth()->startOfDay();
        } catch (\Throwable $e) {
            $startDate = now()->startOfMonth()->startOfDay();
        }

        try {
            $endDate = $endInput
                ? Carbon::parse($endInput)->endOfDay()
                : now()->endOfDay();
        } catch (\Throwable $e) {
            $endDate = now()->endOfDay();
        }

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    private function buildReportData(Carbon $startDate, Carbon $endDate): array
    {
        $userNames = User::pluck('name', 'id');

        $txTotalCol = collect(['total_amount', 'grand_total', 'total', 'final_amount'])
            ->first(fn (string $col) => Schema::hasColumn('transactions', $col));
        $txPayCol = collect(['payment_method', 'method', 'payment_type'])
            ->first(fn (string $col) => Schema::hasColumn('transactions', $col));

        $allTransactions = Transaction::with(['items.product.category'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        $costCol = collect(['cost_per_unit', 'average_cost', 'purchase_price', 'unit_cost'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));
        $recipeQtyCol = collect(['quantity_required', 'quantity', 'qty_required', 'amount'])
            ->first(fn (string $col) => Schema::hasColumn('product_recipes', $col));

        $products = Product::with(['category', 'recipes.ingredient'])->get();
        $productHppMap = [];
        foreach ($products as $prod) {
            $hpp = 0.0;
            foreach ($prod->recipes as $rec) {
                $rQty  = $recipeQtyCol ? (float) ($rec->{$recipeQtyCol} ?? 0) : 0.0;
                $uCost = $costCol ? (float) ($rec->ingredient?->{$costCol} ?? 0) : 0.0;
                $hpp += $rQty * $uCost;
            }
            $productHppMap[(string) $prod->id] = round($hpp, 2);
        }

        $dailySalesMap   = [];
        $menuPerfMap     = [];
        $cashflowDateMap = [];
        $hourlyMap       = [];

        for ($h = 0; $h < 24; $h++) {
            $hourlyMap[$h] = [
                'hour'       => $h,
                'hour_label' => sprintf('%02d:00 - %02d:59', $h, $h),
                'tx_count'   => 0,
                'portions'   => 0,
                'revenue'    => 0.0,
            ];
        }

        $totalOmzetAll    = 0.0;
        $totalCashAll     = 0.0;
        $totalQrisAll     = 0.0;
        $totalPaidTx      = 0;
        $totalVoidTx      = 0;
        $totalPortionsAll = 0;
        $totalEstHppAll   = 0.0;

        foreach ($allTransactions as $tx) {
            $dateKey   = $tx->created_at->format('Y-m-d');
            $dateLabel = $tx->created_at->translatedFormat('d M Y');
            $status    = strtoupper((string) ($tx->status ?? 'PAID'));

            if (! isset($dailySalesMap[$dateKey])) {
                $dailySalesMap[$dateKey] = [
                    'date_raw'    => $dateKey,
                    'tanggal'     => $dateLabel,
                    'paid_count'  => 0,
                    'void_count'  => 0,
                    'portions'    => 0,
                    'cash_total'  => 0.0,
                    'qris_total'  => 0.0,
                    'total_omzet' => 0.0,
                ];
            }

            if ($status !== 'PAID') {
                $dailySalesMap[$dateKey]['void_count']++;
                $totalVoidTx++;
                continue;
            }

            $txAmount = $txTotalCol ? (float) ($tx->{$txTotalCol} ?? 0) : 0.0;
            if ($txAmount <= 0) {
                $txAmount = (float) $tx->items->sum(fn ($i) => (float) $i->unit_price * (int) $i->quantity);
            }

            $payMethod = $txPayCol ? strtoupper((string) ($tx->{$txPayCol} ?? 'CASH')) : 'CASH';
            $isCash    = in_array($payMethod, ['CASH', 'TUNAI'], true);

            $dailySalesMap[$dateKey]['paid_count']++;
            $dailySalesMap[$dateKey]['total_omzet'] += $txAmount;
            if ($isCash) {
                $dailySalesMap[$dateKey]['cash_total'] += $txAmount;
                $totalCashAll += $txAmount;
            } else {
                $dailySalesMap[$dateKey]['qris_total'] += $txAmount;
                $totalQrisAll += $txAmount;
            }

            $totalPaidTx++;
            $totalOmzetAll += $txAmount;

            if (! isset($cashflowDateMap[$dateKey])) {
                $cashflowDateMap[$dateKey] = [
                    'date_raw' => $dateKey,
                    'tanggal'  => $dateLabel,
                    'sales'    => 0.0,
                    'expenses' => 0.0,
                    'net'      => 0.0,
                ];
            }
            $cashflowDateMap[$dateKey]['sales'] += $txAmount;

            $hourIdx = (int) $tx->created_at->format('G');
            $hourlyMap[$hourIdx]['tx_count']++;
            $hourlyMap[$hourIdx]['revenue'] += $txAmount;

            foreach ($tx->items as $item) {
                $pid      = (string) ($item->product_id ?? 'deleted');
                $pName    = $item->product?->name ?? 'Menu Dihapus';
                $catName  = $item->product?->category?->name ?? 'Umum';
                $qty      = (int) $item->quantity;
                $uPrice   = (float) $item->unit_price;
                $rev      = $uPrice * $qty;
                $unitHpp  = $productHppMap[$pid] ?? 0.0;
                $itemHpp  = $unitHpp * $qty;

                $dailySalesMap[$dateKey]['portions'] += $qty;
                $hourlyMap[$hourIdx]['portions'] += $qty;
                $totalPortionsAll += $qty;
                $totalEstHppAll += $itemHpp;

                if (! isset($menuPerfMap[$pid])) {
                    $menuPerfMap[$pid] = [
                        'name'         => $pName,
                        'category'     => $catName,
                        'price'        => $uPrice,
                        'unit_hpp'     => $unitHpp,
                        'qty_sold'     => 0,
                        'revenue'      => 0.0,
                        'total_hpp'    => 0.0,
                        'gross_profit' => 0.0,
                        'share_pct'    => 0.0,
                    ];
                }

                $menuPerfMap[$pid]['qty_sold'] += $qty;
                $menuPerfMap[$pid]['revenue'] += $rev;
                $menuPerfMap[$pid]['total_hpp'] += $itemHpp;
                $menuPerfMap[$pid]['gross_profit'] = $menuPerfMap[$pid]['revenue'] - $menuPerfMap[$pid]['total_hpp'];
            }
        }

        $dailySalesReport = collect($dailySalesMap)->sortByDesc('date_raw')->values();

        $menuReport = collect($menuPerfMap)
            ->map(function ($row) use ($totalOmzetAll) {
                $row['share_pct'] = $totalOmzetAll > 0 ? ($row['revenue'] / $totalOmzetAll) * 100 : 0;
                return $row;
            })
            ->sortByDesc('qty_sold')
            ->values();

        $qtyCol = collect(['quantity_change', 'quantity', 'qty_change', 'delta', 'qty', 'amount', 'change_amount', 'stock_change'])
            ->first(fn (string $col) => Schema::hasColumn('stock_movements', $col));

        $movements = StockMovement::with(['ingredient'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->get();

        $totalExpensesAll = 0.0;
        $ingredientInMap  = [];
        $ingredientOutMap = [];

        foreach ($movements as $mov) {
            $qty       = $qtyCol ? (float) ($mov->{$qtyCol} ?? 0) : 0.0;
            $type      = strtoupper((string) ($mov->type ?? ''));
            $totalCost = (float) ($mov->total_cost ?? 0);
            $ingId     = (string) ($mov->ingredient_id ?? '');

            $isPurchase = in_array($type, ['RESTOCK', 'IN', 'PURCHASE', 'STOCK_IN'], true)
                || ($totalCost > 0 && $qty > 0)
                || ($type === '' && $qty > 0);

            if ($isPurchase && $qty > 0) {
                if ($totalCost <= 0) {
                    $uCost = (float) ($mov->unit_cost ?? ($costCol ? ($mov->ingredient?->{$costCol} ?? 0) : 0));
                    $totalCost = round($qty * $uCost, 2);
                }

                $totalExpensesAll += $totalCost;

                $dKey   = $mov->created_at->format('Y-m-d');
                $dLabel = $mov->created_at->translatedFormat('d M Y');
                if (! isset($cashflowDateMap[$dKey])) {
                    $cashflowDateMap[$dKey] = [
                        'date_raw' => $dKey,
                        'tanggal'  => $dLabel,
                        'sales'    => 0.0,
                        'expenses' => 0.0,
                        'net'      => 0.0,
                    ];
                }
                $cashflowDateMap[$dKey]['expenses'] += $totalCost;

                if ($ingId !== '') {
                    $ingredientInMap[$ingId] = ($ingredientInMap[$ingId] ?? 0) + $qty;
                }
            } elseif ($qty < 0 && $ingId !== '') {
                $ingredientOutMap[$ingId] = ($ingredientOutMap[$ingId] ?? 0) + abs($qty);
            }
        }

        $cashflowDaily = collect($cashflowDateMap)
            ->map(function ($row) {
                $row['net'] = $row['sales'] - $row['expenses'];
                return $row;
            })
            ->sortByDesc('date_raw')
            ->values();

        $minCol = collect(['min_stock', 'minimum_stock', 'min_stock_threshold', 'stock_threshold'])
            ->first(fn (string $col) => Schema::hasColumn('ingredients', $col));

        $totalInventoryAsset = 0.0;
        $lowStockCount = 0;
        $inventoryReport = Ingredient::orderBy('name')->get()->map(function ($ing) use ($minCol, $costCol, $ingredientInMap, $ingredientOutMap, &$totalInventoryAsset, &$lowStockCount) {
            $stock     = (float) $ing->current_stock;
            $minStock  = $minCol ? (float) ($ing->{$minCol} ?? 0) : 0.0;
            $unitCost  = $costCol ? (float) ($ing->{$costCol} ?? 0) : 0.0;
            $assetVal  = round($stock * $unitCost, 2);
            $isLow     = $stock <= $minStock;

            $totalInventoryAsset += $assetVal;
            if ($isLow) {
                $lowStockCount++;
            }

            return [
                'name'          => $ing->name,
                'unit'          => $ing->unit,
                'current_stock' => $stock,
                'min_stock'     => $minStock,
                'unit_cost'     => $unitCost,
                'asset_value'   => $assetVal,
                'qty_in'        => $ingredientInMap[(string) $ing->id] ?? 0.0,
                'qty_out'       => $ingredientOutMap[(string) $ing->id] ?? 0.0,
                'is_low'        => $isLow,
            ];
        });

        $shiftReport = collect();
        $totalShiftDiff = 0.0;

        if (Schema::hasTable('cash_shifts')) {
            $openCol      = collect(['opened_at', 'start_time', 'created_at'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));
            $closeCol     = collect(['closed_at', 'end_time'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));
            $startCashCol = collect(['opening_cash', 'starting_cash', 'initial_cash', 'modal_awal'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));
            $expCashCol   = collect(['expected_cash', 'system_cash', 'expected_ending_cash'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));
            $actCashCol   = collect(['closing_cash', 'actual_cash', 'ending_cash', 'actual_ending_cash'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));
            $diffCol      = collect(['difference', 'discrepancy', 'cash_difference'])->first(fn ($c) => Schema::hasColumn('cash_shifts', $c));

            $rawShifts = DB::table('cash_shifts')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderByDesc('created_at')
                ->get();

            $shiftReport = $rawShifts->map(function ($s) use ($userNames, $openCol, $closeCol, $startCashCol, $expCashCol, $actCashCol, $diffCol, &$totalShiftDiff) {
                $opening  = $startCashCol ? (float) ($s->{$startCashCol} ?? 0) : 0.0;
                $expected = $expCashCol ? (float) ($s->{$expCashCol} ?? 0) : 0.0;
                $actual   = $actCashCol && $s->{$actCashCol} !== null ? (float) $s->{$actCashCol} : null;

                if ($diffCol && $s->{$diffCol} !== null) {
                    $diff = (float) $s->{$diffCol};
                } elseif ($actual !== null) {
                    $diff = $actual - $expected;
                } else {
                    $diff = 0.0;
                }

                $totalShiftDiff += $diff;
                $uid = (string) ($s->user_id ?? $s->cashier_id ?? '');

                return [
                    'opened_at'     => $openCol && ! empty($s->{$openCol}) ? Carbon::parse($s->{$openCol})->format('d M Y H:i') : '-',
                    'closed_at'     => $closeCol && ! empty($s->{$closeCol}) ? Carbon::parse($s->{$closeCol})->format('d M Y H:i') : 'Masih Buka',
                    'cashier_name'  => $userNames[$uid] ?? 'Kasir',
                    'opening_cash'  => $opening,
                    'expected_cash' => $expected,
                    'actual_cash'   => $actual,
                    'difference'    => $diff,
                    'status'        => strtoupper((string) ($s->status ?? ($actual !== null ? 'CLOSED' : 'OPEN'))),
                ];
            });
        }

        $peakHoursReport = collect($hourlyMap)
            ->filter(fn ($h) => $h['tx_count'] > 0 || ($h['hour'] >= 8 && $h['hour'] <= 23))
            ->values();
        $maxHourlyRevenue = max(1, (float) $peakHoursReport->max('revenue'));
        $busiestHour = $peakHoursReport->sortByDesc('revenue')->first();

        return [
            'dailySalesReport' => $dailySalesReport,
            'menuReport'       => $menuReport,
            'cashflowDaily'    => $cashflowDaily,
            'inventoryReport'  => $inventoryReport,
            'shiftReport'      => $shiftReport,
            'peakHoursReport'  => $peakHoursReport,
            'maxHourlyRevenue' => $maxHourlyRevenue,
            'busiestHour'      => $busiestHour,
            'summary' => [
                'total_omzet'           => $totalOmzetAll,
                'total_cash'            => $totalCashAll,
                'total_qris'            => $totalQrisAll,
                'total_paid_tx'         => $totalPaidTx,
                'total_void_tx'         => $totalVoidTx,
                'total_portions'        => $totalPortionsAll,
                'total_est_hpp'         => $totalEstHppAll,
                'total_gross_profit'    => $totalOmzetAll - $totalEstHppAll,
                'total_expenses'        => $totalExpensesAll,
                'net_cashflow'          => $totalOmzetAll - $totalExpensesAll,
                'total_inventory_asset' => $totalInventoryAsset,
                'low_stock_count'       => $lowStockCount,
                'total_shift_diff'      => $totalShiftDiff,
            ],
        ];
    }
}