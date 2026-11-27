<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $reportTitle }} - Warmindo Permata</title>
    <style>
        @page {
            margin: 15mm 14mm 16mm 14mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5pt;
            color: #1C1917;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        /* KOP LAPORAN */
        .header-table {
            width: 100%;
            border-bottom: 2.5px solid #C2410C;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .brand-title {
            font-size: 16pt;
            font-weight: bold;
            color: #C2410C;
            letter-spacing: 0.4px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 8.5pt;
            color: #57534E;
            margin: 2px 0 0 0;
        }
        .report-meta {
            text-align: right;
            vertical-align: top;
        }
        .report-name {
            font-size: 11.5pt;
            font-weight: bold;
            color: #1C1917;
            margin: 0;
        }
        .report-period {
            font-size: 8.5pt;
            color: #57534E;
            margin-top: 3px;
        }

        /* KARTU RINGKASAN */
        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 0 -6px 14px -6px;
        }
        .summary-card {
            background-color: #FAF8F5;
            border: 1px solid #E7E5E4;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .summary-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #57534E;
            font-weight: bold;
            display: block;
        }
        .summary-value {
            font-size: 11.5pt;
            font-weight: bold;
            color: #1C1917;
            margin-top: 3px;
            display: block;
        }
        .text-brand { color: #C2410C; }
        .text-success { color: #15803D; }
        .text-danger { color: #B91C1C; }
        .text-muted { color: #78716C; }

        /* TABEL DATA UTAMA */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            font-size: 8.5pt;
        }
        table.data-table thead th {
            background-color: #292524;
            color: #FFFFFF;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
            padding: 7px 8px;
            border: 1px solid #292524;
            text-align: left;
        }
        table.data-table tbody td {
            padding: 6px 8px;
            border: 1px solid #E7E5E4;
            vertical-align: middle;
        }
        table.data-table tbody tr:nth-child(even) {
            background-color: #FAF8F5;
        }
        table.data-table tfoot td {
            background-color: #F5F5F4;
            font-weight: bold;
            padding: 7px 8px;
            border: 1px solid #D6D3D1;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .font-bold { font-weight: bold; }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7pt;
            font-weight: bold;
            border-radius: 4px;
        }
        .badge-success { background-color: #DCFCE7; color: #15803D; }
        .badge-warning { background-color: #FEF3C7; color: #B45309; }
        .badge-neutral { background-color: #F5F5F4; color: #44403C; }

        /* BAR VISUAL UNTUK PEAK HOURS */
        .bar-wrap {
            width: 100%;
            background-color: #E7E5E4;
            height: 8px;
            border-radius: 4px;
        }
        .bar-fill {
            background-color: #C2410C;
            height: 8px;
            border-radius: 4px;
        }

        /* BLOK TANDA TANGAN & FOOTER */
        .footer-sign-table {
            width: 100%;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .print-info {
            font-size: 7.5pt;
            color: #78716C;
            vertical-align: bottom;
        }
        .sign-box {
            width: 210px;
            text-align: center;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .sign-space {
            height: 52px;
        }
        .sign-line {
            border-top: 1px solid #44403C;
            padding-top: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- KOP SURAT / HEADER DOKUMEN -->
    <table class="header-table">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <h1 class="brand-title">WARMINDO PERMATA</h1>
                <p class="brand-subtitle">Sistem Point of Sales &amp; Manajemen Operasional Warung</p>
            </td>
            <td class="report-meta" style="width: 52%;">
                <p class="report-name">{{ $reportTitle }}</p>
                <p class="report-period">Periode: <strong>{{ $periodLabel }}</strong></p>
            </td>
        </tr>
    </table>

    <!-- =================================================================== -->
    <!-- 1. LAPORAN PENJUALAN HARIAN & METODE PEMBAYARAN                     -->
    <!-- =================================================================== -->
    @if ($reportType === 'daily_sales')
        <table class="summary-grid">
            <tr>
                <td class="summary-card" style="width: 25%;">
                    <span class="summary-label">Total Omzet</span>
                    <span class="summary-value text-brand">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <span class="summary-label">Tunai (CASH)</span>
                    <span class="summary-value text-success">Rp {{ number_format($summary['total_cash'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <span class="summary-label">QRIS / Non-Tunai</span>
                    <span class="summary-value">Rp {{ number_format($summary['total_qris'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 25%;">
                    <span class="summary-label">Nota Sukses / Void</span>
                    <span class="summary-value">{{ $summary['total_paid_tx'] }} / <span class="text-danger">{{ $summary['total_void_tx'] }}</span></span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 16%;">Tanggal</th>
                    <th class="text-right" style="width: 11%;">Nota Sukses</th>
                    <th class="text-right" style="width: 10%;">Nota Void</th>
                    <th class="text-right" style="width: 11%;">Porsi</th>
                    <th class="text-right" style="width: 17%;">Tunai (CASH)</th>
                    <th class="text-right" style="width: 17%;">QRIS / Non-Tunai</th>
                    <th class="text-right" style="width: 18%;">Total Omzet</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dailySalesReport as $row)
                    <tr>
                        <td class="font-bold">{{ $row['tanggal'] }}</td>
                        <td class="text-right">{{ $row['paid_count'] }}</td>
                        <td class="text-right text-danger">{{ $row['void_count'] }}</td>
                        <td class="text-right">{{ number_format($row['portions'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['cash_total'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['qris_total'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold text-success">Rp {{ number_format($row['total_omzet'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Tidak ada transaksi penjualan pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($dailySalesReport->count() > 0)
                <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-right">{{ $summary['total_paid_tx'] }}</td>
                        <td class="text-right text-danger">{{ $summary['total_void_tx'] }}</td>
                        <td class="text-right">{{ number_format($summary['total_portions'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($summary['total_cash'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($summary['total_qris'], 0, ',', '.') }}</td>
                        <td class="text-right text-brand">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

    <!-- =================================================================== -->
    <!-- 2. LAPORAN PERFORMA & PERINGKAT MENU TERLARIS                       -->
    <!-- =================================================================== -->
    @elseif ($reportType === 'menu_performance')
        <table class="summary-grid">
            <tr>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Total Omzet Menu</span>
                    <span class="summary-value">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Total Estimasi HPP Resep</span>
                    <span class="summary-value text-danger">Rp {{ number_format($summary['total_est_hpp'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Estimasi Laba Kotor</span>
                    <span class="summary-value text-success">Rp {{ number_format($summary['total_gross_profit'], 0, ',', '.') }}</span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">#</th>
                    <th style="width: 27%;">Nama Menu (Kategori)</th>
                    <th class="text-right" style="width: 13%;">Harga Jual</th>
                    <th class="text-right" style="width: 9%;">Terjual</th>
                    <th class="text-right" style="width: 15%;">Total Omzet</th>
                    <th class="text-right" style="width: 13%;">Est. HPP</th>
                    <th class="text-right" style="width: 18%;">Est. Laba Kotor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($menuReport as $idx => $row)
                    <tr>
                        <td class="text-center font-bold">{{ $idx + 1 }}</td>
                        <td>
                            <span class="font-bold">{{ $row['name'] }}</span>
                            <span class="badge badge-neutral">{{ $row['category'] }}</span>
                        </td>
                        <td class="text-right">Rp {{ number_format($row['price'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold">{{ number_format($row['qty_sold'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                        <td class="text-right text-muted">Rp {{ number_format($row['total_hpp'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold text-success">Rp {{ number_format($row['gross_profit'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada menu terjual pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    <!-- =================================================================== -->
    <!-- 3. LAPORAN ARUS KAS & LABA RUGI OPERASIONAL                         -->
    <!-- =================================================================== -->
    @elseif ($reportType === 'cashflow')
        <table class="summary-grid">
            <tr>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Total Penjualan</span>
                    <span class="summary-value">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Total Belanja / Pengeluaran</span>
                    <span class="summary-value text-danger">Rp {{ number_format($summary['total_expenses'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 33.3%;">
                    <span class="summary-label">Total Pendapatan Bersih</span>
                    <span class="summary-value {{ $summary['net_cashflow'] >= 0 ? 'text-success' : 'text-danger' }}">
                        Rp {{ number_format($summary['net_cashflow'], 0, ',', '.') }}
                    </span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Tanggal</th>
                    <th class="text-right" style="width: 26%;">Pemasukan Penjualan</th>
                    <th class="text-right" style="width: 26%;">Pengeluaran Belanja Bahan</th>
                    <th class="text-right" style="width: 26%;">Selisih Bersih Harian</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cashflowDaily as $row)
                    <tr>
                        <td class="font-bold">{{ $row['tanggal'] }}</td>
                        <td class="text-right">Rp {{ number_format($row['sales'], 0, ',', '.') }}</td>
                        <td class="text-right text-danger">Rp {{ number_format($row['expenses'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold {{ $row['net'] >= 0 ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($row['net'], 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">Tidak ada arus kas pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($cashflowDaily->count() > 0)
                <tfoot>
                    <tr>
                        <td>TOTAL PERIODE</td>
                        <td class="text-right">Rp {{ number_format($summary['total_omzet'], 0, ',', '.') }}</td>
                        <td class="text-right text-danger">Rp {{ number_format($summary['total_expenses'], 0, ',', '.') }}</td>
                        <td class="text-right {{ $summary['net_cashflow'] >= 0 ? 'text-success' : 'text-danger' }}">
                            Rp {{ number_format($summary['net_cashflow'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>

    <!-- =================================================================== -->
    <!-- 4. LAPORAN VALUASI & MUTASI STOK BAHAN BAKU                         -->
    <!-- =================================================================== -->
    @elseif ($reportType === 'inventory_valuation')
        <table class="summary-grid">
            <tr>
                <td class="summary-card" style="width: 34%;">
                    <span class="summary-label">Total Nilai Aset Stok</span>
                    <span class="summary-value text-brand">Rp {{ number_format($summary['total_inventory_asset'], 0, ',', '.') }}</span>
                </td>
                <td class="summary-card" style="width: 33%;">
                    <span class="summary-label">Jumlah Item Bahan</span>
                    <span class="summary-value">{{ $inventoryReport->count() }} Item</span>
                </td>
                <td class="summary-card" style="width: 33%;">
                    <span class="summary-label">Bahan Perlu Restock</span>
                    <span class="summary-value text-danger">{{ $summary['low_stock_count'] }} Item</span>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 24%;">Nama Bahan Baku</th>
                    <th class="text-right" style="width: 12%;">Masuk</th>
                    <th class="text-right" style="width: 12%;">Keluar</th>
                    <th class="text-right" style="width: 13%;">Stok Akhir</th>
                    <th class="text-right" style="width: 14%;">Harga/Satuan</th>
                    <th class="text-right" style="width: 16%;">Nilai Aset</th>
                    <th class="text-center" style="width: 9%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inventoryReport as $row)
                    <tr>
                        <td class="font-bold">{{ $row['name'] }}</td>
                        <td class="text-right text-success">+{{ number_format($row['qty_in'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                        <td class="text-right text-danger">-{{ number_format($row['qty_out'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                        <td class="text-right font-bold">{{ number_format($row['current_stock'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                        <td class="text-right">Rp {{ number_format($row['unit_cost'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold text-brand">Rp {{ number_format($row['asset_value'], 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($row['is_low'])
                                <span class="badge badge-warning">Menipis</span>
                            @else
                                <span class="badge badge-success">Aman</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Belum ada data bahan baku.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    <!-- =================================================================== -->
    <!-- 5. LAPORAN AUDIT SHIFT & SELISIH LACI KASIR                         -->
    <!-- =================================================================== -->
    @elseif ($reportType === 'cash_shifts')
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Waktu Buka</th>
                    <th style="width: 15%;">Waktu Tutup</th>
                    <th style="width: 16%;">Kasir</th>
                    <th class="text-right" style="width: 13%;">Modal Awal</th>
                    <th class="text-right" style="width: 13%;">Kas Sistem</th>
                    <th class="text-right" style="width: 13%;">Kas Aktual</th>
                    <th class="text-right" style="width: 15%;">Selisih Kas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shiftReport as $row)
                    <tr>
                        <td>{{ $row['opened_at'] }}</td>
                        <td>{{ $row['closed_at'] }}</td>
                        <td class="font-bold">{{ $row['cashier_name'] }}</td>
                        <td class="text-right">Rp {{ number_format($row['opening_cash'], 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($row['expected_cash'], 0, ',', '.') }}</td>
                        <td class="text-right font-bold">
                            {{ $row['actual_cash'] !== null ? 'Rp ' . number_format($row['actual_cash'], 0, ',', '.') : '-' }}
                        </td>
                        <td class="text-right font-bold {{ $row['difference'] < 0 ? 'text-danger' : 'text-success' }}">
                            {{ $row['actual_cash'] !== null ? 'Rp ' . number_format($row['difference'], 0, ',', '.') : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Tidak ada catatan shift kasir pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    <!-- =================================================================== -->
    <!-- 6. LAPORAN JAM RAMAI TRANSAKSI (PEAK HOURS)                         -->
    <!-- =================================================================== -->
    @elseif ($reportType === 'peak_hours')
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Jam Operasional</th>
                    <th class="text-right" style="width: 14%;">Transaksi</th>
                    <th class="text-right" style="width: 14%;">Porsi</th>
                    <th class="text-right" style="width: 22%;">Total Omzet</th>
                    <th style="width: 28%;">Indikator Kepadatan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($peakHoursReport as $row)
                    @php
                        $barPct = $row['revenue'] > 0 ? max(4, round(($row['revenue'] / $maxHourlyRevenue) * 100)) : 0;
                    @endphp
                    <tr>
                        <td class="font-bold">{{ $row['hour_label'] }}</td>
                        <td class="text-right">{{ $row['tx_count'] }}</td>
                        <td class="text-right">{{ $row['portions'] }}</td>
                        <td class="text-right font-bold">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                        <td>
                            <div class="bar-wrap">
                                <div class="bar-fill" style="width: {{ $barPct }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- FOOTER DOKUMEN & KOLOM TANDA TANGAN -->
    <table class="footer-sign-table">
        <tr>
            <td class="print-info">
                Dicetak oleh: <strong>{{ $printedBy }}</strong><br>
                Waktu cetak: {{ $printedAt }}<br>
                Dokumen otomatis dari sistem POS Warmindo Permata.
            </td>
            <td style="width: 35%;"></td>
            <td class="sign-box">
                Mengetahui,<br>
                Penanggung Jawab / Owner
                <div class="sign-space"></div>
                <div class="sign-line">( ________________________ )</div>
            </td>
        </tr>
    </table>

</body>
</html>