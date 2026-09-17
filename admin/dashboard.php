<?php 
require_once '../includes/admin-auth.php';

$page_title = 'Admin Dashboard - EveNeed';
include '../includes/header.php';
include '../includes/admin-navbar.php';

// Quick Action: Approve / Unblock user from dashboard
if (isset($_GET['approve_user'])) {
    $uid = (int)$_GET['approve_user'];
    $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?")->execute([$uid]);
    set_flash('success', "User #{$uid} approved and unblocked successfully.");
    header('Location:dashboard.php');
    exit;
}

// 1. Core Metrics
$user_count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Active'")->fetchColumn();
$blocked_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='Blocked'")->fetchColumn();

$product_count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$low_stock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetchColumn();
$out_of_stock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 0 OR status='Out of Stock'")->fetchColumn();

$category_count = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

$order_count = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Pending', 'Confirmed', 'Packed', 'Shipped', 'Out for Delivery')")->fetchColumn();
$completed_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Delivered'")->fetchColumn();

$total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();

// 2. Recent 5 Orders
$recent_orders = $pdo->query("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 5")->fetchAll();

// 3. Recent 5 Users (Highlighting Blocked ones requiring approval)
$recent_users = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 5")->fetchAll();

// 4. Low-Stock & Out-of-Stock Items (Limit 5)
$low_stock_items = $pdo->query("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.stock <= 5 ORDER BY p.stock ASC LIMIT 5")->fetchAll();

// 5. Sales by Category
$cat_sales = $pdo->query("
    SELECT c.name as category_name, COUNT(DISTINCT oi.order_id) as orders_count, COALESCE(SUM(oi.subtotal), 0) as total_revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY c.id
    ORDER BY total_revenue DESC
    LIMIT 5
")->fetchAll();
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2 text-warning"></i>Store Administration Dashboard</h2>
            <p class="text-muted mb-0">Live operations overview, sales revenue, catalog alerts, and pending customer approvals</p>
        </div>
        <div class="d-flex gap-2">
            <a href="products.php" class="btn btn-warning fw-bold btn-sm shadow-sm">
                <i class="bi bi-plus-circle me-1"></i>Add Product
            </a>
            <a href="reports.php" class="btn btn-dark btn-sm fw-semibold shadow-sm">
                <i class="bi bi-graph-up me-1"></i>View Reports
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="row g-3 mb-4">
        <!-- Revenue -->
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-primary text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Total Sales Revenue</div>
                        <h3 class="fw-bold mb-1"><?= format_price($total_revenue) ?></h3>
                        <small class="text-white-50"><i class="bi bi-check-circle me-1"></i>Excludes cancelled orders</small>
                    </div>
                    <i class="bi bi-currency-rupee display-5 opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Orders -->
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-dark text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-warning small fw-semibold text-uppercase">Total Orders</div>
                        <h3 class="fw-bold mb-1 text-warning"><?= $order_count ?></h3>
                        <small class="text-light opacity-75"><?= $pending_orders ?> active · <?= $completed_orders ?> delivered</small>
                    </div>
                    <i class="bi bi-receipt display-5 text-warning opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Products & Categories -->
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-success text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Catalog & Categories</div>
                        <h3 class="fw-bold mb-1"><?= $product_count ?> <span class="fs-6 fw-normal">Items</span></h3>
                        <small class="text-white-50"><?= $category_count ?> categories · <?= $out_of_stock ?> out of stock</small>
                    </div>
                    <i class="bi bi-box-seam display-5 opacity-50"></i>
                </div>
            </div>
        </div>

        <!-- Users & Approval Alerts -->
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 <?= $blocked_users > 0 ? 'bg-danger' : 'bg-secondary' ?> text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Customers (<?= $user_count ?> Total)</div>
                        <h3 class="fw-bold mb-1"><?= $blocked_users ?> <span class="fs-6 fw-normal">Blocked/Pending</span></h3>
                        <small class="text-white-50"><?= $active_users ?> approved active users</small>
                    </div>
                    <i class="bi bi-people display-5 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Recent Orders Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Customer Orders</h5>
                    <a href="orders.php" class="btn btn-outline-primary btn-sm">View All Orders</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No orders found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $ro): 
                                    $st = $ro['order_status'] ?? $ro['status'] ?? 'Pending';
                                    $st_class = match(strtolower($st)) {
                                        'confirmed' => 'bg-info text-dark',
                                        'packed', 'shipped' => 'bg-primary',
                                        'out for delivery' => 'bg-warning text-dark',
                                        'delivered' => 'bg-success',
                                        'cancelled' => 'bg-danger',
                                        default => 'bg-warning text-dark'
                                    };
                                ?>
                                    <tr>
                                        <td><b><?= htmlspecialchars($ro['order_number'] ?? '#' . $ro['id']) ?></b></td>
                                        <td>
                                            <span class="d-block fw-semibold"><?= htmlspecialchars($ro['user_name'] ?? 'Customer') ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($ro['user_email'] ?? '') ?></small>
                                        </td>
                                        <td class="small"><?= date('M d, Y', strtotime($ro['created_at'])) ?></td>
                                        <td class="fw-bold text-success"><?= format_price($ro['total_amount'] ?? $ro['total']) ?></td>
                                        <td><span class="badge <?= $st_class ?>"><?= htmlspecialchars($st) ?></span></td>
                                        <td class="text-end">
                                            <a href="orders.php?search=<?= $ro['id'] ?>" class="btn btn-outline-dark btn-sm">Manage</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Registered Users & Pending Approvals -->
        <div class="col-lg-4">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-check me-2 text-warning"></i>Customer Registrations</h5>
                    <a href="users.php" class="btn btn-outline-secondary btn-sm">Manage</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_users as $ru): ?>
                        <div class="list-group-item px-3 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($ru['name']) ?></span>
                                <small class="text-muted d-block">@<?= htmlspecialchars($ru['username']) ?> · <?= htmlspecialchars($ru['mobile']) ?></small>
                                <?php if ($ru['status'] === 'Blocked'): ?>
                                    <span class="badge bg-danger small">Pending Approval / Blocked</span>
                                <?php else: ?>
                                    <span class="badge bg-success small">Active User</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($ru['status'] === 'Blocked'): ?>
                                    <a href="dashboard.php?approve_user=<?= $ru['id'] ?>" class="btn btn-success btn-sm fw-bold px-3" title="Approve & Unblock User">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </a>
                                <?php else: ?>
                                    <a href="users.php?search=<?= urlencode($ru['username']) ?>" class="btn btn-outline-secondary btn-sm">View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Low Stock Items & Sales by Category -->
    <div class="row g-4">
        <!-- Low Stock Alerts -->
        <div class="col-lg-6">
            <div class="card shadow-sm border">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Low Stock Inventory Alerts</h5>
                    <a href="products.php" class="btn btn-outline-danger btn-sm">Stock Management</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Remaining Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($low_stock_items)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">All products have healthy inventory levels.</td></tr>
                            <?php else: ?>
                                <?php foreach ($low_stock_items as $lsi): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold d-block text-truncate" style="max-width: 220px;"><?= htmlspecialchars($lsi['name']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($lsi['brand']) ?></small>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($lsi['cat_name']) ?></td>
                                        <td>
                                            <span class="fw-bold text-danger fs-6"><?= $lsi['stock'] ?> units</span>
                                        </td>
                                        <td>
                                            <?php if ($lsi['stock'] <= 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sales by Category Summary -->
        <div class="col-lg-6">
            <div class="card shadow-sm border">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-info me-2"></i>Top Category Sales Performance</h5>
                    <a href="reports.php" class="btn btn-outline-info btn-sm">Full Analytics</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category</th>
                                <th>Orders Fulfilled</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cat_sales as $cs): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($cs['category_name']) ?></td>
                                    <td><?= $cs['orders_count'] ?> orders</td>
                                    <td class="text-end fw-bold text-success"><?= format_price($cs['total_revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>