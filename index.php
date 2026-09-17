<?php 
$page_title = 'Eveneed - Everything You Need, All in One Place';
include 'includes/header.php';
include 'includes/navbar.php'; 

// 1. Fetch all active categories with product counts
$categories = $pdo->query("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status != 'Inactive' WHERE c.status = 'Active' GROUP BY c.id ORDER BY c.name ASC")->fetchAll();

// 2. Fetch Featured Products (High rating / top products)
$featured_products = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status != 'Inactive' ORDER BY p.rating DESC, p.id DESC LIMIT 4")->fetchAll();

// 3. Fetch Today's Deals (Highest discount products)
$deals_products = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'Active' AND p.discount > 0 ORDER BY p.discount DESC LIMIT 4")->fetchAll();

// 4. Fetch Best-Selling Products (Orders count)
$bestsellers = $pdo->query("SELECT p.*, c.name AS category_name, COALESCE(SUM(oi.quantity), 0) as total_sold FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN order_items oi ON oi.product_id = p.id WHERE p.status != 'Inactive' GROUP BY p.id ORDER BY total_sold DESC, p.rating DESC LIMIT 4")->fetchAll();

// 5. Fetch New Arrivals (Latest added)
$new_arrivals = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status != 'Inactive' ORDER BY p.id DESC LIMIT 4")->fetchAll();

// 6. Fetch Low-Stock Products (stock between 1 and 5)
$low_stock_products = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'Active' AND p.stock > 0 AND p.stock <= 5 ORDER BY p.stock ASC LIMIT 4")->fetchAll();

// 7. Fetch Recently Viewed Products from session
$recently_viewed_products = [];
if (!empty($_SESSION['recently_viewed']) && is_array($_SESSION['recently_viewed'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['recently_viewed']), '?'));
    $rv_stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id IN ($placeholders) AND p.status != 'Inactive'");
    $rv_stmt->execute($_SESSION['recently_viewed']);
    $recently_viewed_products = $rv_stmt->fetchAll();
}
?>

<!-- Hero Banner Section -->
<section class="hero text-center text-md-start">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold mb-3 shadow-sm">
                    <i class="bi bi-fire me-1"></i>MEGA FESTIVAL SALE IS LIVE
                </span>
                <h1 class="display-4 fw-bold text-white mb-3 tracking-tight">
                    Everything You Need, <br class="d-none d-lg-inline"><span class="text-warning">All in One Place.</span>
                </h1>
                <p class="lead text-light mb-4 opacity-90" style="max-width: 580px;">
                    Discover curated electronics, smartphones, laptops, fashion, groceries, and beauty essentials with verified buyer reviews, instant simulated checkout, and reliable order tracking.
                </p>
                <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-md-start">
                    <a class="btn btn-warning btn-lg px-4 fw-bold shadow-sm" href="products.php">
                        <i class="bi bi-bag-fill me-2"></i>Explore Products
                    </a>
                    <a class="btn btn-outline-light btn-lg px-4 rounded-3" href="categories.php">
                        <i class="bi bi-grid-fill me-2"></i>Browse Categories
                    </a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="hero-image-wrapper p-3 bg-white bg-opacity-10 rounded-4 shadow-lg border border-white border-opacity-10 backdrop-blur">
                    <img src="assets/images/prod_samsung_s24.svg" alt="EveNeed Flagship Collection" class="img-fluid rounded-3" style="max-height: 300px; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <!-- Value Propositions / Customer Benefits -->
    <div class="row g-3 mb-5">
        <div class="col-md-3 col-6">
            <div class="feature-box h-100 shadow-sm p-3 bg-white rounded-3 border text-center">
                <i class="bi bi-truck text-warning fs-2 mb-2 d-inline-block"></i>
                <h6 class="fw-bold mb-1">Free Delivery</h6>
                <small class="text-muted">On all orders above ₹499</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box h-100 shadow-sm p-3 bg-white rounded-3 border text-center">
                <i class="bi bi-shield-check text-success fs-2 mb-2 d-inline-block"></i>
                <h6 class="fw-bold mb-1">100% Genuine</h6>
                <small class="text-muted">Direct from authorized brands</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box h-100 shadow-sm p-3 bg-white rounded-3 border text-center">
                <i class="bi bi-arrow-repeat text-primary fs-2 mb-2 d-inline-block"></i>
                <h6 class="fw-bold mb-1">Easy Returns</h6>
                <small class="text-muted">7-day hassle-free replacement</small>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="feature-box h-100 shadow-sm p-3 bg-white rounded-3 border text-center">
                <i class="bi bi-headset text-danger fs-2 mb-2 d-inline-block"></i>
                <h6 class="fw-bold mb-1">24/7 Support</h6>
                <small class="text-muted">Dedicated student & viva assistance</small>
            </div>
        </div>
    </div>

    <!-- Shop by Category -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-grid me-2 text-warning"></i>Shop by Category</h3>
            <p class="text-muted small mb-0">Explore our wide selection across 10 official departments</p>
        </div>
        <a href="categories.php" class="btn btn-outline-primary btn-sm">View All Categories <i class="bi bi-arrow-right"></i></a>
    </div>
    
    <div class="row g-3 mb-5">
        <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
            <div class="col-lg-2 col-md-4 col-6">
                <a href="products.php?cat=<?= (int)$cat['id'] ?>" class="card text-decoration-none text-dark h-100 text-center p-3 hover-shadow border">
                    <div class="mb-2">
                        <i class="bi <?= htmlspecialchars($cat['icon'] ?? 'bi-tag-fill') ?> text-primary fs-2"></i>
                    </div>
                    <div class="fw-bold text-truncate"><?= htmlspecialchars($cat['name']) ?></div>
                    <small class="text-muted"><?= $cat['product_count'] ?> items</small>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Today's Deals Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <span class="badge bg-danger px-2 py-1 mb-1">🔥 SPECIAL OFFERS</span>
            <h3 class="fw-bold mb-0">Today's Mega Deals</h3>
            <p class="text-muted small mb-0">Big discounts on premium electronics and fashion</p>
        </div>
        <a href="products.php?discount=15" class="btn btn-outline-danger btn-sm">See All Deals <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="row g-4 mb-5">
        <?php foreach ($deals_products as $p): 
            $discounted = $p['price'] * (1 - ($p['discount'] / 100));
        ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 product-card shadow-sm border position-relative">
                    <?php if ($p['discount'] > 0): ?>
                        <span class="badge bg-danger position-absolute top-0 start-0 m-2 px-2 py-1 fw-bold">
                            <?= (int)$p['discount'] ?>% OFF
                        </span>
                    <?php endif; ?>

                    <a href="product-details.php?id=<?= $p['id'] ?>" class="text-center p-3 bg-light">
                        <img src="<?= htmlspecialchars($p['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="<?= htmlspecialchars($p['name']) ?>">
                    </a>

                    <div class="card-body d-flex flex-column">
                        <div class="text-muted small mb-1"><?= htmlspecialchars($p['brand'] ?? 'EveNeed') ?> · <?= htmlspecialchars($p['category_name']) ?></div>
                        <h6 class="card-title fw-bold mb-2">
                            <a href="product-details.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark text-truncate d-block">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                        </h6>
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-warning me-1">★</span>
                            <span class="small fw-semibold"><?= number_format((float)$p['rating'], 1) ?></span>
                            <span class="text-muted small ms-1">(Verified)</span>
                        </div>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold fs-5 text-dark"><?= format_price($discounted) ?></span>
                                <span class="text-muted text-decoration-line-through small ms-1"><?= format_price($p['price']) ?></span>
                            </div>
                            <a href="product-details.php?id=<?= $p['id'] ?>" class="btn btn-warning btn-sm fw-bold">
                                View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Featured Products Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Featured Products</h3>
            <p class="text-muted small mb-0">Top-rated items handpicked for outstanding performance</p>
        </div>
        <a href="products.php" class="btn btn-outline-primary btn-sm">Explore More <i class="bi bi-arrow-right"></i></a>
    </div>

    <div class="row g-4 mb-5">
        <?php foreach ($featured_products as $p): 
            $discounted = $p['price'] * (1 - ($p['discount'] / 100));
        ?>
            <div class="col-lg-3 col-md-6">
                <div class="card h-100 product-card shadow-sm border">
                    <a href="product-details.php?id=<?= $p['id'] ?>" class="text-center p-3 bg-light">
                        <img src="<?= htmlspecialchars($p['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="<?= htmlspecialchars($p['name']) ?>">
                    </a>
                    <div class="card-body d-flex flex-column">
                        <div class="text-muted small mb-1"><?= htmlspecialchars($p['brand']) ?> · <?= htmlspecialchars($p['category_name']) ?></div>
                        <h6 class="card-title fw-bold mb-2">
                            <a href="product-details.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark text-truncate d-block">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                        </h6>
                        <div class="d-flex align-items-center mb-2">
                            <span class="text-warning me-1">★</span>
                            <span class="small fw-semibold"><?= number_format((float)$p['rating'], 1) ?></span>
                            <span class="text-success small ms-2"><i class="bi bi-check-circle-fill me-1"></i>In Stock</span>
                        </div>
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold fs-5 text-dark"><?= format_price($discounted) ?></span>
                                <?php if ($p['discount'] > 0): ?>
                                    <span class="text-muted text-decoration-line-through small ms-1"><?= format_price($p['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="product-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-dark btn-sm fw-semibold">
                                Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Best-Sellers & New Arrivals Tabs/Row -->
    <div class="row g-4 mb-5">
        <!-- Best Sellers -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm h-100 border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Best-Selling Products</h4>
                    <a href="products.php?sort=popular" class="small text-decoration-none">View All</a>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($bestsellers as $bs): 
                        $bs_price = $bs['price'] * (1 - ($bs['discount'] / 100));
                    ?>
                        <a href="product-details.php?id=<?= $bs['id'] ?>" class="list-group-item list-group-item-action px-0 py-3 d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($bs['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;" alt="">
                            <div class="flex-grow-1">
                                <span class="fw-bold text-dark d-block text-truncate"><?= htmlspecialchars($bs['name']) ?></span>
                                <small class="text-muted"><?= htmlspecialchars($bs['brand']) ?> · <span class="text-warning">★ <?= $bs['rating'] ?></span></small>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-dark d-block"><?= format_price($bs_price) ?></span>
                                <span class="badge bg-success-subtle text-success small">Best Seller</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Low Stock / High Demand Deals -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm h-100 border">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-circle-fill text-danger me-2"></i>Hurry! Low Stock Alerts</h4>
                    <span class="badge bg-danger-subtle text-danger px-2 py-1">Limited Units Left</span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($low_stock_products as $ls): 
                        $ls_price = $ls['price'] * (1 - ($ls['discount'] / 100));
                    ?>
                        <a href="product-details.php?id=<?= $ls['id'] ?>" class="list-group-item list-group-item-action px-0 py-3 d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($ls['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 60px; height: 60px; object-fit: cover;" alt="">
                            <div class="flex-grow-1">
                                <span class="fw-bold text-dark d-block text-truncate"><?= htmlspecialchars($ls['name']) ?></span>
                                <small class="text-danger fw-bold"><i class="bi bi-clock me-1"></i>Only <?= $ls['stock'] ?> units remaining!</small>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-dark d-block"><?= format_price($ls_price) ?></span>
                                <span class="btn btn-warning btn-sm py-0 px-2 fw-bold mt-1">Grab Now</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Viewed Products (if any) -->
    <?php if (!empty($recently_viewed_products)): ?>
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-info"></i>Recently Viewed</h3>
                    <p class="text-muted small mb-0">Items you inspected during this session</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($recently_viewed_products as $rv): 
                    $rv_price = $rv['price'] * (1 - ($rv['discount'] / 100));
                ?>
                    <div class="col-lg-2 col-md-4 col-6">
                        <div class="card h-100 product-card shadow-sm border p-2 text-center">
                            <a href="product-details.php?id=<?= $rv['id'] ?>">
                                <img src="<?= htmlspecialchars($rv['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="img-fluid rounded mb-2" style="height: 110px; object-fit: contain;" alt="">
                            </a>
                            <div class="fw-bold small text-truncate mb-1">
                                <a href="product-details.php?id=<?= $rv['id'] ?>" class="text-dark text-decoration-none"><?= htmlspecialchars($rv['name']) ?></a>
                            </div>
                            <div class="text-primary fw-bold small"><?= format_price($rv_price) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>