<?php 
require_once 'includes/auth.php';

$order_id = (int)($_GET['id'] ?? 0);
$user_id = current_user_id();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Order not found.');
    header('Location: orders.php');
    exit;
}

// Fetch items
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.image, p.stock as current_stock, p.status as current_status 
    FROM order_items oi 
    LEFT JOIN products p ON p.id = oi.product_id 
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Payment record
$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
$pay_stmt->execute([$order_id]);
$payment = $pay_stmt->fetch();

$status = $order['order_status'] ?? $order['status'] ?? 'Pending';
$can_cancel = in_array($status, ['Pending', 'Confirmed']);

$page_title = "Order Details #{$order['order_number']} - EveNeed";
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">My Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order #<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-warning"></i>Order Details</h2>
            <p class="text-muted mb-0">Reference ID: <strong class="text-dark"><?= htmlspecialchars($order['order_number'] ?? '#' . $order['id']) ?></strong> · Placed on <?= date('M d, Y · h:i A', strtotime($order['created_at'])) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="track-order.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm fw-bold px-3">
                <i class="bi bi-geo-alt me-1"></i>Track Shipment
            </a>
            <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm fw-semibold px-3">
                <i class="bi bi-printer me-1"></i>Print Invoice
            </a>
            <?php if ($can_cancel): ?>
                <a href="orders.php?cancel_order=<?= $order['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this order?');">
                    Cancel Order
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="card bg-light p-3 border mb-4 shadow-sm">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-dark text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="bi bi-box-seam fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small d-block">Current Order Status</span>
                    <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($status) ?></h5>
                </div>
            </div>
            <div>
                <a href="track-order.php?id=<?= $order['id'] ?>" class="btn btn-outline-dark btn-sm">
                    View Live Timeline <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Products Table -->
        <div class="col-lg-8">
            <div class="card p-4 shadow-sm border mb-4">
                <h5 class="fw-bold mb-3">Order Items (<?= count($items) ?> Products)</h5>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product Details</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= htmlspecialchars($it['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 55px; height: 55px; object-fit: cover;" alt="">
                                            <div>
                                                <a href="product-details.php?id=<?= $it['product_id'] ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 220px;">
                                                    <?= htmlspecialchars($it['product_name']) ?>
                                                </a>
                                                <small class="text-muted"><?= htmlspecialchars($it['brand'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= format_price($it['final_price'] ?? $it['price']) ?></td>
                                    <td><?= $it['quantity'] ?></td>
                                    <td class="text-end fw-bold"><?= format_price($it['subtotal']) ?></td>
                                    <td class="text-end">
                                        <a href="orders.php?reorder_product=<?= $it['product_id'] ?>" class="btn btn-outline-warning btn-sm text-dark fw-bold" title="Reorder this product">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Address Breakdown -->
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card p-3 shadow-sm border h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-truck me-2 text-primary"></i>Shipping Address</h6>
                        <p class="small text-secondary mb-0">
                            <strong><?= htmlspecialchars($order['shipping_full_name'] ?? '') ?></strong><br>
                            <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?><br>
                            <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_pincode'] ?? '') ?><br>
                            <span class="text-dark">Phone: <?= htmlspecialchars($order['shipping_mobile'] ?? '') ?></span>
                        </p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 shadow-sm border h-100">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-receipt me-2 text-warning"></i>Billing Address</h6>
                        <p class="small text-secondary mb-0">
                            <strong><?= htmlspecialchars($order['billing_full_name'] ?? $order['shipping_full_name'] ?? '') ?></strong><br>
                            <?= nl2br(htmlspecialchars($order['billing_address'] ?? $order['shipping_address'] ?? '')) ?><br>
                            <?= htmlspecialchars($order['billing_city'] ?? $order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['billing_state'] ?? $order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['billing_pincode'] ?? $order['shipping_pincode'] ?? '') ?><br>
                            <span class="text-dark">Phone: <?= htmlspecialchars($order['billing_mobile'] ?? $order['shipping_mobile'] ?? '') ?></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="col-lg-4">
            <div class="card p-4 shadow-sm border mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Payment Breakdown</h5>

                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Subtotal:</span>
                    <span><?= format_price($order['subtotal']) ?></span>
                </div>

                <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 small text-success">
                        <span>Coupon Savings (<?= htmlspecialchars($order['coupon_code'] ?? '') ?>):</span>
                        <span>- <?= format_price($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-muted">Delivery Charges:</span>
                    <span><?= $order['delivery_charge'] == 0 ? 'FREE' : format_price($order['delivery_charge']) ?></span>
                </div>

                <div class="d-flex justify-content-between mb-3 small">
                    <span class="text-muted">Tax (GST 5%):</span>
                    <span><?= format_price($order['tax']) ?></span>
                </div>

                <hr class="my-2">

                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <span class="fs-5 fw-bold text-dark">Total Amount:</span>
                    <span class="fs-4 fw-bold text-success"><?= format_price($order['total_amount']) ?></span>
                </div>

                <div class="bg-light p-3 rounded-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Payment Method:</span>
                        <span class="fw-bold"><?= htmlspecialchars($order['payment_method']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Payment Status:</span>
                        <span class="badge bg-success"><?= htmlspecialchars($order['payment_status']) ?></span>
                    </div>
                    <?php if (!empty($payment['transaction_id'])): ?>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Transaction ID:</span>
                            <code class="small text-truncate" style="max-width: 130px;"><?= htmlspecialchars($payment['transaction_id']) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
