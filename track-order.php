<?php 
require_once 'config/database.php';

$order_id = (int)($_GET['id'] ?? 0);
$search_ref = trim($_GET['ref'] ?? '');
$order = null;

if ($order_id > 0) {
    if (is_user_logged_in()) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, current_user_id()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
    }
    $order = $stmt->fetch();
} elseif (!empty($search_ref)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ?");
    $stmt->execute([$search_ref, (int)$search_ref]);
    $order = $stmt->fetch();
}

// Fetch items if order found
$items = [];
if ($order) {
    $items_stmt = $pdo->prepare("SELECT oi.*, p.image FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
    $items_stmt->execute([$order['id']]);
    $items = $items_stmt->fetchAll();
}

$statuses_flow = [
    'Pending' => ['step' => 1, 'label' => 'Order Placed', 'icon' => 'bi-bag-check', 'desc' => 'Order received and logged in system.'],
    'Confirmed' => ['step' => 2, 'label' => 'Confirmed', 'icon' => 'bi-check-circle', 'desc' => 'Payment verified and order approved.'],
    'Packed' => ['step' => 3, 'label' => 'Packed', 'icon' => 'bi-box-seam', 'desc' => 'Items boxed and labeled at warehouse.'],
    'Shipped' => ['step' => 4, 'label' => 'Shipped', 'icon' => 'bi-truck', 'desc' => 'Package handed over to courier partner.'],
    'Out for Delivery' => ['step' => 5, 'label' => 'Out for Delivery', 'icon' => 'bi-bicycle', 'desc' => 'Delivery agent is en route to your address.'],
    'Delivered' => ['step' => 6, 'label' => 'Delivered', 'icon' => 'bi-house-check', 'desc' => 'Package successfully handed over to recipient.']
];

$current_status = $order ? ($order['order_status'] ?? $order['status'] ?? 'Pending') : 'Pending';
$current_step = ($current_status === 'Cancelled') ? -1 : ($statuses_flow[$current_status]['step'] ?? 1);

$page_title = 'Track Shipment - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <?php if (is_user_logged_in()): ?>
                <li class="breadcrumb-item"><a href="orders.php" class="text-decoration-none">My Orders</a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page">Order Tracking</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <h2 class="display-6 fw-bold mb-2"><i class="bi bi-geo-alt-fill text-warning me-2"></i>Live Order Tracking</h2>
        <p class="text-muted mx-auto" style="max-width: 540px;">Check current shipping progression, carrier updates, and expected delivery schedule</p>
        
        <!-- Search Box -->
        <div class="card p-3 shadow-sm mx-auto border" style="max-width: 500px;">
            <form method="get" action="track-order.php" class="d-flex gap-2">
                <input type="text" name="ref" class="form-control" placeholder="Enter Order # (e.g. ORD-20260810-1001 or ID)" value="<?= htmlspecialchars($search_ref ?: ($order['order_number'] ?? '')) ?>" required>
                <button type="submit" class="btn btn-warning fw-bold px-4">Track</button>
            </form>
        </div>
    </div>

    <?php if ($order): ?>
        <!-- Order Tracking Card -->
        <div class="card p-4 p-md-5 shadow-sm border mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom gap-2">
                <div>
                    <span class="text-muted small">Tracking Order</span>
                    <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($order['order_number'] ?? '#' . $order['id']) ?></h4>
                </div>
                <div class="text-md-end">
                    <span class="text-muted small">Placed on: <?= date('M d, Y · h:i A', strtotime($order['created_at'])) ?></span>
                    <div class="fw-bold text-success fs-5"><?= format_price($order['total_amount'] ?? $order['total']) ?></div>
                </div>
            </div>

            <?php if ($current_status === 'Cancelled'): ?>
                <div class="alert alert-danger p-4 text-center">
                    <i class="bi bi-x-octagon-fill display-5 d-block mb-2"></i>
                    <h4 class="fw-bold">This Order has been Cancelled</h4>
                    <p class="mb-0 small">This order was cancelled before fulfillment. Any payments made will be refunded within 3-5 business days.</p>
                </div>
            <?php else: ?>
                <!-- Visual Stepper / Tracking Progression -->
                <div class="tracking-timeline-wrapper my-4">
                    <div class="row g-2 justify-content-between text-center position-relative">
                        <?php foreach ($statuses_flow as $st_key => $st_info): 
                            $is_passed = ($current_step >= $st_info['step']);
                            $is_current = ($current_step === $st_info['step']);
                        ?>
                            <div class="col-md-2 col-4 mb-3">
                                <div class="timeline-step">
                                    <div class="timeline-icon mx-auto rounded-circle d-flex align-items-center justify-content-center mb-2 shadow-sm <?= $is_current ? 'bg-warning text-dark border border-3 border-dark fw-bold' : ($is_passed ? 'bg-success text-white' : 'bg-light text-muted border') ?>" style="width: 48px; height: 48px; font-size: 1.2rem;">
                                        <i class="bi <?= $st_info['icon'] ?>"></i>
                                    </div>
                                    <h6 class="fw-bold small mb-1 <?= $is_current ? 'text-warning text-dark-emphasis' : ($is_passed ? 'text-success' : 'text-muted') ?>">
                                        <?= $st_info['label'] ?>
                                    </h6>
                                    <small class="text-muted d-none d-md-block fs-8" style="font-size: 0.75rem;"><?= $st_info['desc'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                    <div>
                        <span class="badge bg-success px-3 py-2 text-uppercase fs-7 mb-1">Status: <?= htmlspecialchars($current_status) ?></span>
                        <p class="small text-muted mb-0">Delivery Address: <b><?= htmlspecialchars($order['shipping_address'] ?? $order['address'] ?? '') ?></b></p>
                    </div>
                    <div>
                        <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-printer me-1"></i>Print Invoice
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Package Items Summary -->
            <div class="mt-4 pt-3 border-top">
                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-3">Package Contents:</h6>
                <div class="row g-3">
                    <?php foreach ($items as $it): ?>
                        <div class="col-md-6">
                            <div class="p-2 border rounded-3 d-flex align-items-center gap-3 bg-white">
                                <img src="<?= htmlspecialchars($it['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                <div class="text-truncate">
                                    <a href="product-details.php?id=<?= $it['product_id'] ?>" class="fw-bold text-dark text-decoration-none small d-block text-truncate"><?= htmlspecialchars($it['product_name']) ?></a>
                                    <small class="text-muted">Qty: <?= $it['quantity'] ?> · <?= format_price($it['final_price']) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php elseif ($order_id > 0 || !empty($search_ref)): ?>
        <div class="card p-5 text-center shadow-sm border max-w-md mx-auto" style="max-width: 550px;">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h4 class="fw-bold mt-3">Order Not Found</h4>
            <p class="text-muted">We could not find any order matching the reference details provided.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
