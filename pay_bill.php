<?php
$page_title = 'Pay Bill';
$current_page = 'my_bookings.php';
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

requireLogin();
$userId     = getCurrentUserId();
$user       = getCurrentUser();
$bookingId  = intval($_GET['id'] ?? 0);
$conn       = getDbConnection();

if (!$bookingId) {
    header('Location: my_bookings.php');
    exit;
}

// Fetch booking + vehicle info
$query = "SELECT b.*, v.make, v.model, v.year, v.license_plate
          FROM bookings b
          JOIN vehicles v ON b.vehicle_id = v.id
          WHERE b.id = ? AND b.user_id = ?";
$result = executeQuery($query, [$bookingId, $userId], 'ii');
if (!$result || $result->num_rows === 0) {
    header('Location: my_bookings.php');
    exit;
}
$booking = $result->fetch_assoc();

// Guard: not billed
if (!$booking['is_billed']) {
    header('Location: track_service.php?id=' . $bookingId);
    exit;
}

// Guard: already paid
if (($booking['payment_status'] ?? 'unpaid') === 'paid') {
    header('Location: track_service.php?id=' . $bookingId . '&paid=1');
    exit;
}

// Fetch parts
$partsQuery = "SELECT * FROM parts_used WHERE booking_id = ?";
$partsRes   = executeQuery($partsQuery, [$bookingId], 'i');
$parts      = [];
$partsTotal = 0;
if ($partsRes) {
    while ($p = $partsRes->fetch_assoc()) {
        $parts[]     = $p;
        $partsTotal += $p['total_price'];
    }
}

// Fetch logistics fees
$logQuery = "SELECT type, fee FROM pickup_delivery WHERE booking_id = ? AND fee > 0";
$logRes   = executeQuery($logQuery, [$bookingId], 'i');
$logItems = [];
$logTotal = 0;
if ($logRes) {
    while ($l = $logRes->fetch_assoc()) {
        $logItems[] = $l;
        $logTotal  += $l['fee'];
    }
}

$mechanicFee = floatval($booking['mechanic_fee'] ?? 0);
$finalCost   = floatval($booking['final_cost'] ?? 0);

// Razorpay credentials
$rpKeyId = 'rzp_test_SJSGEHKokFBZ2c';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Bill - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ─── Payment Page ─── */
        .pay-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #f0f4ff 0%, #fafafa 100%);
        }

        /* ─── Progress Bar ─── */
        .pay-progress {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 0;
            position: relative;
            margin-bottom: 2.5rem;
        }
        .pay-progress-connector {
            flex: 1;
            height: 3px;
            background: #e5e7eb;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
            max-width: 120px;
        }
        .pay-progress-connector .fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #3b82f6, #6366f1);
            transition: width 0.6s ease;
        }
        .pay-step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .pay-step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 3px solid #e5e7eb;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #9ca3af;
            font-weight: 900;
            transition: all 0.4s ease;
            position: relative;
            z-index: 1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .pay-step-circle.active {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: #fff;
            box-shadow: 0 4px 16px rgba(59,130,246,0.35);
            transform: scale(1.1);
        }
        .pay-step-circle.done {
            border-color: #22c55e;
            background: #22c55e;
            color: #fff;
            box-shadow: 0 4px 12px rgba(34,197,94,0.25);
        }
        .pay-step-label {
            font-size: 10px;
            font-weight: 800;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: center;
            white-space: nowrap;
            transition: color 0.4s;
        }
        .pay-step-label.active { color: #3b82f6; }
        .pay-step-label.done   { color: #22c55e; }

        /* ─── Step Panels ─── */
        .pay-panel {
            display: none;
            animation: fadeInUp 0.4s ease both;
        }
        .pay-panel.active { display: block; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── Invoice ─── */
        .invoice-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .invoice-line:last-child { border-bottom: none; }
        .invoice-total {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 2px solid #bbf7d0;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-top: 20px;
        }

        /* ─── Payment Methods ─── */
        .pay-method-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .pay-method-card {
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            padding: 18px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s;
            background: #fff;
        }
        .pay-method-card:hover {
            border-color: #93c5fd;
            box-shadow: 0 4px 14px rgba(59,130,246,0.12);
            transform: translateY(-2px);
        }
        .pay-method-card.selected {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff, #f0f4ff);
            box-shadow: 0 4px 16px rgba(59,130,246,0.2);
        }
        .pay-method-card .method-icon {
            font-size: 26px;
            margin-bottom: 8px;
            display: block;
        }
        .pay-method-card .method-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
        }

        /* ─── Success ─── */
        .success-animation {
            animation: popIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.5); }
            to   { opacity: 1; transform: scale(1); }
        }
        .confetti-text {
            background: linear-gradient(135deg, #059669, #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ─── Loading Spinner ─── */
        .pay-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .pay-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.07);
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="page-content pay-wrapper">
                <!-- Page Header -->
                <div class="flex items-center gap-4 mb-8">
                    <a href="track_service.php?id=<?php echo $bookingId; ?>" class="w-10 h-10 rounded-xl bg-white border border-gray-100 flex items-center justify-center text-gray-500 hover:text-primary hover:border-primary transition-all shadow-sm">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-black text-gray-900">Complete Payment</h1>
                        <p class="text-xs text-muted mt-0.5">Booking <span class="font-mono font-bold text-primary">#<?php echo htmlspecialchars($booking['booking_number']); ?></span> &bull; <?php echo htmlspecialchars($booking['year'].' '.$booking['make'].' '.$booking['model']); ?></p>
                    </div>
                </div>

                <div class="max-w-2xl mx-auto">

                    <!-- ═══ PROGRESS BAR ═══ -->
                    <div class="pay-progress mb-10" id="payProgress">
                        <div class="pay-step-item">
                            <div class="pay-step-circle active" id="circle-1"><i class="fa-solid fa-receipt"></i></div>
                            <div class="pay-step-label active" id="label-1">Invoice</div>
                        </div>
                        <div class="pay-progress-connector"><div class="fill" id="fill-1"></div></div>
                        <div class="pay-step-item">
                            <div class="pay-step-circle" id="circle-2"><i class="fa-solid fa-credit-card"></i></div>
                            <div class="pay-step-label" id="label-2">Payment</div>
                        </div>
                        <div class="pay-progress-connector"><div class="fill" id="fill-2"></div></div>
                        <div class="pay-step-item">
                            <div class="pay-step-circle" id="circle-3"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="pay-step-label" id="label-3">Confirmed</div>
                        </div>
                    </div>

                    <!-- ═══ STEP 1: INVOICE REVIEW ═══ -->
                    <div class="pay-panel active" id="step-1">
                        <div class="pay-card">
                            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-white text-xl">
                                        <i class="fa-solid fa-file-invoice-dollar"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-white font-black text-lg">Service Invoice</h2>
                                        <p class="text-blue-100 text-xs font-medium">Review your bill before paying</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6">
                                <!-- Mechanic Fee -->
                                <div class="invoice-line">
                                    <div class="flex items-center gap-2 text-gray-600">
                                        <i class="fa-solid fa-screwdriver-wrench text-primary text-xs"></i>
                                        <span>Mechanic Labor / Service Fee</span>
                                    </div>
                                    <span class="font-black text-gray-900">₹<?php echo number_format($mechanicFee, 2); ?></span>
                                </div>

                                <!-- Parts -->
                                <?php if (!empty($parts)): ?>
                                    <div class="invoice-line">
                                        <span class="text-xs font-black uppercase text-muted tracking-wider flex items-center gap-1">
                                            <i class="fa-solid fa-boxes-stacked text-xs"></i> Parts & Products
                                        </span>
                                    </div>
                                    <?php foreach ($parts as $part): ?>
                                    <div class="invoice-line pl-4">
                                        <span class="text-gray-600"><?php echo htmlspecialchars($part['part_name']); ?> <span class="text-[10px] text-muted">(×<?php echo $part['quantity']; ?>)</span></span>
                                        <span class="font-bold">₹<?php echo number_format($part['total_price'], 2); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Logistics -->
                                <?php if (!empty($logItems)): ?>
                                    <div class="invoice-line">
                                        <span class="text-xs font-black uppercase text-muted tracking-wider flex items-center gap-1">
                                            <i class="fa-solid fa-truck text-xs"></i> Logistics Fees
                                        </span>
                                    </div>
                                    <?php foreach ($logItems as $log): ?>
                                    <div class="invoice-line pl-4">
                                        <span class="text-gray-600 capitalize"><?php echo htmlspecialchars($log['type']); ?> Service</span>
                                        <span class="font-bold">₹<?php echo number_format($log['fee'], 2); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Total -->
                                <div class="invoice-total">
                                    <div class="text-xs font-black uppercase text-muted mb-2 tracking-wider">Total Amount Due</div>
                                    <div class="text-4xl font-black text-green-600">₹<?php echo number_format($finalCost, 2); ?></div>
                                    <div class="text-[10px] text-muted mt-2">
                                        <i class="fa-solid fa-shield-halved mr-1"></i>Secure payment via Razorpay
                                    </div>
                                </div>

                                <button onclick="goToStep(2)" class="btn btn-primary w-full py-4 mt-6 font-bold text-base rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-xl transition-all">
                                    Continue to Payment <i class="fa-solid fa-arrow-right ml-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ STEP 2: PAYMENT METHOD ═══ -->
                    <div class="pay-panel" id="step-2">
                        <div class="pay-card p-6">
                            <h2 class="font-black text-xl text-gray-900 mb-2">Pay ₹<?php echo number_format($finalCost, 2); ?></h2>
                            <p class="text-sm text-muted mb-6">Choose how you'd like to pay. You'll be redirected to the secure Razorpay checkout.</p>

                            <div class="pay-method-grid" id="methodGrid">
                                <div class="pay-method-card selected" data-method="razorpay" onclick="selectMethod(this, 'razorpay')">
                                    <span class="method-icon">💳</span>
                                    <span class="method-label">Card / UPI</span>
                                </div>
                                <div class="pay-method-card" data-method="netbanking" onclick="selectMethod(this, 'netbanking')">
                                    <span class="method-icon">🏦</span>
                                    <span class="method-label">Net Banking</span>
                                </div>
                                <div class="pay-method-card" data-method="wallet" onclick="selectMethod(this, 'wallet')">
                                    <span class="method-icon">👛</span>
                                    <span class="method-label">Wallets</span>
                                </div>
                            </div>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-4 mb-6 text-sm text-muted flex items-start gap-3">
                                <i class="fa-solid fa-lock text-primary mt-0.5"></i>
                                <div>Clicking <strong class="text-gray-800">Pay Now</strong> opens the Razorpay secure checkout. Your card &amp; UPI details are <span class="text-success font-bold">never stored</span> on our servers.</div>
                            </div>

                            <div class="flex gap-3">
                                <button onclick="goToStep(1)" class="btn btn-white border border-gray-200 flex-shrink-0 px-5 rounded-xl font-bold">
                                    <i class="fa-solid fa-arrow-left mr-1"></i> Back
                                </button>
                                <button id="razorpayBtn" onclick="launchRazorpay()" class="btn btn-success flex-1 py-4 font-bold text-base rounded-xl shadow-lg shadow-emerald-500/20 hover:shadow-xl transition-all">
                                    <i class="fa-solid fa-lock mr-2"></i> Pay ₹<?php echo number_format($finalCost, 2); ?> Securely
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ STEP 3: SUCCESS ═══ -->
                    <div class="pay-panel" id="step-3">
                        <div class="pay-card p-10 text-center">
                            <div class="success-animation w-24 h-24 rounded-full bg-gradient-to-br from-green-400 to-emerald-500 flex items-center justify-center mx-auto mb-6 shadow-xl shadow-emerald-500/40">
                                <i class="fa-solid fa-check text-white text-4xl font-black"></i>
                            </div>
                            <h2 class="text-3xl font-black confetti-text mb-2">Payment Successful!</h2>
                            <p class="text-muted mb-6">Your payment has been confirmed and your bill is cleared.</p>

                            <div class="bg-gray-50 border border-gray-100 rounded-xl p-5 text-left mb-8 space-y-3">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Booking</span>
                                    <span class="font-black font-mono text-primary">#<?php echo $booking['booking_number']; ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Vehicle</span>
                                    <span class="font-bold"><?php echo htmlspecialchars($booking['year'].' '.$booking['make'].' '.$booking['model']); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Amount Paid</span>
                                    <span class="font-black text-green-600 text-lg">₹<?php echo number_format($finalCost, 2); ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Transaction ID</span>
                                    <span class="font-mono text-xs font-bold text-gray-700" id="txnIdDisplay">—</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Payment Method</span>
                                    <span class="font-bold">Razorpay</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted font-medium">Date</span>
                                    <span class="font-bold"><?php echo date('M d, Y h:i A'); ?></span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <a href="track_service.php?id=<?php echo $bookingId; ?>" class="btn btn-primary py-4 font-bold rounded-xl">
                                    <i class="fa-solid fa-arrow-right mr-2"></i> Back to Service Tracker
                                </a>
                                <a href="my_bookings.php" class="btn btn-white border border-gray-200 py-3 font-bold rounded-xl text-sm">
                                    View All Bookings
                                </a>
                            </div>
                        </div>
                    </div>

                </div><!-- /max-w -->
            </div><!-- /page-content -->
        </main>
    </div>

    <!-- Razorpay SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <script>
        const BOOKING_ID  = <?php echo $bookingId; ?>;
        const AMOUNT_PAISE = <?php echo (int)($finalCost * 100); ?>; // Razorpay uses paise (1/100 of INR)
        const RP_KEY_ID   = '<?php echo $rpKeyId; ?>';
        const CUSTOMER_NAME   = '<?php echo addslashes($user['name'] ?? ''); ?>';
        const CUSTOMER_EMAIL  = '<?php echo addslashes($user['email'] ?? ''); ?>';
        const CUSTOMER_PHONE  = '<?php echo addslashes($user['phone'] ?? ''); ?>';
        const BOOKING_NUMBER  = '<?php echo $booking['booking_number']; ?>';

        let currentStep = 1;

        // ── Step Navigation ──────────────────────────────────────────────
        function goToStep(step) {
            // Hide current
            document.getElementById('step-' + currentStep).classList.remove('active');

            // Update progress circles
            for (let i = 1; i <= 3; i++) {
                const circle = document.getElementById('circle-' + i);
                const label  = document.getElementById('label-' + i);
                circle.classList.remove('active', 'done');
                label.classList.remove('active', 'done');

                if (i < step) {
                    circle.classList.add('done');
                    circle.innerHTML = '<i class="fa-solid fa-check"></i>';
                    label.classList.add('done');
                } else if (i === step) {
                    circle.classList.add('active');
                    let icon = ['', 'fa-receipt', 'fa-credit-card', 'fa-circle-check'][i];
                    circle.innerHTML = '<i class="fa-solid ' + icon + '"></i>';
                    label.classList.add('active');
                } else {
                    let icon = ['', 'fa-receipt', 'fa-credit-card', 'fa-circle-check'][i];
                    circle.innerHTML = '<i class="fa-solid ' + icon + '"></i>';
                }
            }

            // Update connector fills
            document.getElementById('fill-1').style.width = step >= 2 ? '100%' : '0%';
            document.getElementById('fill-2').style.width = step >= 3 ? '100%' : '0%';

            currentStep = step;
            document.getElementById('step-' + step).classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ── Method Selection ──────────────────────────────────────────────
        function selectMethod(el, method) {
            document.querySelectorAll('.pay-method-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
        }

        // ── Razorpay Checkout ─────────────────────────────────────────────
        function launchRazorpay() {
            const btn = document.getElementById('razorpayBtn');
            btn.innerHTML = '<span class="pay-spinner"></span> Opening Checkout...';
            btn.disabled = true;

            const options = {
                key: RP_KEY_ID,
                amount: AMOUNT_PAISE,
                currency: 'INR',
                name: 'AutoCare Connect',
                description: 'Service Bill - Booking #' + BOOKING_NUMBER,
                image: window.location.origin + '/autocare-connect/assets/img/logo.png',
                handler: function(response) {
                    // Payment succeeded on Razorpay side — verify & record on server
                    verifyPayment(response);
                },
                prefill: {
                    name:  CUSTOMER_NAME,
                    email: CUSTOMER_EMAIL,
                    contact: CUSTOMER_PHONE
                },
                theme: { color: '#3b82f6' },
                modal: {
                    ondismiss: function() {
                        btn.innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Pay ₹<?php echo number_format($finalCost, 2); ?> Securely';
                        btn.disabled = false;
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function(response) {
                alert('Payment failed: ' + response.error.description);
                btn.innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Pay ₹<?php echo number_format($finalCost, 2); ?> Securely';
                btn.disabled = false;
            });
            rzp.open();
        }

        // ── Server-side Verification ──────────────────────────────────────
        async function verifyPayment(razorpayResponse) {
            const btn = document.getElementById('razorpayBtn');
            btn.innerHTML = '<span class="pay-spinner"></span> Verifying...';

            const formData = new FormData();
            formData.append('booking_id',           BOOKING_ID);
            formData.append('razorpay_order_id',    razorpayResponse.razorpay_order_id  || '');
            formData.append('razorpay_payment_id',  razorpayResponse.razorpay_payment_id);
            formData.append('razorpay_signature',   razorpayResponse.razorpay_signature  || '');

            try {
                const res  = await fetch('ajax/process_payment.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    document.getElementById('txnIdDisplay').textContent = razorpayResponse.razorpay_payment_id;
                    goToStep(3);
                } else {
                    alert('⚠️ ' + (data.message || 'Verification failed. Please contact support.'));
                    btn.innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Pay ₹<?php echo number_format($finalCost, 2); ?> Securely';
                    btn.disabled = false;
                }
            } catch (e) {
                alert('Network error. Please check your connection and try again.');
                btn.innerHTML = '<i class="fa-solid fa-lock mr-2"></i> Pay ₹<?php echo number_format($finalCost, 2); ?> Securely';
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
