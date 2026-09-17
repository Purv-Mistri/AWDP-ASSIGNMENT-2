<?php 
require_once 'config/database.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id = ? AND p.status != 'Inactive'");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'The requested product was not found.');
    header('Location: products.php');
    exit;
}

// Track in recently viewed session
track_viewed_product($id);

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!is_user_logged_in()) {
        set_flash('warning', 'Please sign in to submit a review.');
        header("Location: user-login.php?redirect=" . urlencode("product-details.php?id={$id}"));
        exit;
    }

    $uid = current_user_id();
    $rating = (int)($_POST['rating'] ?? 5);
    $review_text = trim($_POST['review_text'] ?? '');

    // Requirement: Only users who purchased the product can submit a review
    $buyer_check = $pdo->prepare("
        SELECT oi.id 
        FROM order_items oi 
        JOIN orders o ON o.id = oi.order_id 
        WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'Delivered'
    ");
    $buyer_check->execute([$uid, $id]);
    $is_verified_buyer = (bool)$buyer_check->fetch();

    if (!$is_verified_buyer) {
        set_flash('danger', 'Only verified buyers who have received a delivered order for this product can submit a review.');
    } elseif (empty($review_text)) {
        set_flash('danger', 'Please write your review comments before submitting.');
    } else {
        $ins_rev = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
        $ins_rev->execute([$id, $uid, $rating, $review_text]);

        // Recompute product rating average
        $avg_stmt = $pdo->prepare("SELECT AVG(rating) as avg_r FROM reviews WHERE product_id = ?");
        $avg_stmt->execute([$id]);
        $new_avg = (float)$avg_stmt->fetchColumn();
        if ($new_avg > 0) {
            $pdo->prepare("UPDATE products SET rating = ? WHERE id = ?")->execute([round($new_avg, 1), $id]);
        }

        set_flash('success', 'Your verified buyer review has been posted successfully!');
    }
    header("Location: product-details.php?id={$id}#reviews-section");
    exit;
}

// Handle Add to Cart / Buy Now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_action'])) {
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $action = $_POST['cart_action'];

    if ($product['stock'] <= 0 || $product['status'] === 'Out of Stock') {
        set_flash('danger', 'Sorry, this product is currently out of stock.');
    } else {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $current_qty = $_SESSION['cart'][$id] ?? 0;
        $new_qty = min($product['stock'], $current_qty + $qty);
        $_SESSION['cart'][$id] = $new_qty;
        
        if ($action === 'buy_now') {
            header('Location: checkout.php');
            exit;
        } else {
            set_flash('success', "Added {$qty} × " . htmlspecialchars($product['name']) . " to your shopping cart!");
            header('Location: cart.php');
            exit;
        }
    }
}

// Check Wishlist status
$in_wishlist = false;
if (is_user_logged_in()) {
    $w_chk = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $w_chk->execute([current_user_id(), $id]);
    $in_wishlist = (bool)$w_chk->fetch();
}

// Check if current user is a verified buyer eligible to review
$can_review = false;
if (is_user_logged_in()) {
    $v_chk = $pdo->prepare("
        SELECT oi.id 
        FROM order_items oi 
        JOIN orders o ON o.id = oi.order_id 
        WHERE o.user_id = ? AND oi.product_id = ? AND o.order_status = 'Delivered'
    ");
    $v_chk->execute([current_user_id(), $id]);
    $can_review = (bool)$v_chk->fetch();
}

// Fetch Reviews
$reviews_stmt = $pdo->prepare("
    SELECT r.*, u.name as reviewer_name, u.profile_image 
    FROM reviews r 
    JOIN users u ON u.id = r.user_id 
    WHERE r.product_id = ? 
    ORDER BY r.id DESC
");
$reviews_stmt->execute([$id]);
$reviews = $reviews_stmt->fetchAll();

// Related Products (same category)
$related_stmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.category_id = ? AND p.id != ? AND p.status != 'Inactive' LIMIT 4");
$related_stmt->execute([$product['category_id'], $id]);
$related_products = $related_stmt->fetchAll();

// Similar Products (same brand)
$similar_stmt = $pdo->prepare("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.brand = ? AND p.id != ? AND p.status != 'Inactive' LIMIT 4");
$similar_stmt->execute([$product['brand'], $id]);
$similar_products = $similar_stmt->fetchAll();

$discounted_price = $product['price'] * (1 - ($product['discount'] / 100));
$savings = $product['price'] - $discounted_price;
$is_out_of_stock = ($product['stock'] <= 0 || $product['status'] === 'Out of Stock');

$page_title = htmlspecialchars($product['name']) . ' - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="products.php" class="text-decoration-none">Products</a></li>
            <li class="breadcrumb-item"><a href="products.php?cat=<?= $product['category_id'] ?>" class="text-decoration-none"><?= htmlspecialchars($product['category_name']) ?></a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <!-- Product Details Card -->
    <div class="card p-4 shadow-sm mb-5 border">
        <div class="row g-4 align-items-center">
            <!-- Product Image Gallery View -->
            <div class="col-lg-5 text-center">
                <div class="p-4 bg-light rounded-4 d-flex align-items-center justify-content-center border" style="min-height: 400px;">
                    <img class="img-fluid rounded-3" style="max-height: 380px; object-fit: contain;" src="<?= htmlspecialchars($product['image']) ?>" onerror="this.src='assets/images/default.jpg'" alt="<?= htmlspecialchars($product['name']) ?>">
                </div>
            </div>

            <!-- Product Details & Actions -->
            <div class="col-lg-7">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-warning text-dark px-3 py-2 fw-bold text-uppercase fs-7"><?= htmlspecialchars($product['category_name']) ?></span>
                    <span class="badge bg-light text-dark border px-3 py-2">Brand: <b><?= htmlspecialchars($product['brand']) ?></b></span>
                </div>

                <h1 class="h2 fw-bold text-dark mb-2"><?= htmlspecialchars($product['name']) ?></h1>

                <!-- Rating and Reviews Link -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="badge bg-success px-2 py-1 fs-6">
                        ★ <?= number_format((float)$product['rating'], 1) ?>
                    </div>
                    <span class="text-muted small"><?= count($reviews) ?> customer ratings & verified reviews</span>
                    <a href="#reviews-section" class="small text-decoration-none ms-2">Read Reviews</a>
                </div>

                <!-- Pricing Box -->
                <div class="p-3 bg-light rounded-3 mb-4 border">
                    <div class="d-flex align-items-baseline gap-3 mb-1">
                        <span class="display-6 fw-bold text-success"><?= format_price($discounted_price) ?></span>
                        <?php if ($product['discount'] > 0): ?>
                            <span class="fs-4 text-decoration-line-through text-muted"><?= format_price($product['price']) ?></span>
                            <span class="badge bg-danger fs-6"><?= (int)$product['discount'] ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($savings > 0): ?>
                        <div class="text-success small fw-semibold">
                            <i class="bi bi-tag-fill me-1"></i>You save <?= format_price($savings) ?> on this item (Inclusive of all taxes)
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stock Status -->
                <div class="mb-4">
                    <?php if ($is_out_of_stock): ?>
                        <div class="alert alert-danger d-inline-flex align-items-center py-2 px-3 mb-0">
                            <i class="bi bi-x-circle-fill me-2 fs-5"></i>
                            <div>
                                <strong class="d-block">Currently Out of Stock</strong>
                                <small>We are restocking this item. Please check back shortly.</small>
                            </div>
                        </div>
                    <?php elseif ($product['stock'] <= 5): ?>
                        <span class="badge bg-warning text-dark px-3 py-2 fs-7 fw-bold">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Hurry, Only <?= $product['stock'] ?> units left in stock!
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success px-3 py-2 fs-7">
                            <i class="bi bi-check-circle-fill me-1"></i>In Stock (<?= $product['stock'] ?> available)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Purchase / Cart Form -->
                <form method="post" action="product-details.php?id=<?= $product['id'] ?>">
                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-sm-4 col-6">
                            <label class="form-label small fw-semibold">Quantity</label>
                            <input type="number" name="qty" class="form-control" value="1" min="1" max="<?= max(1, $product['stock']) ?>" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <button type="submit" name="cart_action" value="add_to_cart" class="btn btn-warning btn-lg px-4 fw-bold shadow-sm" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus me-2"></i>Add to Cart
                        </button>
                        
                        <button type="submit" name="cart_action" value="buy_now" class="btn btn-dark btn-lg px-4 fw-bold shadow-sm" <?= $is_out_of_stock ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge-fill me-1"></i>Buy Now
                        </button>

                        <a href="products.php?wishlist_toggle=<?= $product['id'] ?>" class="btn btn-outline-danger btn-lg px-3" title="Wishlist">
                            <i class="bi <?= $in_wishlist ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                        </a>
                    </div>
                </form>

                <!-- Value Highlights -->
                <div class="row g-2 pt-3 border-top small text-muted">
                    <div class="col-sm-4"><i class="bi bi-truck text-warning me-1"></i>Free Shipping ₹499+</div>
                    <div class="col-sm-4"><i class="bi bi-shield-check text-success me-1"></i>100% Genuine Item</div>
                    <div class="col-sm-4"><i class="bi bi-arrow-repeat text-primary me-1"></i>7-Day Replacement</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Description & Specifications Tabs -->
    <div class="card p-4 shadow-sm mb-5 border">
        <ul class="nav nav-pills mb-3" id="productTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-bold" id="desc-tab" data-bs-toggle="tab" data-bs-target="#desc-pane" type="button" role="tab">Overview & Description</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specs-pane" type="button" role="tab">Technical Specifications</button>
            </li>
        </ul>
        <div class="tab-content pt-2" id="productTabContent">
            <div class="tab-pane fade show active" id="desc-pane" role="tabpanel">
                <h5 class="fw-bold mb-3">Product Information</h5>
                <p class="lead fs-6 text-secondary"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            </div>
            <div class="tab-pane fade" id="specs-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <tbody>
                            <tr><th class="bg-light" style="width: 30%;">Product ID</th><td>SPH-<?= str_pad($product['id'], 5, '0', STR_PAD_LEFT) ?></td></tr>
                            <tr><th class="bg-light">Brand / Manufacturer</th><td><?= htmlspecialchars($product['brand']) ?></td></tr>
                            <tr><th class="bg-light">Department</th><td><?= htmlspecialchars($product['category_name']) ?></td></tr>
                            <tr><th class="bg-light">Model Name</th><td><?= htmlspecialchars($product['name']) ?></td></tr>
                            <tr><th class="bg-light">Stock Status</th><td><?= $is_out_of_stock ? 'Out of Stock' : 'In Stock (' . $product['stock'] . ' units)' ?></td></tr>
                            <tr><th class="bg-light">Rating Score</th><td><?= $product['rating'] ?> / 5.0 (Customer Certified)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Reviews Section -->
    <div class="card p-4 shadow-sm mb-5 border" id="reviews-section">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Customer Ratings & Reviews</h3>
                <p class="text-muted small mb-0">Honest feedback from verified purchasers</p>
            </div>
            <div class="text-end">
                <span class="display-6 fw-bold text-warning"><?= number_format((float)$product['rating'], 1) ?></span>
                <span class="text-muted">/ 5</span>
            </div>
        </div>

        <div class="row g-4">
            <!-- Review Submission Box (Requirement: Only users who purchased the product can submit) -->
            <div class="col-lg-5">
                <div class="p-4 bg-light rounded-3 border">
                    <h5 class="fw-bold mb-2">Write a Customer Review</h5>
                    <?php if (!is_user_logged_in()): ?>
                        <p class="small text-muted mb-3">Please sign in to write a review if you have purchased this product.</p>
                        <a href="user-login.php?redirect=<?= urlencode("product-details.php?id={$id}") ?>" class="btn btn-outline-warning text-dark fw-bold btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Review
                        </a>
                    <?php elseif (!$can_review): ?>
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="bi bi-info-circle me-1"></i> <strong>Verified Buyers Only:</strong> You can submit a review once your order containing this product has been marked as <strong>Delivered</strong>.
                        </div>
                    <?php else: ?>
                        <form method="post" action="product-details.php?id=<?= $product['id'] ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Rating (1 to 5 Stars)</label>
                                <select name="rating" class="form-select form-select-sm" required>
                                    <option value="5">★★★★★ (5 Stars - Excellent)</option>
                                    <option value="4">★★★★☆ (4 Stars - Very Good)</option>
                                    <option value="3">★★★☆☆ (3 Stars - Average)</option>
                                    <option value="2">★★☆☆☆ (2 Stars - Below Average)</option>
                                    <option value="1">★☆☆☆☆ (1 Star - Poor)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Your Review & Comments</label>
                                <textarea name="review_text" class="form-control form-control-sm" rows="3" placeholder="Share your personal experience with the product..." required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-warning fw-bold btn-sm w-100">
                                <i class="bi bi-send-fill me-1"></i>Submit Verified Review
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="col-lg-7">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-chat-left-quote fs-1 opacity-50"></i>
                        <p class="mt-2 mb-0">No customer reviews yet. Be the first verified buyer to leave feedback!</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="p-3 border rounded-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-warning text-dark rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                            <?= strtoupper(substr($rev['reviewer_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                            <span class="badge bg-success-subtle text-success small py-0"><i class="bi bi-patch-check-fill me-1"></i>Verified Buyer</span>
                                        </div>
                                    </div>
                                    <span class="text-muted small"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <div class="text-warning mb-2">
                                    <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                </div>
                                <p class="small text-secondary mb-0"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Related Products (Same Category) -->
    <?php if (!empty($related_products)): ?>
        <div class="mb-5">
            <h3 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>Related Products in <?= htmlspecialchars($product['category_name']) ?></h3>
            <div class="row g-4">
                <?php foreach ($related_products as $rel): 
                    $rel_price = $rel['price'] * (1 - ($rel['discount'] / 100));
                ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 product-card shadow-sm border">
                            <a href="product-details.php?id=<?= $rel['id'] ?>" class="p-3 bg-light text-center">
                                <img src="<?= htmlspecialchars($rel['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="">
                            </a>
                            <div class="card-body d-flex flex-column p-3">
                                <span class="small text-muted mb-1"><?= htmlspecialchars($rel['brand']) ?></span>
                                <h6 class="fw-bold mb-2">
                                    <a href="product-details.php?id=<?= $rel['id'] ?>" class="text-dark text-decoration-none text-truncate d-block"><?= htmlspecialchars($rel['name']) ?></a>
                                </h6>
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark"><?= format_price($rel_price) ?></span>
                                    <a href="product-details.php?id=<?= $rel['id'] ?>" class="btn btn-warning btn-sm fw-bold">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Similar Products (Same Brand) -->
    <?php if (!empty($similar_products)): ?>
        <div class="mb-5">
            <h3 class="fw-bold mb-4"><i class="bi bi-award me-2 text-warning"></i>More from <?= htmlspecialchars($product['brand']) ?></h3>
            <div class="row g-4">
                <?php foreach ($similar_products as $sim): 
                    $sim_price = $sim['price'] * (1 - ($sim['discount'] / 100));
                ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="card h-100 product-card shadow-sm border">
                            <a href="product-details.php?id=<?= $sim['id'] ?>" class="p-3 bg-light text-center">
                                <img src="<?= htmlspecialchars($sim['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="">
                            </a>
                            <div class="card-body d-flex flex-column p-3">
                                <span class="small text-muted mb-1"><?= htmlspecialchars($sim['cat_name']) ?></span>
                                <h6 class="fw-bold mb-2">
                                    <a href="product-details.php?id=<?= $sim['id'] ?>" class="text-dark text-decoration-none text-truncate d-block"><?= htmlspecialchars($sim['name']) ?></a>
                                </h6>
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark"><?= format_price($sim_price) ?></span>
                                    <a href="product-details.php?id=<?= $sim['id'] ?>" class="btn btn-outline-dark btn-sm">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>