<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();

// Ensure cart is not empty
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('warning', 'Your shopping cart is empty. Please add products before checking out.');
    header('Location: cart.php');
    exit;
}

// Calculate cart items & totals
$cart = $_SESSION['cart'];
$cart_items = [];
$subtotal = 0;
$total_mrp = 0;

foreach ($cart as $id => $qty) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id = ?");
    $stmt->execute([(int)$id]);
    $p = $stmt->fetch();
    if (!$p) continue;

    // Check stock availability
    if ($p['stock'] < $qty || $p['status'] !== 'Active') {
        set_flash('danger', "Product '" . htmlspecialchars($p['name']) . "' only has {$p['stock']} units available in stock. Please adjust your cart.");
        header('Location: cart.php');
        exit;
    }

    $unit_final = $p['price'] * (1 - ($p['discount'] / 100));
    $item_subtotal = $unit_final * $qty;
    $mrp_subtotal = $p['price'] * $qty;

    $subtotal += $item_subtotal;
    $total_mrp += $mrp_subtotal;

    $cart_items[] = [
        'product' => $p,
        'qty' => $qty,
        'unit_final' => $unit_final,
        'subtotal' => $item_subtotal
    ];
}

if (empty($cart_items)) {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit;
}

// Coupon Discount
$coupon_discount = 0;
$applied_coupon = $_SESSION['applied_coupon'] ?? null;
if ($applied_coupon && $subtotal > 0) {
    $c_res = validate_coupon($pdo, $applied_coupon['code'], $subtotal);
    if ($c_res['valid']) {
        $coupon_discount = $c_res['discount'];
    } else {
        unset($_SESSION['applied_coupon']);
        $applied_coupon = null;
    }
}

// Delivery Charge: Free over ₹499
$delivery_charge = ($subtotal >= 499) ? 0.00 : 50.00;

// Tax: 5% GST
$taxable_amount = max(0, $subtotal - $coupon_discount);
$tax = round($taxable_amount * 0.05, 2);

// Grand Total
$grand_total = max(0, $subtotal - $coupon_discount + $delivery_charge + $tax);

// Fetch saved user addresses
$addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addr_stmt->execute([$user_id]);
$saved_addresses = $addr_stmt->fetchAll();

$user_profile = current_user();

$page_title = 'Multi-Step Checkout - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none">Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>

    <div class="mb-4">
        <h2 class="fw-bold mb-1"><i class="bi bi-shield-check me-2 text-warning"></i>Multi-Step Checkout</h2>
        <p class="text-muted mb-0">Complete your delivery and payment details in 5 simple steps</p>
    </div>

    <!-- Main Checkout Form pointing to payment.php -->
    <form method="post" action="payment.php" id="checkoutForm" class="needs-validation" novalidate>
        <div class="row g-4">
            <!-- Steps Column -->
            <div class="col-lg-8">
                
                <!-- STEP 1: Cart Review -->
                <div class="card p-4 shadow-sm border mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">1</span>
                        <h5 class="fw-bold mb-0">Review Cart Items (<?= count($cart_items) ?> Products)</h5>
                        <a href="cart.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-pencil me-1"></i>Edit Cart</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $ci): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold d-block text-truncate" style="max-width: 280px;"><?= htmlspecialchars($ci['product']['name']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($ci['product']['brand']) ?></small>
                                        </td>
                                        <td><?= $ci['qty'] ?></td>
                                        <td><?= format_price($ci['unit_final']) ?></td>
                                        <td class="text-end fw-bold"><?= format_price($ci['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STEP 2: Shipping Address -->
                <div class="card p-4 shadow-sm border mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">2</span>
                        <h5 class="fw-bold mb-0">Shipping / Delivery Address</h5>
                    </div>

                    <?php if (!empty($saved_addresses)): ?>
                        <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Select a Saved Address:</h6>
                        <div class="row g-3 mb-3">
                            <?php foreach ($saved_addresses as $idx => $s_addr): ?>
                                <div class="col-md-6">
                                    <div class="card p-3 border h-100 saved-addr-card <?= $s_addr['is_default'] ? 'border-primary bg-primary-subtle bg-opacity-25' : '' ?>">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="shipping_address_choice" id="s_addr_<?= $s_addr['id'] ?>" value="<?= $s_addr['id'] ?>" <?= $s_addr['is_default'] || $idx === 0 ? 'checked' : '' ?> onclick="populateShippingAddress(<?= htmlspecialchars(json_encode($s_addr)) ?>)">
                                            <label class="form-check-label w-100" for="s_addr_<?= $s_addr['id'] ?>">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($s_addr['full_name']) ?></span>
                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($s_addr['address_type']) ?></span>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    <?= htmlspecialchars($s_addr['address']) ?>, <?= htmlspecialchars($s_addr['city']) ?>, <?= htmlspecialchars($s_addr['state']) ?> - <?= htmlspecialchars($s_addr['pincode']) ?>
                                                </small>
                                                <small class="text-dark d-block mt-1">Phone: <?= htmlspecialchars($s_addr['mobile']) ?></small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                    $def = !empty($saved_addresses) ? $saved_addresses[0] : null;
                    ?>
                    <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Shipping Details:</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Recipient Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="shipping_full_name" id="shipping_full_name" value="<?= htmlspecialchars($def['full_name'] ?? $user_profile['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="shipping_mobile" id="shipping_mobile" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($def['mobile'] ?? $user_profile['mobile'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Street Address / House No. <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="shipping_address" id="shipping_address" rows="2" required><?= htmlspecialchars($def['address'] ?? $user_profile['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="shipping_city" id="shipping_city" value="<?= htmlspecialchars($def['city'] ?? 'Bengaluru') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="shipping_state" id="shipping_state" value="<?= htmlspecialchars($def['state'] ?? 'Karnataka') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Pincode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="shipping_pincode" id="shipping_pincode" maxlength="6" value="<?= htmlspecialchars($def['pincode'] ?? '560001') ?>" required>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Billing Address -->
                <div class="card p-4 shadow-sm border mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">3</span>
                        <h5 class="fw-bold mb-0">Billing Address</h5>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="same_as_shipping" id="sameAsShipping" value="1" checked onchange="toggleBillingAddress()">
                        <label class="form-check-label fw-semibold" for="sameAsShipping">
                            Billing Address is the same as Shipping Address
                        </label>
                    </div>

                    <div id="billingAddressFields" style="display: none;">
                        <h6 class="fw-semibold text-muted text-uppercase fs-7 mb-2">Enter Billing Address:</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Billing Name</label>
                                <input type="text" class="form-control" name="billing_full_name" id="billing_full_name" value="<?= htmlspecialchars($user_profile['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Billing Phone</label>
                                <input type="tel" class="form-control" name="billing_mobile" id="billing_mobile" maxlength="10" value="<?= htmlspecialchars($user_profile['mobile'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Billing Street Address</label>
                                <textarea class="form-control" name="billing_address" id="billing_address" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">City</label>
                                <input type="text" class="form-control" name="billing_city" id="billing_city">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">State</label>
                                <input type="text" class="form-control" name="billing_state" id="billing_state">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Pincode</label>
                                <input type="text" class="form-control" name="billing_pincode" id="billing_pincode" maxlength="6">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4 & 5: Simulated Payment Method -->
                <div class="card p-4 shadow-sm border mb-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">4 & 5</span>
                        <h5 class="fw-bold mb-0">Select Payment Method (Simulation)</h5>
                    </div>

                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i> This is an academic project payment simulation. No real money will be charged.
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- COD Option -->
                        <div class="col-md-6">
                            <div class="card p-3 border payment-option-card h-100">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_cod" value="Cash on Delivery" checked onchange="handlePaymentMethodChange('cod')">
                                    <label class="form-check-label fw-bold text-dark" for="pay_cod">
                                        <i class="bi bi-cash-coin text-success fs-5 me-1"></i> Cash on Delivery
                                        <small class="text-muted d-block fw-normal">Pay with cash upon package receipt</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- UPI Option -->
                        <div class="col-md-6">
                            <div class="card p-3 border payment-option-card h-100">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_upi" value="UPI" onchange="handlePaymentMethodChange('upi')">
                                    <label class="form-check-label fw-bold text-dark" for="pay_upi">
                                        <i class="bi bi-qr-code text-primary fs-5 me-1"></i> UPI / QR Code
                                        <small class="text-muted d-block fw-normal">Google Pay, PhonePe, Paytm, BHIM</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Card Option -->
                        <div class="col-md-6">
                            <div class="card p-3 border payment-option-card h-100">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_card" value="Credit / Debit Card" onchange="handlePaymentMethodChange('card')">
                                    <label class="form-check-label fw-bold text-dark" for="pay_card">
                                        <i class="bi bi-credit-card text-warning fs-5 me-1"></i> Credit / Debit Card
                                        <small class="text-muted d-block fw-normal">Visa, MasterCard, RuPay, Amex</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Net Banking Option -->
                        <div class="col-md-6">
                            <div class="card p-3 border payment-option-card h-100">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="pay_netbanking" value="Net Banking" onchange="handlePaymentMethodChange('netbanking')">
                                    <label class="form-check-label fw-bold text-dark" for="pay_netbanking">
                                        <i class="bi bi-bank text-info fs-5 me-1"></i> Net Banking
                                        <small class="text-muted d-block fw-normal">HDFC, ICICI, SBI, Axis, Kotak</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Payment Sub-forms -->
                    <!-- 1. COD Notice -->
                    <div id="cod_details" class="payment-details-box p-3 bg-light rounded-3 border">
                        <p class="mb-0 small text-success fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> Cash on Delivery Selected. You can verify your package before handing over payment.
                        </p>
                    </div>

                    <!-- 2. UPI Form -->
                    <div id="upi_details" class="payment-details-box p-3 bg-light rounded-3 border" style="display: none;">
                        <label class="form-label small fw-semibold">Enter your Virtual Payment Address (UPI ID) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="upi_id" placeholder="e.g. yourname@okhdfcbank / mobile@upi" id="upi_id_input">
                        <small class="text-muted mt-1 d-block">A payment request simulator will confirm this transaction automatically.</small>
                    </div>

                    <!-- 3. Card Form -->
                    <div id="card_details" class="payment-details-box p-3 bg-light rounded-3 border" style="display: none;">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Name on Card <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="card_name" placeholder="Full name as shown on card" id="card_name_input">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Card Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="card_number" maxlength="19" placeholder="1234 5678 9012 3456" id="card_number_input">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Expiry Date (MM/YY) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="card_expiry" maxlength="5" placeholder="MM/YY" id="card_expiry_input">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">CVV <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="card_cvv" maxlength="3" placeholder="3 digits" id="card_cvv_input">
                            </div>
                        </div>
                    </div>

                    <!-- 4. Net Banking Form -->
                    <div id="netbanking_details" class="payment-details-box p-3 bg-light rounded-3 border" style="display: none;">
                        <label class="form-label small fw-semibold">Select Your Bank</label>
                        <select name="bank_name" class="form-select">
                            <option value="HDFC Bank">HDFC Bank</option>
                            <option value="State Bank of India">State Bank of India (SBI)</option>
                            <option value="ICICI Bank">ICICI Bank</option>
                            <option value="Axis Bank">Axis Bank</option>
                            <option value="Kotak Mahindra Bank">Kotak Mahindra Bank</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sticky Card -->
            <div class="col-lg-4">
                <div class="card p-4 shadow-sm border sticky-top" style="top: 90px;">
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Order Price Summary</h5>

                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Items Subtotal:</span>
                        <span class="fw-semibold text-dark"><?= format_price($subtotal) ?></span>
                    </div>

                    <?php if ($coupon_discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 small text-success">
                            <span>Coupon (<?= htmlspecialchars($applied_coupon['code']) ?>):</span>
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
                        <span class="fs-5 fw-bold text-dark">Amount Payable:</span>
                        <span class="fs-4 fw-bold text-success"><?= format_price($grand_total) ?></span>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm mb-3">
                        <i class="bi bi-lock-fill me-1"></i>Place Simulated Order
                    </button>

                    <div class="text-center text-muted small">
                        By placing your order, you agree to EveNeed's terms of service and simulated purchase policies.
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function populateShippingAddress(addr) {
    document.getElementById('shipping_full_name').value = addr.full_name;
    document.getElementById('shipping_mobile').value = addr.mobile;
    document.getElementById('shipping_address').value = addr.address;
    document.getElementById('shipping_city').value = addr.city;
    document.getElementById('shipping_state').value = addr.state;
    document.getElementById('shipping_pincode').value = addr.pincode;
}

function toggleBillingAddress() {
    const isSame = document.getElementById('sameAsShipping').checked;
    const box = document.getElementById('billingAddressFields');
    box.style.display = isSame ? 'none' : 'block';
}

function handlePaymentMethodChange(type) {
    document.getElementById('cod_details').style.display = (type === 'cod') ? 'block' : 'none';
    document.getElementById('upi_details').style.display = (type === 'upi') ? 'block' : 'none';
    document.getElementById('card_details').style.display = (type === 'card') ? 'block' : 'none';
    document.getElementById('netbanking_details').style.display = (type === 'netbanking') ? 'block' : 'none';
}
</script>

<?php include 'includes/footer.php'; ?>