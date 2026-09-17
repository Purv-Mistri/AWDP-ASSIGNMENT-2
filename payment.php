<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('warning', 'Your cart is empty.');
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

// 1. Gather Shipping Address Information
$shipping_full_name = trim($_POST['shipping_full_name'] ?? '');
$shipping_mobile = trim($_POST['shipping_mobile'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_city = trim($_POST['shipping_city'] ?? '');
$shipping_state = trim($_POST['shipping_state'] ?? '');
$shipping_pincode = trim($_POST['shipping_pincode'] ?? '');

if (empty($shipping_full_name) || empty($shipping_mobile) || empty($shipping_address) || empty($shipping_city) || empty($shipping_state) || empty($shipping_pincode)) {
    set_flash('danger', 'Please provide complete shipping address details.');
    header('Location: checkout.php');
    exit;
}

// 2. Gather Billing Address Information
$same_as_shipping = isset($_POST['same_as_shipping']) ? 1 : 0;
if ($same_as_shipping) {
    $billing_full_name = $shipping_full_name;
    $billing_mobile = $shipping_mobile;
    $billing_address = $shipping_address;
    $billing_city = $shipping_city;
    $billing_state = $shipping_state;
    $billing_pincode = $shipping_pincode;
} else {
    $billing_full_name = trim($_POST['billing_full_name'] ?? '') ?: $shipping_full_name;
    $billing_mobile = trim($_POST['billing_mobile'] ?? '') ?: $shipping_mobile;
    $billing_address = trim($_POST['billing_address'] ?? '') ?: $shipping_address;
    $billing_city = trim($_POST['billing_city'] ?? '') ?: $shipping_city;
    $billing_state = trim($_POST['billing_state'] ?? '') ?: $shipping_state;
    $billing_pincode = trim($_POST['billing_pincode'] ?? '') ?: $shipping_pincode;
}

// 3. Gather & Validate Payment Information
$payment_method = trim($_POST['payment_method'] ?? 'Cash on Delivery');
$payment_status = 'Completed';
$card_last_four = null;
$upi_id = null;
$bank_name = null;
$txn_id = 'TXN-' . strtoupper(substr($payment_method, 0, 3)) . '-' . time() . rand(100, 999);

if ($payment_method === 'Credit / Debit Card') {
    $card_name = trim($_POST['card_name'] ?? '');
    $card_number = preg_replace('/\s+/', '', trim($_POST['card_number'] ?? ''));
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv = trim($_POST['card_cvv'] ?? '');

    if (empty($card_name) || empty($card_number) || empty($card_expiry) || empty($card_cvv)) {
        set_flash('danger', 'Please provide all credit/debit card payment details.');
        header('Location: checkout.php');
        exit;
    }
    if (strlen($card_number) < 13 || strlen($card_number) > 19) {
        set_flash('danger', 'Please enter a valid card number.');
        header('Location: checkout.php');
        exit;
    }
    if (strlen($card_cvv) < 3) {
        set_flash('danger', 'Please enter a valid 3-digit CVV.');
        header('Location: checkout.php');
        exit;
    }
    $card_last_four = substr($card_number, -4);

} elseif ($payment_method === 'UPI') {
    $upi_id = trim($_POST['upi_id'] ?? '');
    if (empty($upi_id) || strpos($upi_id, '@') === false) {
        set_flash('danger', 'Please enter a valid UPI ID (e.g. username@okhdfcbank).');
        header('Location: checkout.php');
        exit;
    }
} elseif ($payment_method === 'Net Banking') {
    $bank_name = trim($_POST['bank_name'] ?? 'HDFC Bank');
} else {
    // Cash on Delivery
    $payment_method = 'Cash on Delivery';
    $payment_status = 'Pending';
}

// 4. Begin Database Transaction
$pdo->beginTransaction();

try {
    $cart = $_SESSION['cart'];
    $subtotal = 0;
    $order_items_data = [];

    // Verify stock and fetch fresh product data
    foreach ($cart as $pid => $qty) {
        $st = $pdo->prepare("SELECT * FROM products WHERE id = ? FOR UPDATE");
        $st->execute([(int)$pid]);
        $prod = $st->fetch();

        if (!$prod || $prod['status'] !== 'Active' || $prod['stock'] < $qty) {
            throw new Exception("Product '" . ($prod['name'] ?? 'Item') . "' does not have sufficient stock available.");
        }

        $unit_price = (float)$prod['price'];
        $discount = (float)$prod['discount'];
        $final_price = $unit_price * (1 - ($discount / 100));
        $item_subtotal = $final_price * $qty;

        $subtotal += $item_subtotal;

        $order_items_data[] = [
            'product_id' => $prod['id'],
            'product_name' => $prod['name'],
            'brand' => $prod['brand'],
            'price' => $unit_price,
            'discount' => $discount,
            'final_price' => $final_price,
            'quantity' => $qty,
            'subtotal' => $item_subtotal
        ];
    }

    // Coupon Calculation
    $coupon_id = null;
    $coupon_code = null;
    $discount_amount = 0.00;
    $applied_coupon = $_SESSION['applied_coupon'] ?? null;

    if ($applied_coupon) {
        $c_chk = validate_coupon($pdo, $applied_coupon['code'], $subtotal);
        if ($c_chk['valid']) {
            $coupon_id = $c_chk['coupon']['id'];
            $coupon_code = $c_chk['coupon']['code'];
            $discount_amount = $c_chk['discount'];
        }
    }

    // Delivery charge & tax
    $delivery_charge = ($subtotal >= 499) ? 0.00 : 50.00;
    $taxable_amount = max(0, $subtotal - $discount_amount);
    $tax = round($taxable_amount * 0.05, 2);
    $total_amount = max(0, $subtotal - $discount_amount + $delivery_charge + $tax);

    // Generate unique human-readable Order Number
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    // Insert Order
    $order_stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, subtotal, discount_amount, coupon_id, coupon_code,
            delivery_charge, tax, total_amount, payment_method, payment_status, order_status,
            shipping_full_name, shipping_mobile, shipping_address, shipping_city, shipping_state, shipping_pincode,
            billing_full_name, billing_mobile, billing_address, billing_city, billing_state, billing_pincode
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, 'Pending',
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?
        )
    ");

    $order_stmt->execute([
        $order_number, $user_id, $subtotal, $discount_amount, $coupon_id, $coupon_code,
        $delivery_charge, $tax, $total_amount, $payment_method, $payment_status,
        $shipping_full_name, $shipping_mobile, $shipping_address, $shipping_city, $shipping_state, $shipping_pincode,
        $billing_full_name, $billing_mobile, $billing_address, $billing_city, $billing_state, $billing_pincode
    ]);
    $order_id = $pdo->lastInsertId();

    // Insert Order Items & Deduct Stock
    $item_ins = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, brand, price, discount, final_price, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stock_up = $pdo->prepare("
        UPDATE products 
        SET stock = stock - ?, 
            status = IF(stock - ? <= 0, 'Out of Stock', status) 
        WHERE id = ?
    ");

    foreach ($order_items_data as $it) {
        $item_ins->execute([
            $order_id, $it['product_id'], $it['product_name'], $it['brand'],
            $it['price'], $it['discount'], $it['final_price'], $it['quantity'], $it['subtotal']
        ]);
        $stock_up->execute([$it['quantity'], $it['quantity'], $it['product_id']]);
    }

    // Insert Payment Record
    $pay_stmt = $pdo->prepare("
        INSERT INTO payments (order_id, user_id, payment_method, transaction_id, amount, status, card_last_four, upi_id, bank_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $pay_stmt->execute([
        $order_id, $user_id, $payment_method, $txn_id, $total_amount, $payment_status,
        $card_last_four, $upi_id, $bank_name
    ]);

    // Record Coupon Usage if coupon applied
    if ($coupon_id) {
        $pdo->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_id) VALUES (?, ?, ?)")->execute([$coupon_id, $user_id, $order_id]);
        $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$coupon_id]);
    }

    // Clear cart in session and database
    $_SESSION['cart'] = [];
    unset($_SESSION['applied_coupon']);
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    $pdo->commit();

    set_flash('success', "Order placed successfully! Your Order ID is #{$order_number}.");
    header("Location: order-success.php?id={$order_id}");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('danger', 'Order placement failed: ' . $e->getMessage());
    header('Location: checkout.php');
    exit;
}
?>
