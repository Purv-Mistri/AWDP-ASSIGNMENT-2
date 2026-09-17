<?php 
require_once '../includes/admin-auth.php';

$page_title = 'Sales Reports & Analytics - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';

// Date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Core Report Aggregates
$total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$total_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$delivered_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Delivered'")->fetchColumn();
$cancelled_orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Cancelled'")->fetchColumn();
$avg_order_value = $total_orders > 0 ? ($total_revenue / max(1, ($total_orders - $cancelled_orders))) : 0;
$total_discounts_given = (float)$pdo->query("SELECT COALESCE(SUM(discount_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$total_tax_collected = (float)$pdo->query("SELECT COALESCE(SUM(tax), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();

// Order Status Distribution
$status_counts = $pdo->query("SELECT order_status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount FROM orders GROUP BY order_status")->fetchAll();

// Category Sales
$category_sales = $pdo->query("
    SELECT c.name as cat_name, 
           COUNT(DISTINCT oi.order_id) as total_orders, 
           COALESCE(SUM(oi.quantity), 0) as units_sold, 
           COALESCE(SUM(oi.subtotal), 0) as revenue
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY c.id
    ORDER BY revenue DESC
")->fetchAll();

// Top Selling Products
$top_products = $pdo->query("
    SELECT p.name, p.brand, p.image, c.name as category_name, 
           COALESCE(SUM(oi.quantity), 0) as total_sold, 
           COALESCE(SUM(oi.subtotal), 0) as total_revenue
    FROM products p
    JOIN categories c ON c.id = p.category_id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC, total_revenue DESC
    LIMIT 8
")->fetchAll();

// Top Customers by Spend
$top_customers = $pdo->query("
    SELECT u.name, u.username, u.email, u.mobile, 
           COUNT(o.id) as order_count, 
           COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM users u
    JOIN orders o ON o.user_id = u.id
    WHERE o.order_status != 'Cancelled'
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 6
")->fetchAll();
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-graph-up-arrow me-2 text-warning"></i>Store Sales & Financial Reports</h2>
            <p class="text-muted mb-0">Business performance metrics, category revenue contribution, product velocity, and customer loyalty</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm fw-semibold">
                <i class="bi bi-printer me-1"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Summary KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-primary text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Total Net Revenue</div>
                        <h3 class="fw-bold mb-1"><?= format_price($total_revenue) ?></h3>
                        <small class="text-white-50"><i class="bi bi-check-circle me-1"></i>Delivered & Active orders</small>
                    </div>
                    <i class="bi bi-wallet2 display-5 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-dark text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-warning small fw-semibold text-uppercase">Average Order Value</div>
                        <h3 class="fw-bold mb-1 text-warning"><?= format_price($avg_order_value) ?></h3>
                        <small class="text-light opacity-75">Per completed transaction</small>
                    </div>
                    <i class="bi bi-calculator display-5 text-warning opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-success text-white h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-white-50 small fw-semibold text-uppercase">Total Fulfilled Orders</div>
                        <h3 class="fw-bold mb-1"><?= $delivered_orders ?> <span class="fs-6 fw-normal">Delivered</span></h3>
                        <small class="text-white-50"><?= $total_orders ?> total placed · <?= $cancelled_orders ?> cancelled</small>
                    </div>
                    <i class="bi bi-check-all display-5 opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card p-3 shadow-sm border-0 bg-warning text-dark h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-dark-50 small fw-bold text-uppercase">Promotions & GST Tax</div>
                        <h4 class="fw-bold mb-0">Coupons: <?= format_price($total_discounts_given) ?></h4>
                        <small class="fw-semibold">GST Collected: <?= format_price($total_tax_collected) ?></small>
                    </div>
                    <i class="bi bi-receipt-cutoff display-5 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Sales Breakdown & Order Status Breakdown -->
    <div class="row g-4 mb-4">
        <!-- Category Sales -->
        <div class="col-lg-8">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Department & Category Revenue Performance</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Category Name</th>
                                <th>Orders Count</th>
                                <th>Units Sold</th>
                                <th class="text-end">Revenue Contribution</th>
                                <th style="width: 25%;">Share of Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_sales as $cs): 
                                $pct = $total_revenue > 0 ? round(($cs['revenue'] / $total_revenue) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($cs['cat_name']) ?></td>
                                    <td><?= $cs['total_orders'] ?> orders</td>
                                    <td><span class="badge bg-light text-dark border"><?= $cs['units_sold'] ?> units</span></td>
                                    <td class="text-end fw-bold text-success"><?= format_price($cs['revenue']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%"></div>
                                            </div>
                                            <small class="text-muted fw-semibold" style="width: 45px;"><?= $pct ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Order Fulfillment Status Breakdown -->
        <div class="col-lg-4">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-truck me-2 text-warning"></i>Order Status Breakdown</h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($status_counts as $sc): 
                            $badge_color = match(strtolower($sc['order_status'])) {
                                'confirmed' => 'bg-info text-dark',
                                'packed', 'shipped' => 'bg-primary text-white',
                                'out for delivery' => 'bg-warning text-dark',
                                'delivered' => 'bg-success text-white',
                                'cancelled' => 'bg-danger text-white',
                                default => 'bg-secondary text-white'
                            };
                        ?>
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border">
                                <div>
                                    <span class="badge <?= $badge_color ?> px-2 py-1 text-uppercase mb-1"><?= htmlspecialchars($sc['order_status']) ?></span>
                                    <div class="small text-muted">Value: <?= format_price($sc['amount']) ?></div>
                                </div>
                                <h4 class="fw-bold text-dark mb-0"><?= $sc['count'] ?> <small class="fs-7 text-muted">orders</small></h4>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Top Selling Products & Top Customers -->
    <div class="row g-4">
        <!-- Top Selling Products -->
        <div class="col-lg-6">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Top Velocity Products</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product Details</th>
                                <th>Units Sold</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $tp): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="../<?= htmlspecialchars($tp['image']) ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;">
                                            <div>
                                                <span class="fw-bold d-block text-truncate" style="max-width: 220px; font-size: 0.85rem;"><?= htmlspecialchars($tp['name']) ?></span>
                                                <small class="text-muted"><?= htmlspecialchars($tp['brand']) ?> · <?= htmlspecialchars($tp['category_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><b><?= $tp['total_sold'] ?></b> units</td>
                                    <td class="text-end fw-bold text-success"><?= format_price($tp['total_revenue']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Customer Accounts -->
        <div class="col-lg-6">
            <div class="card shadow-sm border h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Top Customer Lifetime Value</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Completed Orders</th>
                                <th class="text-end">Total Spend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_customers as $tc): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-dark text-warning rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($tc['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="fw-bold d-block"><?= htmlspecialchars($tc['name']) ?></span>
                                                <small class="text-muted">@<?= htmlspecialchars($tc['username']) ?> · <?= htmlspecialchars($tc['mobile']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= $tc['order_count'] ?> Orders</span></td>
                                    <td class="text-end fw-bold text-success fs-6"><?= format_price($tc['total_spent']) ?></td>
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