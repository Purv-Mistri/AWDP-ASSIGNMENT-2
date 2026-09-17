<?php 
require_once 'config/database.php';

if (is_user_logged_in()) {
    header('Location: index.php');
    exit;
}

$error_msg = '';
$success_msg = '';

$form_data = [
    'name' => '',
    'username' => '',
    'email' => '',
    'mobile' => '',
    'address' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $address = trim($_POST['address'] ?? '');

    $form_data = [
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'mobile' => $mobile,
        'address' => $address
    ];

    // Client-side and server-side validation
    if (empty($name) || empty($username) || empty($email) || empty($mobile) || empty($password) || empty($confirm)) {
        $error_msg = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please provide a valid email address.';
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error_msg = 'Please enter a valid 10-digit mobile number.';
    } elseif (strlen($password) < 6) {
        $error_msg = 'Password must be at least 6 characters long for security.';
    } elseif ($password !== $confirm) {
        $error_msg = 'Password and Confirm Password do not match.';
    } else {
        // Check uniqueness for username, email, and mobile
        $check_stmt = $pdo->prepare("SELECT id, username, email, mobile FROM users WHERE username = ? OR email = ? OR mobile = ?");
        $check_stmt->execute([$username, $email, $mobile]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            if (strtolower($existing['username']) === strtolower($username)) {
                $error_msg = "Username '{$username}' is already taken. Please choose another.";
            } elseif (strtolower($existing['email']) === strtolower($email)) {
                $error_msg = "Email address '{$email}' is already registered. Please log in instead.";
            } else {
                $error_msg = "Mobile number '{$mobile}' is already registered with another account.";
            }
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // IMPORTANT RULE: Newly registered users have status = 'Blocked'
                // Admin must approve/unblock the account before the user can log in.
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, mobile, password, address, status) VALUES (?, ?, ?, ?, ?, ?, 'Blocked')");
                $stmt->execute([$name, $username, $email, $mobile, $hash, $address]);
                $new_user_id = $pdo->lastInsertId();

                // If user entered address, insert default address in addresses table too
                if (!empty($address)) {
                    $addr_stmt = $pdo->prepare("INSERT INTO addresses (user_id, full_name, mobile, address, city, state, pincode, address_type, is_default) VALUES (?, ?, ?, ?, 'City', 'State', '000000', 'Home', 1)");
                    $addr_stmt->execute([$new_user_id, $name, $mobile, $address]);
                }

                $success_msg = "Registration successful! Your account has been created and is currently <strong>Pending Admin Approval (Status: Blocked)</strong>. You will be able to log in as soon as an administrator unblocks and approves your account.";
                // Reset form data
                $form_data = ['name' => '', 'username' => '', 'email' => '', 'mobile' => '', 'address' => ''];

            } catch (Exception $e) {
                $error_msg = 'A database error occurred during registration. Please try again.';
            }
        }
    }
}

$page_title = 'Create Customer Account - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="card p-4 p-md-5 mx-auto shadow-sm" style="max-width: 620px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-warning-subtle text-dark rounded-circle mb-2" style="width: 60px; height: 60px;">
                <i class="bi bi-person-plus-fill fs-2 text-warning"></i>
            </div>
            <h3 class="fw-bold mb-1">Create an Account</h3>
            <p class="text-muted small">Join EveNeed to enjoy exclusive shopping perks and order tracking</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= $error_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show p-4" role="alert">
                <div class="d-flex align-items-start">
                    <i class="bi bi-shield-check fs-2 text-success me-3"></i>
                    <div>
                        <h5 class="alert-heading fw-bold mb-1">Account Created!</h5>
                        <p class="mb-2 small"><?= $success_msg ?></p>
                        <hr class="my-2">
                        <p class="mb-0 small">Already approved by admin? <a href="user-login.php" class="fw-bold text-success text-decoration-underline">Proceed to Login</a></p>
                    </div>
                </div>
            </div>
        <?php else: ?>

            <div class="alert alert-info py-2 small mb-4">
                <i class="bi bi-info-circle-fill me-1"></i> <strong>Note:</strong> Newly created accounts are submitted for administrative approval before first sign-in.
            </div>

            <form method="post" action="register.php" class="needs-validation" novalidate id="registerForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($form_data['name']) ?>" placeholder="e.g. Rahul Sharma" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($form_data['username']) ?>" placeholder="e.g. rahul_sharma" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" placeholder="name@example.com" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" name="mobile" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($form_data['mobile']) ?>" placeholder="10-digit mobile number" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" minlength="6" placeholder="At least 6 characters" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm" minlength="6" placeholder="Re-enter password" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label small fw-semibold">Default Address / Delivery Location</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="House/Flat No, Street, Landmark, City, State, PIN"><?= htmlspecialchars($form_data['address']) ?></textarea>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm">
                            <i class="bi bi-person-check-fill me-2"></i>Register for EveNeed
                        </button>
                    </div>
                </div>
            </form>

            <div class="text-center border-top pt-3 mt-4">
                <p class="small text-muted mb-1">Already registered with an active account?</p>
                <a href="user-login.php" class="fw-bold text-decoration-none">Sign In to Your Account</a>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>