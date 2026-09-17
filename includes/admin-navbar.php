<?php
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$admin_user = $_SESSION['admin']['username'] ?? 'Admin';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top border-bottom border-secondary border-opacity-50 shadow-sm">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand d-flex align-items-center fw-bold" href="dashboard.php">
            <div class="bg-warning text-dark rounded d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <span>Eve<b>Need</b> <span class="badge bg-warning text-dark fs-8 ms-1">ADMIN</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar" aria-controls="adminNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active text-warning fw-bold' : '' ?>" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'users.php' ? 'active text-warning fw-bold' : '' ?>" href="users.php">
                        <i class="bi bi-people me-1"></i>Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'products.php' ? 'active text-warning fw-bold' : '' ?>" href="products.php">
                        <i class="bi bi-box-seam me-1"></i>Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'categories.php' ? 'active text-warning fw-bold' : '' ?>" href="categories.php">
                        <i class="bi bi-grid me-1"></i>Categories
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'orders.php' ? 'active text-warning fw-bold' : '' ?>" href="orders.php">
                        <i class="bi bi-receipt me-1"></i>Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'coupons.php' ? 'active text-warning fw-bold' : '' ?>" href="coupons.php">
                        <i class="bi bi-ticket-perforated me-1"></i>Coupons
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'reports.php' ? 'active text-warning fw-bold' : '' ?>" href="reports.php">
                        <i class="bi bi-graph-up me-1"></i>Reports
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                <a href="../index.php" target="_blank" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Storefront
                </a>
                <div class="dropdown">
                    <button class="btn btn-secondary btn-sm dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($admin_user) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted">Administrator</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>
