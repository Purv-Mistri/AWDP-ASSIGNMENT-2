<?php 
require_once 'config/database.php';

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    die("Invalid Order ID.");
}

// Fetch order
if (is_admin_logged_in()) {
    $stmt = $pdo->prepare("SELECT o.*, u.email as user_email, u.mobile as user_mobile FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    $stmt->execute([$order_id]);
} elseif (is_user_logged_in()) {
    $stmt = $pdo->prepare("SELECT o.*, u.email as user_email, u.mobile as user_mobile FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? AND o.user_id = ?");
    $stmt->execute([$order_id, current_user_id()]);
} else {
    die("Please log in to view this invoice.");
}

$order = $stmt->fetch();
if (!$order) {
    die("Order not found or access denied.");
}

// Fetch order items
$items_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Fetch payment record
$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
$pay_stmt->execute([$order_id]);
$payment = $pay_stmt->fetch();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tax Invoice - <?= htmlspecialchars($order['order_number'] ?? 'ORD-' . $order['id']) ?> - EveNeed</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #334155;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .invoice-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        @media print {
            body { background: #ffffff !important; }
            .no-print { display: none !important; }
            .invoice-card { box-shadow: none !important; border: 0 !important; }
        }
    </style>
</head>
<body class="py-4">

<div class="container" style="max-width: 850px;">
    <!-- Action Bar (Hidden when printing) -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <button onclick="window.history.back()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </button>
        <button onclick="window.print()" class="btn btn-warning btn-sm fw-bold px-4 shadow-sm">
            <i class="bi bi-printer-fill me-1"></i>Print Invoice
        </button>
    </div>

    <!-- Printable Invoice Sheet -->
    <div class="invoice-card p-4 p-md-5 border">
        <!-- Header -->
        <div class="row align-items-center border-bottom pb-4 mb-4">
            <div class="col-6">
                <div class="d-flex align-items-center mb-1">
                    <div class="bg-dark text-warning rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 38px; height: 38px;">
                        <i class="bi bi-bag-check-fill fs-5"></i>
                    </div>
                    <span class="fs-3 fw-bold text-dark">Eve<span class="text-warning">Need</span></span>
                </div>
                <small class="text-muted d-block">"Everything You Need, All in One Place."</small>
                <small class="text-muted d-block">EveNeed Global Retail Pvt Ltd</small>
                <small class="text-muted d-block">GSTIN: 29AAACS1234F1Z8</small>
            </div>
            <div class="col-6 text-end">
                <span class="badge bg-dark text-warning px-3 py-2 text-uppercase fs-7 mb-1">TAX INVOICE</span>
                <div class="fw-bold text-primary fs-5 mt-1"><?= htmlspecialchars($order['order_number'] ?? 'ORD-' . $order['id']) ?></div>
                <small class="text-muted d-block">Date: <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></small>
                <small class="text-muted d-block">Payment: <b><?= htmlspecialchars($order['payment_method']) ?></b> (<?= htmlspecialchars($order['payment_status']) ?>)</small>
            </div>
        </div>

        <!-- Addresses -->
        <div class="row g-4 mb-4 border-bottom pb-4">
            <div class="col-6">
                <h6 class="fw-bold text-muted text-uppercase fs-8 mb-2">Billed To (Customer):</h6>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['billing_full_name'] ?? $order['shipping_full_name'] ?? 'Customer') ?></div>
                <div class="small text-secondary">
                    <?= nl2br(htmlspecialchars($order['billing_address'] ?? $order['shipping_address'] ?? '')) ?><br>
                    <?= htmlspecialchars($order['billing_city'] ?? $order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['billing_state'] ?? $order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['billing_pincode'] ?? $order['shipping_pincode'] ?? '') ?><br>
                    Phone: <?= htmlspecialchars($order['billing_mobile'] ?? $order['shipping_mobile'] ?? '') ?>
                </div>
            </div>
            <div class="col-6 text-end">
                <h6 class="fw-bold text-muted text-uppercase fs-8 mb-2">Shipped To:</h6>
                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($order['shipping_full_name'] ?? 'Customer') ?></div>
                <div class="small text-secondary">
                    <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?><br>
                    <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_pincode'] ?? '') ?><br>
                    Phone: <?= htmlspecialchars($order['shipping_mobile'] ?? '') ?>
                </div>
            </div>
        </div>

        <!-- Itemized Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Item Description</th>
                        <th class="text-center" style="width: 15%;">Unit Price</th>
                        <th class="text-center" style="width: 10%;">Qty</th>
                        <th class="text-end" style="width: 25%;">Amount (INR)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($items as $it): ?>
                        <tr>
                            <td><?= $idx++ ?></td>
                            <td>
                                <b><?= htmlspecialchars($it['product_name']) ?></b>
                                <?php if (!empty($it['brand'])): ?>
                                    <small class="text-muted d-block">Brand: <?= htmlspecialchars($it['brand']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= format_price($it['final_price'] ?? $it['price']) ?></td>
                            <td class="text-center"><?= $it['quantity'] ?></td>
                            <td class="text-end fw-bold"><?= format_price($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Total Calculation -->
        <div class="row justify-content-end mb-4">
            <div class="col-md-5">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Subtotal:</span>
                    <span class="fw-semibold"><?= format_price($order['subtotal']) ?></span>
                </div>
                <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between small mb-1 text-success">
                        <span>Coupon Discount (<?= htmlspecialchars($order['coupon_code'] ?? '') ?>):</span>
                        <span>- <?= format_price($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Delivery Charges:</span>
                    <span><?= $order['delivery_charge'] == 0 ? 'FREE' : format_price($order['delivery_charge']) ?></span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">GST (5%):</span>
                    <span><?= format_price($order['tax']) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fs-5 fw-bold text-dark pt-1">
                    <span>Grand Total:</span>
                    <span class="text-success"><?= format_price($order['total_amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer / Signature -->
        <div class="border-top pt-4 mt-4 text-center small text-muted">
            <p class="mb-1">This is a computer-generated tax invoice and requires no physical signature.</p>
            <p class="mb-0">Thank you for choosing <b>EveNeed</b>. For customer support inquiries, contact support@eveneed.com.</p>
        </div>
    </div>
</div>

</body>
</html>
