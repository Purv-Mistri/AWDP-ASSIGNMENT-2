<?php 
require_once '../includes/admin-auth.php';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'Pending');

    $valid_statuses = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled'];
    if ($order_id > 0 && in_array($new_status, $valid_statuses)) {
        // If changing to Cancelled, restore stock if it was not already cancelled
        $curr_stmt = $pdo->prepare("SELECT order_status FROM orders WHERE id = ?");
        $curr_stmt->execute([$order_id]);
        $current_status = $curr_stmt->fetchColumn();

        if ($new_status === 'Cancelled' && $current_status !== 'Cancelled') {
            $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$order_id]);
            $items = $items_stmt->fetchAll();
            $restore_stmt = $pdo->prepare("UPDATE products SET stock = stock + ?, status = IF(status = 'Out of Stock' AND stock + ? > 0, 'Active', status) WHERE id = ?");
            foreach ($items as $it) {
                $restore_stmt->execute([(int)$it['quantity'], (int)$it['quantity'], (int)$it['product_id']]);
            }
        }

        $up = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
        $up->execute([$new_status, $order_id]);
        set_flash('success', "Order #{$order_id} status updated to '{$new_status}'.");
    }
    header('Location: orders.php');
    exit;
}

$status_filter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

$query = "SELECT o.*, u.name AS user_name, u.email AS user_email, u.mobile AS user_mobile FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE 1=1";
$params = [];

if ($status_filter) {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
}
if ($search) {
    $query .= " AND (o.id = ? OR o.order_number LIKE ? OR o.shipping_full_name LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ? OR o.shipping_mobile LIKE ?)";
    $params = array_merge($params, [(int)$search, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}

$query .= " ORDER BY o.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = 'Manage Orders - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';
?>

<div class="container pb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-receipt me-2 text-warning"></i>Customer Orders Management</h2>
            <p class="text-muted mb-0">Track and update customer shipments and fulfillment statuses</p>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="card p-3 shadow-sm border mb-4">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by Order #, Customer Name, Email, Mobile..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="Confirmed" <?= $status_filter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="Packed" <?= $status_filter === 'Packed' ? 'selected' : '' ?>>Packed</option>
                    <option value="Shipped" <?= $status_filter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="Out for Delivery" <?= $status_filter === 'Out for Delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                    <option value="Delivered" <?= $status_filter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($search || $status_filter): ?>
                    <a href="orders.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Orders Cards -->
    <?php if (empty($orders)): ?>
        <div class="card text-center p-5 shadow-sm">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <h4 class="fw-bold mt-3">No Orders Found</h4>
            <p class="text-muted">No orders match the selected search or filter parameters.</p>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($orders as $o): 
                // Fetch items for this order
                $i_stmt = $pdo->prepare("SELECT oi.*, p.image, p.brand as prod_brand FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
                $i_stmt->execute([$o['id']]);
                $items = $i_stmt->fetchAll();

                $ord_st = $o['order_status'] ?? 'Pending';
                $status_class = match(strtolower($ord_st)) {
                    'confirmed' => 'bg-info text-dark',
                    'packed', 'shipped' => 'bg-primary',
                    'out for delivery' => 'bg-warning text-dark',
                    'delivered' => 'bg-success',
                    'cancelled' => 'bg-danger',
                    default => 'bg-warning text-dark'
                };
            ?>
                <div class="card shadow-sm border">
                    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-3">
                        <div>
                            <span class="fw-bold fs-5 me-2"><?= htmlspecialchars($o['order_number'] ?? 'Order #' . $o['id']) ?></span>
                            <span class="badge <?= $status_class ?> px-3 py-2 text-uppercase"><?= htmlspecialchars($ord_st) ?></span>
                            <span class="text-muted small ms-2"><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></span>
                        </div>
                        <div class="mt-2 mt-md-0 d-flex align-items-center gap-3">
                            <div>
                                <span class="text-muted me-1">Total:</span>
                                <span class="fw-bold text-success fs-5"><?= format_price($o['total_amount']) ?></span>
                            </div>
                            <a href="../invoice.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-outline-secondary btn-sm" title="Print Invoice">
                                <i class="bi bi-printer me-1"></i>Invoice
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Customer Details -->
                            <div class="col-md-4">
                                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-2"><i class="bi bi-person me-1"></i>Customer Details</h6>
                                <p class="mb-1 fw-bold"><?= htmlspecialchars($o['user_name'] ?? $o['shipping_full_name'] ?? 'Customer') ?></p>
                                <p class="mb-1 text-muted small"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($o['user_email'] ?? 'N/A') ?></p>
                                <p class="mb-2 text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($o['user_mobile'] ?? $o['shipping_mobile'] ?? 'N/A') ?></p>
                                
                                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-1 mt-3"><i class="bi bi-geo-alt me-1"></i>Delivery Address</h6>
                                <p class="small text-secondary mb-2">
                                    <strong><?= htmlspecialchars($o['shipping_full_name']) ?></strong><br>
                                    <?= nl2br(htmlspecialchars($o['shipping_address'])) ?><br>
                                    <?= htmlspecialchars($o['shipping_city']) ?>, <?= htmlspecialchars($o['shipping_state']) ?> - <?= htmlspecialchars($o['shipping_pincode']) ?><br>
                                    Phone: <?= htmlspecialchars($o['shipping_mobile']) ?>
                                </p>

                                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-1"><i class="bi bi-credit-card me-1"></i>Payment Method</h6>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($o['payment_method']) ?> (<?= htmlspecialchars($o['payment_status']) ?>)</span>
                            </div>

                            <!-- Ordered Items -->
                            <div class="col-md-5 border-start-md border-end-md">
                                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-2"><i class="bi bi-box me-1"></i>Ordered Items (<?= count($items) ?>)</h6>
                                <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                                    <?php foreach ($items as $it): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 border-bottom">
                                            <div class="d-flex align-items-center">
                                                <img src="../<?= htmlspecialchars($it['image'] ?? 'assets/images/default.jpg') ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                <div>
                                                    <span class="fw-bold d-block text-truncate" style="max-width: 180px; font-size: 0.85rem;"><?= htmlspecialchars($it['product_name']) ?></span>
                                                    <small class="text-muted"><?= $it['quantity'] ?> × <?= format_price($it['final_price']) ?></small>
                                                </div>
                                            </div>
                                            <span class="fw-semibold text-dark"><?= format_price($it['subtotal']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Status Changer Form -->
                            <div class="col-md-3">
                                <h6 class="fw-bold text-muted text-uppercase fs-7 mb-2"><i class="bi bi-gear me-1"></i>Update Fulfillment</h6>
                                <form method="post" action="orders.php" class="bg-light p-3 rounded-3 border">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <label class="form-label small fw-semibold">Order Status</label>
                                    <select name="status" class="form-select form-select-sm mb-3">
                                        <option value="Pending" <?= $ord_st === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Confirmed" <?= $ord_st === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="Packed" <?= $ord_st === 'Packed' ? 'selected' : '' ?>>Packed</option>
                                        <option value="Shipped" <?= $ord_st === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="Out for Delivery" <?= $ord_st === 'Out for Delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                        <option value="Delivered" <?= $ord_st === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="Cancelled" <?= $ord_st === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                        <i class="bi bi-check2 me-1"></i>Save Status
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>