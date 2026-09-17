<?php 
require_once 'config/database.php';

// 1. Remove Item
if (isset($_GET['remove'])) {
    $remove_id = (int)$_GET['remove'];
    unset($_SESSION['cart'][$remove_id]);
    
    if (is_user_logged_in()) {
        $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?")->execute([current_user_id(), $remove_id]);
    }

    set_flash('info', 'Item removed from cart.');
    header('Location: cart.php');
    exit;
}

// 2. Clear Entire Cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    unset($_SESSION['applied_coupon']);
    
    if (is_user_logged_in()) {
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([current_user_id()]);
    }

    set_flash('info', 'Your shopping cart has been cleared.');
    header('Location: cart.php');
    exit;
}

// 3. Update Item Quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (!empty($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (int)$qty;

            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
                if (is_user_logged_in()) {
                    $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?")->execute([current_user_id(), $pid]);
                }
            } else {
                $st = $pdo->prepare("SELECT stock, status FROM products WHERE id = ?");
                $st->execute([$pid]);
                $prod = $st->fetch();

                if ($prod && $prod['status'] === 'Active') {
                    $final_qty = min($prod['stock'], $qty);
                    $_SESSION['cart'][$pid] = $final_qty;

                    if (is_user_logged_in()) {
                        $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?")->execute([current_user_id(), $pid, $final_qty, $final_qty]);
                    }
                }
            }
        }
        set_flash('success', 'Shopping cart updated successfully.');
    }
    header('Location: cart.php');
    exit;
}

// 4. Apply Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    
    // Calculate raw subtotal first for validation
    $raw_subtotal = 0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $id => $qty) {
            $st = $pdo->prepare("SELECT price, discount FROM products WHERE id = ?");
            $st->execute([(int)$id]);
            $p = $st->fetch();
            if ($p) {
                $raw_subtotal += ($p['price'] * (1 - ($p['discount'] / 100))) * $qty;
            }
        }
    }

    $coupon_res = validate_coupon($pdo, $coupon_code, $raw_subtotal);
    if ($coupon_res['valid']) {
        $_SESSION['applied_coupon'] = [
            'id' => $coupon_res['coupon']['id'],
            'code' => $coupon_res['coupon']['code'],
            'discount_type' => $coupon_res['coupon']['discount_type'],
            'discount_value' => $coupon_res['coupon']['discount_value'],
            'max_discount' => $coupon_res['coupon']['max_discount'],
            'min_order_amount' => $coupon_res['coupon']['min_order_amount']
        ];
        set_flash('success', 'Coupon Applied Successfully!');
    } else {
        unset($_SESSION['applied_coupon']);
        set_flash('danger', $coupon_res['message']);
    }
    header('Location: cart.php');
    exit;
}

// 5. Remove Coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    set_flash('info', 'Coupon removed from your order.');
    header('Location: cart.php');
    exit;
}

// Calculate Cart Items and Pricing
$cart = $_SESSION['cart'] ?? [];
$cart_items = [];
$subtotal = 0;
$total_mrp = 0;

if (!empty($cart)) {
    foreach ($cart as $id => $qty) {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id = ?");
        $stmt->execute([(int)$id]);
        $p = $stmt->fetch();
        if (!$p) continue;

        $unit_final = $p['price'] * (1 - ($p['discount'] / 100));
        $item_subtotal = $unit_final * $qty;
        $mrp_subtotal = $p['price'] * $qty;

        $subtotal += $item_subtotal;
        $total_mrp += $mrp_subtotal;

        $cart_items[] = [
            'product' => $p,
            'qty' => $qty,
            'unit_final' => $unit_final,
            'subtotal' => $item_subtotal,
            'mrp_subtotal' => $mrp_subtotal
        ];
    }
}

$product_discount_savings = $total_mrp - $subtotal;

// Calculate Coupon Discount
$coupon_discount = 0;
$applied_coupon = $_SESSION['applied_coupon'] ?? null;
if ($applied_coupon && $subtotal > 0) {
    // Revalidate against subtotal
    $reval = validate_coupon($pdo, $applied_coupon['code'], $subtotal);
    if ($reval['valid']) {
        $coupon_discount = $reval['discount'];
    } else {
        unset($_SESSION['applied_coupon']);
        $applied_coupon = null;
    }
}

// Delivery Charge: Free over ₹499, else ₹50
$delivery_charge = ($subtotal >= 499 || $subtotal == 0) ? 0.00 : 50.00;

// Tax: 5% GST
$taxable_amount = max(0, $subtotal - $coupon_discount);
$tax = round($taxable_amount * 0.05, 2);

// Grand Total
$grand_total = max(0, $subtotal - $coupon_discount + $delivery_charge + $tax);

$page_title = 'Shopping Cart - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-cart3 me-2 text-warning"></i>Shopping Cart</h2>
            <p class="text-muted mb-0">Review your selected items and apply coupons before checkout</p>
        </div>
        <?php if (!empty($cart_items)): ?>
            <a href="cart.php?clear=1" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to remove all items from your cart?');">
                <i class="bi bi-trash me-1"></i>Clear Cart
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($cart_items)): ?>
        <div class="card text-center p-5 shadow-sm border">
            <div class="py-5">
                <i class="bi bi-cart-x fs-1 text-muted" style="font-size: 4rem !important;"></i>
                <h3 class="fw-bold mt-3">Your Shopping Cart is Empty</h3>
                <p class="text-muted">You have no items in your shopping bag. Explore our deals to get started!</p>
                <a href="products.php" class="btn btn-warning fw-bold px-4 mt-2">
                    <i class="bi bi-bag-plus me-1"></i>Start Shopping
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Cart Items Form & Table -->
            <div class="col-lg-8">
                <form method="post" action="cart.php" id="cartForm">
                    <input type="hidden" name="update_cart" value="1">
                    
                    <div class="card shadow-sm border mb-4">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 250px;">Product</th>
                                        <th>Price</th>
                                        <th style="width: 140px;">Quantity</th>
                                        <th class="text-end">Subtotal</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): 
                                        $p = $item['product'];
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= htmlspecialchars($p['image']) ?>" onerror="this.src='assets/images/default.jpg'" class="rounded border" style="width: 65px; height: 65px; object-fit: cover;" alt="">
                                                    <div>
                                                        <h6 class="fw-bold mb-1">
                                                            <a href="product-details.php?id=<?= $p['id'] ?>" class="text-dark text-decoration-none text-truncate d-block" style="max-width: 220px;">
                                                                <?= htmlspecialchars($p['name']) ?>
                                                            </a>
                                                        </h6>
                                                        <small class="text-muted d-block"><?= htmlspecialchars($p['brand']) ?> · <?= htmlspecialchars($p['category_name']) ?></small>
                                                        <small class="text-success fw-semibold">In Stock: <?= $p['stock'] ?></small>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="fw-bold text-dark"><?= format_price($item['unit_final']) ?></div>
                                                <?php if ($p['discount'] > 0): ?>
                                                    <small class="text-muted text-decoration-line-through"><?= format_price($p['price']) ?></small>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <button class="btn btn-outline-secondary px-2 qty-btn" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown(); document.getElementById('cartForm').submit();">-</button>
                                                    <input type="number" name="quantities[<?= $p['id'] ?>]" class="form-control text-center px-1" value="<?= $item['qty'] ?>" min="1" max="<?= $p['stock'] ?>" onchange="this.form.submit()">
                                                    <button class="btn btn-outline-secondary px-2 qty-btn" type="button" onclick="this.parentNode.querySelector('input[type=number]').stepUp(); document.getElementById('cartForm').submit();">+</button>
                                                </div>
                                            </td>

                                            <td class="text-end fw-bold text-dark">
                                                <?= format_price($item['subtotal']) ?>
                                            </td>

                                            <td class="text-end">
                                                <a href="cart.php?remove=<?= $p['id'] ?>" class="btn btn-sm text-danger hover-danger" title="Remove Item" onclick="return confirm('Remove this product?');">
                                                    <i class="bi bi-trash fs-5"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <!-- Coupon System -->
                <div class="card p-4 shadow-sm border mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-ticket-perforated me-2 text-warning"></i>Apply Coupon Code</h5>
                    
                    <?php if ($applied_coupon): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center py-2 px-3 mb-0">
                            <div>
                                <i class="bi bi-check-circle-fill me-2"></i>
                                Coupon <strong><?= htmlspecialchars($applied_coupon['code']) ?></strong> applied! 
                                (Savings: <b><?= format_price($coupon_discount) ?></b>)
                            </div>
                            <a href="cart.php?remove_coupon=1" class="btn btn-outline-danger btn-sm py-0 px-2 fw-semibold">Remove</a>
                        </div>
                    <?php else: ?>
                        <form method="post" action="cart.php" class="d-flex gap-2">
                            <input type="text" name="coupon_code" class="form-control text-uppercase" placeholder="Enter coupon code (e.g. SAVE10 / WELCOME50)" required>
                            <button type="submit" name="apply_coupon" class="btn btn-dark fw-bold px-4 text-nowrap">
                                Apply Coupon
                            </button>
                        </form>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>Try <code>WELCOME50</code> (50% off up to ₹200) or <code>SAVE10</code> (10% off)
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Continue Shopping
                    </a>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border sticky-top" style="top: 90px;">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Total MRP:</span>
                        <span><?= format_price($total_mrp) ?></span>
                    </div>

                    <?php if ($product_discount_savings > 0): ?>
                        <div class="d-flex justify-content-between mb-2 small text-success">
                            <span>Product Discount:</span>
                            <span>- <?= format_price($product_discount_savings) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-semibold text-dark"><?= format_price($subtotal) ?></span>
                    </div>

                    <?php if ($coupon_discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 small text-success">
                            <span>Coupon Discount (<?= htmlspecialchars($applied_coupon['code']) ?>):</span>
                            <span>- <?= format_price($coupon_discount) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Delivery Charges:</span>
                        <span>
                            <?php if ($delivery_charge == 0): ?>
                                <span class="text-success fw-bold">FREE</span>
                            <?php else: ?>
                                <?= format_price($delivery_charge) ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3 small">
                        <span class="text-muted">GST Tax (5%):</span>
                        <span><?= format_price($tax) ?></span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="fs-5 fw-bold text-dark">Grand Total:</span>
                        <span class="fs-4 fw-bold text-success"><?= format_price($grand_total) ?></span>
                    </div>

                    <?php if (!is_user_logged_in()): ?>
                        <a href="user-login.php?redirect=checkout.php" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In to Checkout
                        </a>
                    <?php else: ?>
                        <a href="checkout.php" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm">
                            <i class="bi bi-shield-check me-1"></i>Proceed to Checkout
                        </a>
                    <?php endif; ?>

                    <div class="text-center mt-3 text-muted small">
                        <i class="bi bi-lock-fill me-1"></i>Simulated 256-bit Encrypted Checkout
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>