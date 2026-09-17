<?php
$base_url = get_base_url();
$cart_count = get_cart_count();
$is_user = is_user_logged_in();
$is_adm = is_admin_logged_in();
$wishlist_count = isset($pdo) ? get_wishlist_count($pdo) : 0;
$user_info = current_user();

// Fetch active categories for navbar dropdown
$nav_categories = [];
if (isset($pdo)) {
    try {
        $nav_categories = $pdo->query("SELECT id, name, icon FROM categories WHERE status='Active' ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
}
?>
<header class="sticky-top">
    <!-- Top Announcement Bar -->
    <div class="bg-dark text-white py-1 px-3 d-none d-md-block border-bottom border-secondary border-opacity-25" style="font-size: 0.82rem;">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <span class="text-warning fw-semibold"><i class="bi bi-stars me-1"></i>EveNeed:</span>
                <span class="text-light opacity-90">"Everything You Need, All in One Place." · Free Delivery on orders above ₹499</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= $base_url ?>track-order.php" class="text-light text-decoration-none hover-warning"><i class="bi bi-geo-alt me-1"></i>Track Order</a>
                <span class="opacity-25">|</span>
                <?php if ($is_adm): ?>
                    <a href="<?= $base_url ?>admin/dashboard.php" class="text-warning text-decoration-none fw-semibold"><i class="bi bi-shield-lock me-1"></i>Admin Dashboard</a>
                <?php else: ?>
                    <a href="<?= $base_url ?>admin-login.php" class="text-secondary text-decoration-none hover-warning"><i class="bi bi-lock me-1"></i>Admin</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-2 shadow-sm">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand d-flex align-items-center py-0" href="<?= $base_url ?>index.php">
                <div class="brand-icon-box me-2 d-flex align-items-center justify-content-center bg-warning text-dark rounded-circle" style="width: 38px; height: 38px;">
                    <i class="bi bi-bag-check-fill fs-5"></i>
                </div>
                <div>
                    <span class="fw-bold tracking-tight text-white fs-4">Eve<span class="text-warning">Need</span></span>
                </div>
            </a>

            <!-- Mobile Toggler -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#shopNavbar" aria-controls="shopNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse mt-2 mt-lg-0" id="shopNavbar">
                <!-- Search Bar -->
                <form class="d-flex mx-lg-auto my-2 my-lg-0" style="max-width: 420px; width: 100%;" action="<?= $base_url ?>products.php" method="get">
                    <div class="input-group">
                        <input class="form-control bg-white border-0 py-2 ps-3 shadow-none" type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search electronics, brands, fashion..." aria-label="Search">
                        <button class="btn btn-warning px-3 fw-bold" type="submit" title="Search">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>

                <!-- Navigation Links -->
                <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                    <li class="nav-item">
                        <a class="nav-link px-2" href="<?= $base_url ?>index.php">
                            <i class="bi bi-house-door me-1"></i>Home
                        </a>
                    </li>
                    
                    <!-- Categories Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle px-2" href="<?= $base_url ?>categories.php" id="categoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-grid me-1"></i>Categories
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark shadow-lg border-0" aria-labelledby="categoriesDropdown">
                            <li><a class="dropdown-item fw-bold text-warning" href="<?= $base_url ?>categories.php"><i class="bi bi-grid-3x3-gap me-2"></i>All Categories</a></li>
                            <li><hr class="dropdown-divider border-secondary opacity-50"></li>
                            <?php foreach ($nav_categories as $c): ?>
                                <li>
                                    <a class="dropdown-item py-2" href="<?= $base_url ?>products.php?cat=<?= (int)$c['id'] ?>">
                                        <i class="bi <?= htmlspecialchars($c['icon'] ?? 'bi-tag') ?> me-2 text-warning opacity-75"></i><?= htmlspecialchars($c['name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link px-2" href="<?= $base_url ?>products.php">
                            <i class="bi bi-shop me-1"></i>Products
                        </a>
                    </li>

                    <!-- Wishlist -->
                    <li class="nav-item">
                        <a class="nav-link position-relative px-2 me-1" href="<?= $base_url ?>wishlist.php" title="My Wishlist">
                            <i class="bi bi-heart fs-5 align-middle"></i>
                            <span class="d-lg-none ms-1">Wishlist</span>
                            <?php if ($wishlist_count > 0): ?>
                                <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle">
                                    <?= $wishlist_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Cart -->
                    <li class="nav-item">
                        <a class="nav-link position-relative px-2 me-lg-2" href="<?= $base_url ?>cart.php" title="Shopping Cart">
                            <i class="bi bi-cart3 fs-5 align-middle"></i>
                            <span class="d-lg-none ms-1">Cart</span>
                            <?php if ($cart_count > 0): ?>
                                <span class="badge rounded-pill bg-warning text-dark position-absolute top-0 start-100 translate-middle fw-bold">
                                    <?= $cart_count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- User Account / Login State -->
                    <?php if ($is_user): ?>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="btn btn-outline-light btn-sm dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 mt-2 mt-lg-0 rounded-pill" href="#" id="userAccountMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-6 text-warning"></i>
                                <span class="fw-semibold text-truncate" style="max-width: 120px;"><?= htmlspecialchars($user_info['name'] ?? 'Account') ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border-0" aria-labelledby="userAccountMenu">
                                <li class="px-3 py-2 border-bottom border-secondary border-opacity-50">
                                    <div class="fw-bold text-white"><?= htmlspecialchars($user_info['name'] ?? '') ?></div>
                                    <small class="text-muted text-truncate d-block">@<?= htmlspecialchars($user_info['username'] ?? '') ?></small>
                                </li>
                                <li><a class="dropdown-item py-2" href="<?= $base_url ?>profile.php"><i class="bi bi-person-badge me-2 text-primary"></i>My Profile</a></li>
                                <li><a class="dropdown-item py-2" href="<?= $base_url ?>orders.php"><i class="bi bi-box-seam me-2 text-success"></i>My Orders</a></li>
                                <li><a class="dropdown-item py-2" href="<?= $base_url ?>wishlist.php"><i class="bi bi-heart me-2 text-danger"></i>My Wishlist</a></li>
                                <li><a class="dropdown-item py-2" href="<?= $base_url ?>addresses.php"><i class="bi bi-geo-alt me-2 text-info"></i>Saved Addresses</a></li>
                                <li><hr class="dropdown-divider border-secondary opacity-50"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-semibold" href="<?= $base_url ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                            <a class="btn btn-outline-light btn-sm px-3 rounded-pill" href="<?= $base_url ?>user-login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item ms-lg-1 mt-1 mt-lg-0">
                            <a class="btn btn-warning btn-sm px-3 text-dark fw-bold rounded-pill" href="<?= $base_url ?>register.php">
                                Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>