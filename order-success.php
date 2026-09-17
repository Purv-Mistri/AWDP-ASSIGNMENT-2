<?php 
require_once 'includes/auth.php';

$order_id = (int)($_GET['id'] ?? 0);
$user_id = current_user_id();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Order details not found.');
    header('Location: orders.php');
    exit;
}

// Fetch ordered items
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.image 
    FROM order_items oi 
    JOIN products p ON p.id = oi.product_id 
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

$page_title = 'Order Confirmed - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="card p-4 p-md-5 mx-auto shadow-sm border text-center mb-4" style="max-width: 750px;">
        <div class="d-inline-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle mb-3 mx-auto" style="width: 80px; height: 80px;">
            <i class="bi bi-check-lg display-4 fw-bold"></i>
        </div>
        
        <h2 class="fw-bold text-dark mb-1">Thank You for Your Order!</h2>
        <p class="text-muted mb-4">Your order has been placed and is currently being processed by our fulfillment team.</p>

        <!-- Order Highlight Box -->
        <div class="p-4 bg-light rounded-3 text-start mb-4 border">
            <div class="row g-3">
                <div class="col-sm-6">
                    <small class="text-muted text-uppercase d-block fw-semibold fs-8">Order Reference Number</small>
                    <span class="fs-5 fw-bold text-primary"><?= htmlspecialchars($order['order_number'] ?? '#' . $order['id']) ?></span>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted text-uppercase d-block fw-semibold fs-8">Order Date & Time</small>
                    <span class="fs-6 fw-semibold text-dark"><?= date('F d, Y · h:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted text-uppercase d-block fw-semibold fs-8">Payment Method & Status</small>
                    <span class="badge bg-white text-dark border me-1"><?= htmlspecialchars($order['payment_method']) ?></span>
                    <span class="badge bg-success"><?= htmlspecialchars($order['payment_status']) ?></span>
                </div>
                <div class="col-sm-6">
                    <small class="text-muted text-uppercase d-block fw-semibold fs-8">Estimated Delivery</small>
                    <span class="fs-6 fw-bold text-success"><i class="bi bi-calendar-event me-1"></i>Within 3 - 5 Business Days</span>
                </div>
            </div>
        </div>

        <!-- Ordered Items Preview -->
        <div class="text-start mb-4">
            <h6 class="fw-bold text-muted text-uppercase fs-7 mb-3">Items in this shipment:</h6>
            <div class="list-group list-group-flush border-top border-bottom">
                <?php foreach ($items as $it): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-3">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($it['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 55px; height: 55px; object-fit: cover;" alt="">
                            <div>
                                <span class="fw-bold d-block"><?= htmlspecialchars($it['product_name']) ?></span>
                                <small class="text-muted">Quantity: <?= $it['quantity'] ?> · Price: <?= format_price($it['final_price']) ?></small>
                            </div>
                        </div>
                        <span class="fw-bold text-dark"><?= format_price($it['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Financial Breakdown -->
        <div class="text-start bg-light p-3 rounded-3 mb-4 border">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Subtotal:</span>
                <span><?= format_price($order['subtotal']) ?></span>
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
                <span class="text-muted">Tax (GST):</span>
                <span><?= format_price($order['tax']) ?></span>
            </div>
            <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                <span>Grand Total:</span>
                <span class="text-success"><?= format_price($order['total_amount']) ?></span>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="order-details.php?id=<?= $order['id'] ?>" class="btn btn-outline-primary fw-semibold px-4">
                <i class="bi bi-eye me-1"></i>View Order Details
            </a>
            <a href="track-order.php?id=<?= $order['id'] ?>" class="btn btn-primary fw-bold px-4">
                <i class="bi bi-geo-alt me-1"></i>Track Order
            </a>
            <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline-secondary fw-semibold px-4">
                <i class="bi bi-printer me-1"></i>Print Invoice
            </a>
            <a href="products.php" class="btn btn-warning fw-bold px-4">
                <i class="bi bi-shop me-1"></i>Continue Shopping
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>