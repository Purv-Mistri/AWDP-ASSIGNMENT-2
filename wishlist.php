<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();

// Handle Remove from Wishlist
if (isset($_GET['remove'])) {
    $pid = (int)$_GET['remove'];
    $del = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
    $del->execute([$user_id, $pid]);
    set_flash('info', 'Product removed from your wishlist.');
    header('Location: wishlist.php');
    exit;
}

// Handle Move to Cart
if (isset($_GET['move_to_cart'])) {
    $pid = (int)$_GET['move_to_cart'];
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$pid]);
    $prod = $st->fetch();

    if ($prod && $prod['stock'] > 0 && $prod['status'] === 'Active') {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        $curr = $_SESSION['cart'][$pid] ?? 0;
        $_SESSION['cart'][$pid] = min($prod['stock'], $curr + 1);

        // Remove from wishlist
        $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?")->execute([$user_id, $pid]);
        set_flash('success', "Moved '" . htmlspecialchars($prod['name']) . "' to your shopping cart!");
    } else {
        set_flash('danger', 'This item is currently out of stock and cannot be moved to cart.');
    }
    header('Location: wishlist.php');
    exit;
}

// Fetch Wishlist Items
$stmt = $pdo->prepare("
    SELECT w.id as wishlist_id, w.created_at as added_on, p.*, c.name as category_name 
    FROM wishlist w 
    JOIN products p ON p.id = w.product_id 
    JOIN categories c ON c.id = p.category_id 
    WHERE w.user_id = ? 
    ORDER BY w.id DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

$page_title = 'My Wishlist - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Wishlist</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-heart-fill text-danger me-2"></i>My Wishlist</h2>
            <p class="text-muted mb-0">Saved products to purchase later or monitor for price drops</p>
        </div>
        <span class="badge bg-light text-dark border fs-6 px-3 py-2">
            <b><?= count($items) ?></b> Items Saved
        </span>
    </div>

    <?php if (empty($items)): ?>
        <div class="card text-center p-5 shadow-sm border">
            <div class="py-4">
                <i class="bi bi-heart fs-1 text-muted" style="font-size: 4rem !important;"></i>
                <h4 class="fw-bold mt-3">Your Wishlist is Empty</h4>
                <p class="text-muted">Explore our catalog and click the heart icon on any product to save it here.</p>
                <a href="products.php" class="btn btn-warning fw-bold px-4 mt-2">
                    <i class="bi bi-bag-plus me-1"></i>Discover Products
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($items as $item): 
                $final_price = $item['price'] * (1 - ($item['discount'] / 100));
                $is_unavailable = ($item['stock'] <= 0 || $item['status'] !== 'Active');
            ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 product-card shadow-sm border position-relative">
                        <!-- Remove button -->
                        <a href="wishlist.php?remove=<?= $item['id'] ?>" class="btn btn-sm btn-light rounded-circle shadow-sm position-absolute top-0 end-0 m-2 z-2" title="Remove from Wishlist" onclick="return confirm('Remove this product from your wishlist?');">
                            <i class="bi bi-x-lg text-danger"></i>
                        </a>

                        <!-- Product Image -->
                        <a href="product-details.php?id=<?= $item['id'] ?>" class="p-3 bg-light text-center">
                            <img src="<?= htmlspecialchars($item['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="product-img img-fluid" alt="<?= htmlspecialchars($item['name']) ?>">
                        </a>

                        <div class="card-body d-flex flex-column p-3">
                            <div class="small text-muted mb-1"><?= htmlspecialchars($item['brand']) ?> · <?= htmlspecialchars($item['category_name']) ?></div>
                            <h6 class="card-title fw-bold mb-2">
                                <a href="product-details.php?id=<?= $item['id'] ?>" class="text-dark text-decoration-none text-truncate d-block">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            </h6>

                            <div class="mb-3">
                                <span class="fw-bold fs-5 text-dark"><?= format_price($final_price) ?></span>
                                <?php if ($item['discount'] > 0): ?>
                                    <span class="text-muted text-decoration-line-through small ms-1"><?= format_price($item['price']) ?></span>
                                    <span class="badge bg-danger small ms-1"><?= (int)$item['discount'] ?>% OFF</span>
                                <?php endif; ?>
                            </div>

                            <!-- Availability & Action -->
                            <div class="mt-auto pt-2 border-top">
                                <?php if ($is_unavailable): ?>
                                    <div class="alert alert-danger py-1 px-2 small text-center mb-2">
                                        <i class="bi bi-exclamation-octagon me-1"></i>Currently Unavailable
                                    </div>
                                    <button class="btn btn-secondary btn-sm w-100" disabled>Out of Stock</button>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <a href="wishlist.php?move_to_cart=<?= $item['id'] ?>" class="btn btn-warning btn-sm fw-bold">
                                            <i class="bi bi-cart-check me-1"></i>Move to Cart
                                        </a>
                                        <a href="product-details.php?id=<?= $item['id'] ?>" class="btn btn-outline-dark btn-sm">
                                            View Product
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
