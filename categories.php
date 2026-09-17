<?php 
require_once 'config/database.php';

$page_title = 'Explore All Categories - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';

// Fetch all categories with product counts and price range
$categories = $pdo->query("
    SELECT c.*, 
           COUNT(p.id) AS product_count,
           MIN(p.price * (1 - p.discount/100)) AS min_price,
           MAX(p.price * (1 - p.discount/100)) AS max_price
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id AND p.status != 'Inactive'
    WHERE c.status = 'Active'
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Categories</li>
        </ol>
    </nav>

    <div class="text-center mb-5">
        <span class="badge bg-warning-subtle text-dark px-3 py-2 rounded-pill fw-bold mb-2">10 MAJOR DEPARTMENTS</span>
        <h2 class="display-6 fw-bold mb-2">Shop by Category</h2>
        <p class="text-muted mx-auto" style="max-width: 600px;">
            Find precisely what you are searching for across our authenticated consumer technology, fashion, grocery, wellness, home, sports, and literature collections.
        </p>
    </div>

    <div class="row g-4">
        <?php foreach ($categories as $c): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card h-100 shadow-sm border category-card hover-shadow overflow-hidden">
                    <div class="position-relative">
                        <img src="<?= htmlspecialchars($c['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= htmlspecialchars($c['name']) ?>">
                        <span class="badge bg-dark bg-opacity-75 position-absolute top-0 end-0 m-3 px-3 py-2">
                            <i class="bi <?= htmlspecialchars($c['icon'] ?? 'bi-tag') ?> text-warning me-1"></i> <?= $c['product_count'] ?> Products
                        </span>
                    </div>

                    <div class="card-body d-flex flex-column p-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($c['name']) ?></h4>
                        </div>
                        <p class="text-muted small mb-3 flex-grow-1">
                            <?= htmlspecialchars($c['description'] ?? 'Explore our top quality products in this category with great discounts.') ?>
                        </p>

                        <?php if ($c['min_price'] !== null): ?>
                            <div class="small text-secondary mb-3">
                                Starts from <b class="text-success"><?= format_price($c['min_price']) ?></b>
                            </div>
                        <?php endif; ?>

                        <a href="products.php?cat=<?= $c['id'] ?>" class="btn btn-outline-warning text-dark fw-bold w-100">
                            Explore <?= htmlspecialchars($c['name']) ?> <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
