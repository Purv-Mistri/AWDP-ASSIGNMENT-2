<?php 
require_once 'config/database.php';

// Handle Wishlist Toggle
if (isset($_GET['wishlist_toggle'])) {
    if (!is_user_logged_in()) {
        set_flash('warning', 'Please login to add items to your wishlist.');
        header('Location: user-login.php');
        exit;
    }
    $pid = (int)$_GET['wishlist_toggle'];
    $uid = current_user_id();

    $check = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check->execute([$uid, $pid]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$uid, $pid]);
        set_flash('info', 'Product removed from your wishlist.');
    } else {
        $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)")->execute([$uid, $pid]);
        set_flash('success', 'Product added to your wishlist!');
    }
    
    // Redirect back to clean query
    $back = $_SERVER['HTTP_REFERER'] ?? 'products.php';
    header("Location: {$back}");
    exit;
}

// Handle Direct Add to Cart
if (isset($_POST['direct_add_cart'])) {
    $pid = (int)($_POST['product_id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$pid]);
    $prod = $st->fetch();

    if ($prod) {
        if ($prod['stock'] <= 0 || $prod['status'] === 'Out of Stock') {
            set_flash('danger', 'Sorry, this product is currently out of stock.');
        } else {
            if (!isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid] = 0;
            }
            if ($_SESSION['cart'][$pid] < $prod['stock']) {
                $_SESSION['cart'][$pid]++;
                set_flash('success', "Added " . htmlspecialchars($prod['name']) . " to cart!");
            } else {
                set_flash('warning', 'Maximum available stock already in your cart.');
            }
        }
    }
    $back = $_SERVER['HTTP_REFERER'] ?? 'products.php';
    header("Location: {$back}");
    exit;
}

// Filter and Search Parameters
$q = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$brand = trim($_GET['brand'] ?? '');
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$min_rating = (float)($_GET['rating'] ?? 0);
$availability = trim($_GET['availability'] ?? '');
$discount_min = (float)($_GET['discount'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';

// Fetch distinct categories for sidebar
$all_categories = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id AND p.status != 'Inactive' 
    WHERE c.status = 'Active'
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();

// Fetch distinct brands for sidebar
$all_brands = $pdo->query("
    SELECT DISTINCT brand, COUNT(id) as brand_count 
    FROM products 
    WHERE status != 'Inactive' AND brand != '' 
    GROUP BY brand 
    ORDER BY brand ASC
")->fetchAll();

// Build Product Query
$sql = "SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status != 'Inactive'";
$params = [];

if (!empty($q)) {
    $sql .= " AND (p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ? OR p.description LIKE ?)";
    $term = "%{$q}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($cat > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $cat;
}

if (!empty($brand)) {
    $sql .= " AND p.brand = ?";
    $params[] = $brand;
}

if ($min_price > 0) {
    $sql .= " AND (p.price * (1 - p.discount/100)) >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $sql .= " AND (p.price * (1 - p.discount/100)) <= ?";
    $params[] = $max_price;
}

if ($min_rating > 0) {
    $sql .= " AND p.rating >= ?";
    $params[] = $min_rating;
}

if ($availability === 'in_stock') {
    $sql .= " AND p.stock > 0 AND p.status = 'Active'";
}

if ($discount_min > 0) {
    $sql .= " AND p.discount >= ?";
    $params[] = $discount_min;
}

// Sorting logic
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY (p.price * (1 - p.discount/100)) ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY (p.price * (1 - p.discount/100)) DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.rating DESC, p.stock DESC";
        break;
    case 'highest_rated':
        $sql .= " ORDER BY p.rating DESC";
        break;
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// User Wishlist IDs for fast checking
$user_wishlist_ids = [];
if (is_user_logged_in()) {
    $w_stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $w_stmt->execute([current_user_id()]);
    $user_wishlist_ids = $w_stmt->fetchAll(PDO::FETCH_COLUMN);
}

$page_title = 'Products Catalog - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Products</li>
            <?php if ($cat > 0): 
                $curr_cat = array_filter($all_categories, fn($c) => $c['id'] == $cat);
                $curr_cat_name = !empty($curr_cat) ? reset($curr_cat)['name'] : '';
            ?>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($curr_cat_name) ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="card p-3 shadow-sm mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-funnel me-1 text-warning"></i>Filters</h5>
                    <?php if ($q || $cat || $brand || $min_price || $max_price || $min_rating || $availability || $discount_min || $sort !== 'newest'): ?>
                        <a href="products.php" class="btn btn-outline-danger btn-sm py-0 px-2">Clear All</a>
                    <?php endif; ?>
                </div>

                <form method="get" action="products.php">
                    <?php if (!empty($q)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                    <?php endif; ?>

                    <!-- Categories Filter -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Category</h6>
                    <div class="list-group list-group-flush mb-3">
                        <a href="products.php<?= $q ? '?q=' . urlencode($q) : '' ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 <?= $cat === 0 ? 'fw-bold active bg-dark text-warning border-0' : '' ?>">
                            <span>All Categories</span>
                        </a>
                        <?php foreach ($all_categories as $c): ?>
                            <a href="products.php?cat=<?= $c['id'] ?><?= $q ? '&q=' . urlencode($q) : '' ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-2 py-2 <?= $cat === (int)$c['id'] ? 'fw-bold active bg-dark text-warning border-0' : '' ?>">
                                <span><?= htmlspecialchars($c['name']) ?></span>
                                <span class="badge <?= $cat === (int)$c['id'] ? 'bg-warning text-dark' : 'bg-light text-dark border' ?> rounded-pill"><?= $c['product_count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <hr class="my-3">

                    <!-- Brand Filter -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Brand</h6>
                    <select name="brand" class="form-select form-select-sm mb-3" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        <?php foreach ($all_brands as $b): ?>
                            <option value="<?= htmlspecialchars($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['brand']) ?> (<?= $b['brand_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <hr class="my-3">

                    <!-- Price Range -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Price Range (₹)</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Min" value="<?= $min_price > 0 ? $min_price : '' ?>">
                        </div>
                        <div class="col-6">
                            <input type="number" name="max_price" class="form-control form-control-sm" placeholder="Max" value="<?= $max_price > 0 ? $max_price : '' ?>">
                        </div>
                    </div>

                    <!-- Minimum Rating -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Customer Rating</h6>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="rating" id="r4" value="4.5" <?= $min_rating >= 4.5 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="r4">
                                <span class="text-warning">★★★★½</span> 4.5 & above
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="rating" id="r3" value="4.0" <?= $min_rating >= 4.0 && $min_rating < 4.5 ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="r3">
                                <span class="text-warning">★★★★☆</span> 4.0 & above
                            </label>
                        </div>
                    </div>

                    <!-- Availability -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Availability</h6>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="availability" value="in_stock" id="availCheck" <?= $availability === 'in_stock' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="availCheck">
                            In Stock Only
                        </label>
                    </div>

                    <!-- Discount -->
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Discount</h6>
                    <select name="discount" class="form-select form-select-sm mb-3">
                        <option value="">All Discounts</option>
                        <option value="10" <?= $discount_min == 10 ? 'selected' : '' ?>>10% or more</option>
                        <option value="20" <?= $discount_min == 20 ? 'selected' : '' ?>>20% or more</option>
                        <option value="30" <?= $discount_min == 30 ? 'selected' : '' ?>>30% or more</option>
                    </select>

                    <?php if ($cat > 0): ?>
                        <input type="hidden" name="cat" value="<?= $cat ?>">
                    <?php endif; ?>

                    <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold">
                        <i class="bi bi-funnel-fill me-1"></i>Apply Filters
                    </button>
                </form>
            </div>
        </div>

        <!-- Products Main Area -->
        <div class="col-lg-9">
            <!-- Results Counter & Sort Bar -->
            <div class="card p-3 shadow-sm mb-4">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <span class="fw-bold text-dark fs-6">
                            <i class="bi bi-search me-1 text-warning"></i>
                            <?= count($products) ?> Products Found
                        </span>
                        <?php if ($q): ?>
                            <span class="text-muted small">for "<b><?= htmlspecialchars($q) ?></b>"</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <form method="get" class="d-flex justify-content-md-end align-items-center gap-2">
                            <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                            <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
                            <?php if ($brand): ?><input type="hidden" name="brand" value="<?= htmlspecialchars($brand) ?>"><?php endif; ?>
                            <?php if ($min_price): ?><input type="hidden" name="min_price" value="<?= $min_price ?>"><?php endif; ?>
                            <?php if ($max_price): ?><input type="hidden" name="max_price" value="<?= $max_price ?>"><?php endif; ?>
                            <?php if ($min_rating): ?><input type="hidden" name="rating" value="<?= $min_rating ?>"><?php endif; ?>
                            <?php if ($availability): ?><input type="hidden" name="availability" value="<?= htmlspecialchars($availability) ?>"><?php endif; ?>

                            <label class="form-label small mb-0 fw-semibold text-nowrap">Sort By:</label>
                            <select name="sort" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option>
                                <option value="highest_rated" <?= $sort === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <?php if (empty($products)): ?>
                <div class="card text-center p-5 shadow-sm">
                    <i class="bi bi-search fs-1 text-muted"></i>
                    <h4 class="fw-bold mt-3">No Products Matched Your Criteria</h4>
                    <p class="text-muted">Try removing some filters or searching for different keywords.</p>
                    <div>
                        <a href="products.php" class="btn btn-warning fw-bold px-4">View All Products</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $p): 
                        $final_price = $p['price'] * (1 - ($p['discount'] / 100));
                        $is_in_wishlist = in_array((int)$p['id'], $user_wishlist_ids);
                        $is_out_of_stock = ($p['stock'] <= 0 || $p['status'] === 'Out of Stock');
                        $is_low_stock = ($p['stock'] > 0 && $p['stock'] <= 5);
                    ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card h-100 product-card shadow-sm border position-relative d-flex flex-column">
                                <!-- Wishlist Button -->
                                <a href="products.php?wishlist_toggle=<?= $p['id'] ?>" class="btn btn-sm btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 z-2" title="Add to Wishlist" style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;">
                                    <i class="bi <?= $is_in_wishlist ? 'bi-heart-fill text-danger' : 'bi-heart text-secondary' ?>"></i>
                                </a>

                                <!-- Discount Badge -->
                                <?php if ($p['discount'] > 0): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-2 px-2 py-1 fw-bold z-2">
                                        <?= (int)$p['discount'] ?>% OFF
                                    </span>
                                <?php endif; ?>

                                <!-- Product Image -->
                                <a href="product-details.php?id=<?= $p['id'] ?>" class="p-3 bg-light text-center">
                                    <img src="<?= htmlspecialchars($p['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="<?= htmlspecialchars($p['name']) ?>">
                                </a>

                                <div class="card-body d-flex flex-column p-3">
                                    <!-- Category & Brand -->
                                    <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['brand']) ?></span>
                                        <span><?= htmlspecialchars($p['cat_name']) ?></span>
                                    </div>

                                    <!-- Product Title -->
                                    <h6 class="card-title fw-bold mb-2">
                                        <a href="product-details.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark d-block text-truncate" title="<?= htmlspecialchars($p['name']) ?>">
                                            <?= htmlspecialchars($p['name']) ?>
                                        </a>
                                    </h6>

                                    <!-- Rating -->
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-warning me-1">★</span>
                                        <span class="small fw-semibold"><?= number_format((float)$p['rating'], 1) ?></span>
                                        
                                        <!-- Stock status -->
                                        <?php if ($is_out_of_stock): ?>
                                            <span class="badge bg-danger ms-auto">Out of Stock</span>
                                        <?php elseif ($is_low_stock): ?>
                                            <span class="badge bg-warning text-dark ms-auto">Only <?= $p['stock'] ?> left</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success ms-auto">In Stock</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Pricing -->
                                    <div class="mb-3">
                                        <span class="fw-bold fs-5 text-dark"><?= format_price($final_price) ?></span>
                                        <?php if ($p['discount'] > 0): ?>
                                            <span class="text-muted text-decoration-line-through small ms-1"><?= format_price($p['price']) ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-auto pt-2 border-top d-grid gap-2">
                                        <?php if ($is_out_of_stock): ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="bi bi-x-circle me-1"></i>Out of Stock
                                            </button>
                                        <?php else: ?>
                                            <form method="post" action="products.php" class="d-grid">
                                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                <button type="submit" name="direct_add_cart" class="btn btn-warning btn-sm fw-bold">
                                                    <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="product-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-dark btn-sm">
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>