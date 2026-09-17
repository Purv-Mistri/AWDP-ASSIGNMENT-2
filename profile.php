<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

$error_msg = '';
$pwd_error_msg = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($mobile)) {
        $error_msg = 'Name and mobile number are required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error_msg = 'Please enter a valid 10-digit mobile number.';
    } else {
        // Check mobile uniqueness if changed
        $m_check = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
        $m_check->execute([$mobile, $user_id]);
        if ($m_check->fetch()) {
            $error_msg = 'This mobile number is already in use by another account.';
        } else {
            $up = $pdo->prepare("UPDATE users SET name = ?, mobile = ?, address = ? WHERE id = ?");
            $up->execute([$name, $mobile, $address, $user_id]);
            
            // Update session
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['mobile'] = $mobile;
            $_SESSION['user']['address'] = $address;
            
            set_flash('success', 'Profile details updated successfully!');
            header('Location: profile.php');
            exit;
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $pwd_error_msg = 'All password fields are required.';
    } elseif (!password_verify($current_pass, $user['password'])) {
        $pwd_error_msg = 'Your current password is incorrect.';
    } elseif (strlen($new_pass) < 6) {
        $pwd_error_msg = 'New password must be at least 6 characters long.';
    } elseif ($new_pass !== $confirm_pass) {
        $pwd_error_msg = 'New password and confirmation do not match.';
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $up->execute([$new_hash, $user_id]);
        
        set_flash('success', 'Your password has been changed successfully!');
        header('Location: profile.php');
        exit;
    }
}

// User statistics
$o_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$o_stmt->execute([$user_id]);
$total_orders = (int)$o_stmt->fetchColumn();

$p_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status IN ('Pending', 'Confirmed', 'Packed', 'Shipped', 'Out for Delivery')");
$p_stmt->execute([$user_id]);
$pending_orders = (int)$p_stmt->fetchColumn();

$c_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'Delivered'");
$c_stmt->execute([$user_id]);
$completed_orders = (int)$c_stmt->fetchColumn();

$wishlist_count = get_wishlist_count($pdo);
$cart_count = get_cart_count();

// Recent 3 orders
$ro_stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 3");
$ro_stmt->execute([$user_id]);
$recent_orders = $ro_stmt->fetchAll();

$page_title = 'My Profile & Dashboard - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Profile</li>
        </ol>
    </nav>

    <!-- Profile Header Banner -->
    <div class="card bg-dark text-white p-4 shadow-sm mb-4 border-0">
        <div class="row align-items-center">
            <div class="col-md-8 d-flex align-items-center gap-3">
                <img src="<?= htmlspecialchars($user['profile_image'] ?? 'assets/images/user-avatar.png') ?>" onerror="this.src='assets/images/user-avatar.png'" alt="User Profile" class="rounded-circle border border-3 border-warning" style="width: 80px; height: 80px; object-fit: cover;">
                <div>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                    <div class="d-flex flex-wrap gap-2 text-light opacity-75 small">
                        <span><i class="bi bi-person me-1"></i>@<?= htmlspecialchars($user['username']) ?></span>
                        <span>•</span>
                        <span><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?></span>
                        <span>•</span>
                        <span><i class="bi bi-phone me-1"></i><?= htmlspecialchars($user['mobile']) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-success px-3 py-2 text-uppercase fs-7"><i class="bi bi-check-circle-fill me-1"></i>Active Account</span>
                <div class="small text-muted mt-1">Joined <?= date('M Y', strtotime($user['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card p-3 shadow-sm text-center h-100 border-start border-primary border-4">
                <h6 class="text-muted small text-uppercase mb-1">Total Orders</h6>
                <h3 class="fw-bold text-primary mb-0"><?= $total_orders ?></h3>
                <a href="orders.php" class="small text-decoration-none mt-2">View Orders <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-3 shadow-sm text-center h-100 border-start border-warning border-4">
                <h6 class="text-muted small text-uppercase mb-1">Pending Orders</h6>
                <h3 class="fw-bold text-warning mb-0"><?= $pending_orders ?></h3>
                <a href="orders.php" class="small text-decoration-none mt-2">Track Status <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-3 shadow-sm text-center h-100 border-start border-danger border-4">
                <h6 class="text-muted small text-uppercase mb-1">Wishlist Items</h6>
                <h3 class="fw-bold text-danger mb-0"><?= $wishlist_count ?></h3>
                <a href="wishlist.php" class="small text-decoration-none mt-2">View Saved <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card p-3 shadow-sm text-center h-100 border-start border-success border-4">
                <h6 class="text-muted small text-uppercase mb-1">Cart Items</h6>
                <h3 class="fw-bold text-success mb-0"><?= $cart_count ?></h3>
                <a href="cart.php" class="small text-decoration-none mt-2">Go to Cart <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Edit Profile Form -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-person-gear me-2 text-primary"></i>Personal Information</h5>
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>

                <form method="post" action="profile.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Username (Read-only)</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email (Read-only)</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number</label>
                        <input type="tel" class="form-control" name="mobile" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($user['mobile']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Default Address</label>
                        <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="addresses.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-geo-alt me-1"></i>Manage Saved Addresses
                        </a>
                        <button type="submit" name="update_profile" class="btn btn-primary fw-semibold px-4">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password & Quick Actions -->
        <div class="col-lg-6">
            <div class="card p-4 shadow-sm mb-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-warning"></i>Security & Password</h5>
                
                <?php if (!empty($pwd_error_msg)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($pwd_error_msg) ?></div>
                <?php endif; ?>

                <form method="post" action="profile.php">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">New Password</label>
                            <input type="password" class="form-control" name="new_password" minlength="6" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-warning fw-bold px-4">
                        <i class="bi bi-key-fill me-1"></i>Update Password
                    </button>
                </form>
            </div>

            <!-- Recent Orders Snippet -->
            <div class="card p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-success"></i>Recent Orders</h5>
                    <a href="orders.php" class="btn btn-outline-primary btn-sm">View All</a>
                </div>

                <?php if (empty($recent_orders)): ?>
                    <p class="text-muted small mb-0">You have not placed any orders yet. <a href="products.php">Start shopping!</a></p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_orders as $ro): ?>
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold d-block">Order #<?= htmlspecialchars($ro['order_number'] ?? $ro['id']) ?></span>
                                    <small class="text-muted"><?= date('M d, Y', strtotime($ro['created_at'])) ?> · <span class="badge bg-secondary"><?= htmlspecialchars($ro['order_status'] ?? $ro['status'] ?? 'Pending') ?></span></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary"><?= format_price($ro['total_amount'] ?? $ro['total']) ?></div>
                                    <a href="order-details.php?id=<?= $ro['id'] ?>" class="small text-decoration-none">Details <i class="bi bi-chevron-right"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
