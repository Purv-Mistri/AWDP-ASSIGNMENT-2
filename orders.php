<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();

// Handle Order Cancellation (Allowed for Pending or Confirmed orders)
if (isset($_GET['cancel_order'])) {
    $cancel_id = (int)$_GET['cancel_order'];
    $c_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $c_stmt->execute([$cancel_id, $user_id]);
    $ord_to_cancel = $c_stmt->fetch();

    if ($ord_to_cancel && in_array($ord_to_cancel['order_status'], ['Pending', 'Confirmed'])) {
        $pdo->beginTransaction();
        try {
            // Update order status
            $pdo->prepare("UPDATE orders SET order_status = 'Cancelled' WHERE id = ?")->execute([$cancel_id]);

            // Restore product stock
            $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$cancel_id]);
            $items = $items_stmt->fetchAll();

            $restore_stmt = $pdo->prepare("UPDATE products SET stock = stock + ?, status = IF(status = 'Out of Stock' AND stock + ? > 0, 'Active', status) WHERE id = ?");
            foreach ($items as $it) {
                $restore_stmt->execute([(int)$it['quantity'], (int)$it['quantity'], (int)$it['product_id']]);
            }

            $pdo->commit();
            set_flash('success', "Order #{$cancel_id} has been cancelled successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Failed to cancel order: ' . $e->getMessage());
        }
    } else {
        set_flash('danger', 'This order cannot be cancelled at its current fulfillment stage.');
    }
    header('Location: orders.php');
    exit;
}

// Handle Reorder Product (Checks current stock and adds to cart)
if (isset($_GET['reorder_product'])) {
    $pid = (int)$_GET['reorder_product'];
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$pid]);
    $prod = $st->fetch();

    if ($prod && $prod['stock'] > 0 && $prod['status'] === 'Active') {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $curr = $_SESSION['cart'][$pid] ?? 0;
        $_SESSION['cart'][$pid] = min($prod['stock'], $curr + 1);
        set_flash('success', "Added '" . htmlspecialchars($prod['name']) . "' to your shopping cart!");
        header('Location: cart.php');
        exit;
    } else {
        set_flash('danger', 'Product currently out of stock');
        header('Location: orders.php');
        exit;
    }
}

// Fetch all user orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

$page_title = 'My Orders - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Orders</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-box-seam me-2 text-warning"></i>My Orders</h2>
            <p class="text-muted mb-0">Track shipments, reorder items, download invoices, or cancel pending orders</p>
        </div>
        <span class="badge bg-light text-dark border fs-6 px-3 py-2">
            <b><?= count($orders) ?></b> Orders Placed
        </span>
    </div>

    <?php if (empty($orders)): ?>
        <div class="card text-center p-5 shadow-sm border">
            <div class="py-5">
                <i class="bi bi-bag-x fs-1 text-muted" style="font-size: 4rem !important;"></i>
                <h4 class="fw-bold mt-3">No Orders Placed Yet</h4>
                <p class="text-muted">You have not made any purchases with EveNeed yet.</p>
                <a href="products.php" class="btn btn-warning fw-bold px-4 mt-2">
                    <i class="bi bi-cart3 me-1"></i>Start Shopping Now
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ($orders as $o): 
                // Fetch items for this order
                $i_stmt = $pdo->prepare("SELECT oi.*, p.image, p.stock as current_stock, p.status as current_status FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
                $i_stmt->execute([$o['id']]);
                $items = $i_stmt->fetchAll();

                $status = $o['order_status'] ?? $o['status'] ?? 'Pending';
                $status_class = match(strtolower($status)) {
                    'confirmed' => 'bg-info text-dark',
                    'packed' => 'bg-secondary',
                    'shipped' => 'bg-primary',
                    'out for delivery' => 'bg-warning text-dark',
                    'delivered' => 'bg-success',
                    'cancelled' => 'bg-danger',
                    default => 'bg-warning text-dark'
                };

                $can_cancel = in_array($status, ['Pending', 'Confirmed']);
            ?>
                <div class="card shadow-sm border overflow-hidden">
                    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3 border-bottom">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($o['order_number'] ?? 'ORD-#' . $o['id']) ?></span>
                            <span class="text-muted small">Placed on <?= date('M d, Y · h:i A', strtotime($o['created_at'])) ?></span>
                            <span class="badge <?= $status_class ?> px-3 py-2 text-uppercase"><?= htmlspecialchars($status) ?></span>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <span class="text-muted me-1 small">Total Amount:</span>
                            <span class="fw-bold text-success fs-5"><?= format_price($o['total_amount'] ?? $o['total']) ?></span>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <!-- Items List -->
                            <div class="col-lg-8">
                                <h6 class="fw-bold text-muted mb-3 text-uppercase fs-7">Products in this Order:</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($items as $it): 
                                        $is_in_stock = ($it['current_stock'] > 0 && $it['current_status'] === 'Active');
                                    ?>
                                        <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center px-0 py-3 border-0 border-bottom">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?= htmlspecialchars($it['image']) ?>" onerror="this.src='assets/images/default.jpg'" alt="" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;">
                                                <div>
                                                    <a href="product-details.php?id=<?= $it['product_id'] ?>" class="fw-bold text-dark text-decoration-none d-block text-truncate" style="max-width: 320px;">
                                                        <?= htmlspecialchars($it['product_name']) ?>
                                                    </a>
                                                    <small class="text-muted">Quantity: <?= $it['quantity'] ?> · Price: <?= format_price($it['final_price'] ?? $it['price']) ?></small>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 mt-2 mt-md-0">
                                                <span class="fw-bold text-dark"><?= format_price($it['subtotal']) ?></span>
                                                <!-- Reorder single item button -->
                                                <a href="orders.php?reorder_product=<?= $it['product_id'] ?>" class="btn btn-outline-warning btn-sm text-dark fw-bold" title="Reorder this product">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Reorder
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Delivery & Actions Sidebar -->
                            <div class="col-lg-4 border-start-lg">
                                <div class="bg-light p-3 rounded-3 h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-geo-alt me-1 text-danger"></i>Delivery Address</h6>
                                        <p class="small text-secondary mb-3">
                                            <b><?= htmlspecialchars($o['shipping_full_name'] ?? $user_profile['name'] ?? '') ?></b><br>
                                            <?= nl2br(htmlspecialchars($o['shipping_address'] ?? $o['address'] ?? '')) ?>
                                        </p>

                                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-credit-card me-1 text-primary"></i>Payment</h6>
                                        <span class="badge bg-white text-dark border mb-3"><?= htmlspecialchars($o['payment_method']) ?> (<?= htmlspecialchars($o['payment_status']) ?>)</span>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2 pt-3 border-top">
                                        <a href="track-order.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm fw-bold">
                                            <i class="bi bi-geo-alt me-1"></i>Track Order
                                        </a>
                                        <div class="d-flex gap-2">
                                            <a href="order-details.php?id=<?= $o['id'] ?>" class="btn btn-outline-dark btn-sm flex-fill">
                                                <i class="bi bi-eye me-1"></i>Details
                                            </a>
                                            <a href="invoice.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm flex-fill">
                                                <i class="bi bi-printer me-1"></i>Invoice
                                            </a>
                                        </div>
                                        <?php if ($can_cancel): ?>
                                            <a href="orders.php?cancel_order=<?= $o['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this order? Any deducted stock will be released.');">
                                                <i class="bi bi-x-circle me-1"></i>Cancel Order
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>