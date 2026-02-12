<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireAdmin();

$current_page = 'admin_income_report.php';
$page_title = 'Income Report';

// Default filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch Income Data
$incomeQuery = "SELECT 
                    b.booking_number, 
                    b.service_type, 
                    u.name as customer_name, 
                    b.completion_date, 
                    b.mechanic_fee, 
                    (SELECT SUM(unit_price * quantity) FROM parts_used WHERE booking_id = b.id) as parts_cost,
                    (SELECT SUM(fee) FROM pickup_delivery WHERE booking_id = b.id AND status = 'completed') as delivery_fees,
                    b.final_cost
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.status IN ('completed', 'delivered') 
                AND DATE(b.completion_date) BETWEEN ? AND ?
                ORDER BY b.completion_date DESC";

$incomeResult = executeQuery($incomeQuery, [$start_date, $end_date], 'ss');
$reportData = [];
$totalMechanicFees = 0;
$totalPartsCost = 0;
$totalDeliveryFees = 0;
$totalFinalIncome = 0;

if ($incomeResult) {
    while ($row = $incomeResult->fetch_assoc()) {
        $row['parts_cost'] = $row['parts_cost'] ?? 0;
        $row['delivery_fees'] = $row['delivery_fees'] ?? 0;
        $reportData[] = $row;
        $totalMechanicFees += $row['mechanic_fee'];
        $totalPartsCost += $row['parts_cost'];
        $totalDeliveryFees += $row['delivery_fees'];
        $totalFinalIncome += $row['final_cost'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Report - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page { margin: 0; size: landscape; }
            body { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
                background: white;
                font-family: 'Times New Roman', Times, serif; /* Professional Serif for Document */
            }
            .sidebar, .dashboard-wrapper > .sidebar, .main-content > header, .filters-section, .no-print, .btn, form { display: none !important; }
            
            /* Reset Layout */
            .dashboard-wrapper, .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; background: white !important; }
            .page-content { padding: 40px !important; max-width: 100% !important; }
            
            /* Header */
            .print-header { 
                display: flex !important; 
                align-items: flex-end; 
                justify-content: space-between; 
                border-bottom: 3px double #1e40af; 
                padding-bottom: 20px; 
                margin-bottom: 40px; 
                width: 100%; 
            }
            
            /* Summary Section */
            .summary-grid { 
                display: flex !important; 
                justify-content: space-between; 
                gap: 20px !important; 
                margin-bottom: 40px !important; 
                border-bottom: 1px solid #eee;
                padding-bottom: 30px;
            }
            .summary-card { 
                border: none !important; 
                padding: 0 !important; 
                background: transparent !important; 
                text-align: left;
                flex: 1;
            }
            .summary-card .text-muted { 
                font-size: 10px !important; 
                text-transform: uppercase; 
                letter-spacing: 1px; 
                color: #555 !important; 
                display: block;
                margin-bottom: 5px;
                font-family: sans-serif;
            }
            .summary-card .text-2xl, .summary-card .text-3xl { 
                font-size: 24px !important; 
                font-weight: bold; 
                color: #000 !important; 
                font-family: 'Courier New', Courier, monospace; /* Monospace for numbers */
            }

            /* Table */
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
            table { 
                font-size: 12px !important; 
                width: 100% !important; 
                border-collapse: collapse; 
                font-family: sans-serif;
            }
            th { 
                background-color: #f1f5f9 !important; 
                color: #0f172a !important; 
                font-weight: 800 !important; 
                text-transform: uppercase; 
                font-size: 10px !important; 
                padding: 12px 8px !important;
                border-top: 2px solid #000 !important;
                border-bottom: 2px solid #000 !important;
            }
            td { 
                padding: 10px 8px !important; 
                border-bottom: 1px solid #e2e8f0 !important; 
                color: #334155 !important; 
            }
            tr:last-child td { border-bottom: 2px solid #000 !important; }
            
            /* Watermark & Footer */
            .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 10rem; color: rgba(0,0,0,0.02); z-index: -1; font-weight: 900; }
            .print-footer { 
                display: flex !important; 
                justify-content: space-between; 
                margin-top: 50px; 
                padding-top: 20px;
                border-top: 1px solid #ccc;
                font-size: 10px;
                color: #666;
            }
        }

        .print-header, .print-footer { display: none; }
        
        /* Interactive Buttons (Screen Only) */
        .btn-blue-soft { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; transition: all 0.2s; }
        .btn-blue-soft:hover { background: #dbeafe; color: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .btn-print { background: white; color: #2563eb; border: 2px solid #2563eb; transition: all 0.2s; }
        .btn-print:hover { background: #2563eb; color: white; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-csv { background: #2563eb; color: white; border: none; transition: all 0.2s; }
        .btn-csv:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
        .btn-filter { background: #2563eb; color: white; transition: all 0.2s; }
        .btn-filter:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4); }
        .filters-section:hover { transform: none !important; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; border-color: #f3f4f6 !important; }
    </style>
</head>
<body>
    <div class="watermark">CONFIDENTIAL</div>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="page-content">
                <!-- Print Header -->
                <div class="print-header">
                    <div class="flex items-center gap-4">
                         <!-- Simplified Branding for Print -->
                         <div style="font-family: serif;">
                             <h1 style="font-size: 32px; font-weight: 900; color: #1e3a8a; margin: 0; line-height: 1;">AutoCare Connect</h1>
                             <p style="font-size: 11px; color: #555; margin: 5px 0 0; letter-spacing: 3px; text-transform: uppercase;">Premium Vehicle Service Network</p>
                         </div>
                    </div>
                    <div style="text-align: right; font-family: serif;">
                        <h2 style="margin: 0; font-size: 20px; color: #000; text-transform: uppercase; letter-spacing: 1px;">Income Statement</h2>
                        <p style="margin: 5px 0 0; font-size: 11px; color: #444;">Report ID: #<?php echo strtoupper(uniqid()); ?></p>
                        <p style="margin: 0; font-size: 11px; color: #444;">Date Range: <strong><?php echo date('d M Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('d M Y', strtotime($end_date)); ?></strong></p>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-8 no-print">
                    <div>
                        <h1 class="text-2xl font-bold">Financial Income Report</h1>
                        <p class="text-muted text-sm">Detailed breakdown of revenue generated across services.</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="window.print()" class="btn btn-print flex items-center gap-2 px-6 py-2.5 rounded-xl font-bold shadow-md shadow-blue-100">
                            <i class="fa-solid fa-print"></i> Print Report
                        </button>
                        <a href="ajax/export_income_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-csv flex items-center gap-2 px-6 py-2.5 rounded-xl shadow-lg shadow-blue-200 font-bold">
                            <i class="fa-solid fa-file-csv"></i> Export CSV
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card p-6 mb-8 bg-white border border-gray-100 shadow-sm no-print filters-section rounded-2xl">
                    <form method="GET" class="flex flex-wrap items-end gap-8" id="reportFilterForm">
                        <div class="space-y-1.5 mr-4">
                            <label class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>" class="form-control bg-gray-50 border-gray-200 rounded-lg px-4 py-2.5 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium" style="min-width: 200px;">
                        </div>
                        <div class="space-y-1.5 mr-4">
                            <label class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">End Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" min="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>" class="form-control bg-gray-50 border-gray-200 rounded-lg px-4 py-2.5 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all font-medium" style="min-width: 200px;">
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="submit" class="btn btn-filter px-8 py-2.5 rounded-lg font-bold shadow-lg shadow-primary/20 hover:-translate-y-0.5 hover:shadow-primary/30 transition-all">
                                Filter Report
                            </button>
                            <a href="admin_income_report.php" class="btn btn-blue-soft px-6 py-2.5 rounded-lg font-bold transition-all flex items-center gap-2">
                                <i class="fa-solid fa-rotate-right text-xs"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>


                <script>
                    // Validations
                    const startInput = document.getElementById('start_date');
                    const endInput = document.getElementById('end_date');

                    startInput.addEventListener('change', function() {
                        endInput.min = this.value;
                        if(endInput.value && endInput.value < this.value) {
                            endInput.value = this.value;
                        }
                    });
                </script>

                <!-- Summary Cards -->
                <div class="grid gap-4 mb-8 summary-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="card p-6 border-l-4 border-indigo-500 summary-card">
                        <span class="text-muted text-xs font-bold uppercase tracking-wider">Labor Income</span>
                        <div class="text-2xl font-black mt-2">₹<?php echo number_format($totalMechanicFees); ?></div>
                    </div>
                    <div class="card p-6 border-l-4 border-amber-500 summary-card">
                        <span class="text-muted text-xs font-bold uppercase tracking-wider">Parts Revenue</span>
                        <div class="text-2xl font-black mt-2">₹<?php echo number_format($totalPartsCost); ?></div>
                    </div>
                    <div class="card p-6 border-l-4 border-blue-500 summary-card">
                        <span class="text-muted text-xs font-bold uppercase tracking-wider">Logistics</span>
                        <div class="text-2xl font-black mt-2">₹<?php echo number_format($totalDeliveryFees); ?></div>
                    </div>
                    <div class="card p-6 border-l-4 border-success bg-success/5 summary-card">
                        <span class="text-success text-xs font-bold uppercase tracking-wider">Total Net Income</span>
                        <div class="text-3xl font-black mt-2 text-gray-900">₹<?php echo number_format($totalFinalIncome); ?></div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="card p-0 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 border-bottom border-gray-100">
                            <tr>
                                <th class="p-4 text-[10px] font-black uppercase text-muted">Date</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted">Booking #</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted">Customer</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted">Service Type</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted text-right">Labor</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted text-right">Parts</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted text-right">Logistics</th>
                                <th class="p-4 text-[10px] font-black uppercase text-muted text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-50">
                            <?php if (empty($reportData)): ?>
                                <tr>
                                    <td colspan="8" class="p-12 text-center text-muted italic">
                                        No income data found for the selected period.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reportData as $row): ?>
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="p-4 font-medium"><?php echo date('M d, Y', strtotime($row['completion_date'])); ?></td>
                                        <td class="p-4 font-bold text-primary">#<?php echo $row['booking_number']; ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td class="p-4 text-xs"><?php echo htmlspecialchars($row['service_type']); ?></td>
                                        <td class="p-4 text-right">₹<?php echo number_format($row['mechanic_fee']); ?></td>
                                        <td class="p-4 text-right">₹<?php echo number_format($row['parts_cost']); ?></td>
                                        <td class="p-4 text-right">₹<?php echo number_format($row['delivery_fees']); ?></td>
                                        <td class="p-4 text-right font-black">₹<?php echo number_format($row['final_cost']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-black">
                             <tr>
                                <td colspan="4" class="p-4 text-right uppercase tracking-widest text-xs">Total for Period</td>
                                <td class="p-4 text-right">₹<?php echo number_format($totalMechanicFees); ?></td>
                                <td class="p-4 text-right">₹<?php echo number_format($totalPartsCost); ?></td>
                                <td class="p-4 text-right">₹<?php echo number_format($totalDeliveryFees); ?></td>
                                <td class="p-4 text-right text-lg">₹<?php echo number_format($totalFinalIncome); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Print Footer -->
                <div class="print-footer">
                    <div>
                        <p style="font-weight: bold; margin-bottom: 5px; color: #000;">Authorized By</p>
                        <div style="border-top: 2px solid #000; width: 150px; margin-top: 40px;"></div>
                        <p style="font-size: 10px; margin-top: 5px; color: #555;">(Signature & Stamp)</p>
                    </div>
                    <div style="text-align: right;">
                        <p style="font-weight: bold; margin-bottom: 5px; color: #000;">AutoCare Connect</p>
                        <p style="margin: 0; font-size: 10px; color: #555;">Premium Vehicle Service Network</p>
                        <p style="margin: 0; font-size: 10px; color: #555;">Generated via Admin Panel</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
