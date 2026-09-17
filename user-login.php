<?php 
require_once 'config/database.php';

if (is_user_logged_in()) {
    header('Location: index.php');
    exit;
}

$error_msg = '';
$login_val = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_val = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? '';

    if (empty($login_val) || empty($password)) {
        $error_msg = 'Please enter your username, email, or mobile number and password.';
    } else {
        // Query user by username OR email OR mobile
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR mobile = ?");
        $stmt->execute([$login_val, $login_val, $login_val]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check Account Status
            if ($user['status'] === 'Blocked') {
                // Exact required message
                $error_msg = 'Your account is blocked. Please contact the administrator.';
            } else {
                // Login successful - establish session
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'mobile' => $user['mobile'],
                    'address' => $user['address'],
                    'profile_image' => $user['profile_image'] ?? 'assets/images/user-avatar.png',
                    'status' => $user['status']
                ];

                // Synchronize database cart with session cart
                if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                $cart_stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
                $cart_stmt->execute([$user['id']]);
                $db_cart = $cart_stmt->fetchAll();
                foreach ($db_cart as $row) {
                    if (!isset($_SESSION['cart'][$row['product_id']])) {
                        $_SESSION['cart'][$row['product_id']] = (int)$row['quantity'];
                    }
                }

                set_flash('success', "Welcome back, " . htmlspecialchars($user['name']) . "!");
                
                $destination = !empty($redirect) ? $redirect : 'index.php';
                header("Location: {$destination}");
                exit;
            }
        } else {
            $error_msg = 'Invalid username/email/mobile or incorrect password.';
        }
    }
}

$page_title = 'Customer Sign In - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="card p-4 p-md-5 mx-auto shadow-sm" style="max-width: 460px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-dark rounded-circle mb-2" style="width: 60px; height: 60px;">
                <i class="bi bi-person-circle fs-2 text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1">Customer Sign In</h3>
            <p class="text-muted small">Sign in with your Username, Email, or Mobile</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" action="user-login.php">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="mb-3">
                <label class="form-label small fw-semibold">Username, Email, or Mobile</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" name="login" value="<?= htmlspecialchars($login_val) ?>" placeholder="e.g. john_doe / john@example.com" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label small fw-semibold mb-1">Password</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
            </button>
        </form>

        <div class="bg-light p-3 rounded small text-muted mb-3">
            <div class="fw-semibold text-dark mb-1"><i class="bi bi-info-circle me-1"></i>Sample Active Account:</div>
            <div>Username: <code>john_doe</code> | Password: <code>password123</code></div>
            <div class="mt-1 text-danger small">Sample Blocked Account: <code>rohit_kumar</code> | <code>password123</code></div>
        </div>

        <div class="text-center border-top pt-3">
            <p class="small text-muted mb-1">Don't have an account yet?</p>
            <a href="register.php" class="fw-bold text-decoration-none">Create a New Account</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>