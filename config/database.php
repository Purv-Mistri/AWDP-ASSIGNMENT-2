<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

$db_host = 'localhost';
$db_name = 'eveneed';
$db_user = 'root';
$db_pass = '';

try {
    try {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e1) {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname=shopsphere;charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
} catch (PDOException $e) {
    die("EveNeed Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Flash message helpers
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Currency formatting (Indian Rupee)
function format_price($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// User Authentication checks
function is_user_logged_in() {
    return !empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_user_id() {
    return $_SESSION['user']['id'] ?? 0;
}

// Admin Authentication check
function is_admin_logged_in() {
    return !empty($_SESSION['admin']) && is_array($_SESSION['admin']) && !empty($_SESSION['admin']['id']);
}

// Cart quantity counter
function get_cart_count() {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0;
    }
    return array_sum($_SESSION['cart']);
}

// Wishlist counter for current user
function get_wishlist_count($pdo) {
    if (!is_user_logged_in() || !$pdo) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([current_user_id()]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Recently viewed products tracker
function track_viewed_product($product_id) {
    $product_id = (int)$product_id;
    if ($product_id <= 0) return;
    if (!isset($_SESSION['recently_viewed']) || !is_array($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    // Remove if already in list to move to front
    $_SESSION['recently_viewed'] = array_values(array_diff($_SESSION['recently_viewed'], [$product_id]));
    array_unshift($_SESSION['recently_viewed'], $product_id);
    // Keep max 6 items
    if (count($_SESSION['recently_viewed']) > 6) {
        $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 6);
    }
}

// Coupon validation helper
function validate_coupon($pdo, $code, $subtotal) {
    $code = strtoupper(trim($code));
    if (empty($code)) {
        return ['valid' => false, 'message' => 'Please enter a coupon code.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ?");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['valid' => false, 'message' => 'Invalid coupon code.'];
    }

    if ($coupon['status'] !== 'Active') {
        return ['valid' => false, 'message' => 'This coupon is no longer active.'];
    }

    $today = date('Y-m-d');
    if ($coupon['expiry_date'] < $today) {
        return ['valid' => false, 'message' => 'Coupon has expired.'];
    }

    if ($coupon['usage_limit'] > 0 && $coupon['used_count'] >= $coupon['usage_limit']) {
        return ['valid' => false, 'message' => 'Coupon usage limit has been reached.'];
    }

    if ($subtotal < (float)$coupon['min_order_amount']) {
        return [
            'valid' => false, 
            'message' => "Minimum order amount of " . format_price($coupon['min_order_amount']) . " required to apply this coupon."
        ];
    }

    // Calculate discount
    $discount = 0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = ($subtotal * (float)$coupon['discount_value']) / 100;
        if ((float)$coupon['max_discount'] > 0) {
            $discount = min($discount, (float)$coupon['max_discount']);
        }
    } else {
        $discount = min((float)$coupon['discount_value'], $subtotal);
    }

    return [
        'valid' => true,
        'coupon' => $coupon,
        'discount' => round($discount, 2),
        'message' => 'Coupon applied successfully!'
    ];
}

// Dynamic base path helper for assets and links
function get_base_url() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return (strpos($script, '/admin/') !== false) ? '../' : '';
}
?>