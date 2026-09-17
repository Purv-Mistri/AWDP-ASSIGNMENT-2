<?php 
require_once '../includes/admin-auth.php';

// Handle Delete Coupon
if (isset($_GET['del'])) {
    $del_id = (int)$_GET['del'];
    $del_stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $del_stmt->execute([$del_id]);
    set_flash('success', 'Coupon deleted successfully.');
    header('Location: coupons.php');
    exit;
}

// Handle Status Toggle
if (isset($_GET['toggle_status'])) {
    $cid = (int)$_GET['toggle_status'];
    $c_stmt = $pdo->prepare("SELECT status FROM coupons WHERE id = ?");
    $c_stmt->execute([$cid]);
    $curr = $c_stmt->fetchColumn();
    if ($curr) {
        $new_st = ($curr === 'Active') ? 'Inactive' : 'Active';
        $pdo->prepare("UPDATE coupons SET status = ? WHERE id = ?")->execute([$new_st, $cid]);
        set_flash('success', "Coupon status updated to {$new_st}.");
    }
    header('Location: coupons.php');
    exit;
}

// Handle Add Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_order_amount = (float)($_POST['min_order_amount'] ?? 0);
    $max_discount = (float)($_POST['max_discount'] ?? 0);
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $usage_limit = (int)($_POST['usage_limit'] ?? 100);
    $status = $_POST['status'] ?? 'Active';

    if (empty($code) || $discount_value <= 0 || empty($expiry_date)) {
        set_flash('danger', 'Please provide a valid coupon code, discount value, and expiration date.');
    } else {
        $chk = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            set_flash('danger', "Coupon code '{$code}' already exists.");
        } else {
            $ins = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount, expiry_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$code, $discount_type, $discount_value, $min_order_amount, $max_discount, $expiry_date, $usage_limit, $status]);
            set_flash('success', "Coupon '{$code}' created successfully!");
        }
    }
    header('Location: coupons.php');
    exit;
}

// Handle Edit Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_coupon'])) {
    $id = (int)($_POST['coupon_id'] ?? 0);
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = (float)($_POST['discount_value'] ?? 0);
    $min_order_amount = (float)($_POST['min_order_amount'] ?? 0);
    $max_discount = (float)($_POST['max_discount'] ?? 0);
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $usage_limit = (int)($_POST['usage_limit'] ?? 100);
    $status = $_POST['status'] ?? 'Active';

    if ($id > 0 && !empty($code) && $discount_value > 0 && !empty($expiry_date)) {
        $up = $pdo->prepare("UPDATE coupons SET code=?, discount_type=?, discount_value=?, min_order_amount=?, max_discount=?, expiry_date=?, usage_limit=?, status=? WHERE id=?");
        $up->execute([$code, $discount_type, $discount_value, $min_order_amount, $max_discount, $expiry_date, $usage_limit, $status, $id]);
        set_flash('success', "Coupon #{$id} updated successfully.");
    } else {
        set_flash('danger', 'Failed to update coupon. Please check required fields.');
    }
    header('Location: coupons.php');
    exit;
}

// Fetch all coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();

// Edit modal data
$edit_coupon = null;
if (isset($_GET['edit_id'])) {
    $ec_id = (int)$_GET['edit_id'];
    $ec_stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $ec_stmt->execute([$ec_id]);
    $edit_coupon = $ec_stmt->fetch();
}

$page_title = 'Manage Coupons - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-ticket-perforated me-2 text-warning"></i>Discount Coupons & Promotional Offers</h2>
            <p class="text-muted mb-0">Create promotional promo codes, set minimum purchase criteria, discount limits, and track usage counts</p>
        </div>
        <button class="btn btn-warning fw-bold btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCouponModal">
            <i class="bi bi-plus-circle me-1"></i>Create New Coupon
        </button>
    </div>

    <!-- Coupons Table -->
    <div class="card shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Coupon Code</th>
                        <th>Discount Value</th>
                        <th>Min Order Req.</th>
                        <th>Max Discount Cap</th>
                        <th>Usage / Limit</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No discount coupons found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $cp): 
                            $today = date('Y-m-d');
                            $is_expired = ($cp['expiry_date'] < $today);
                        ?>
                            <tr>
                                <td><b>#<?= $cp['id'] ?></b></td>
                                <td>
                                    <span class="badge bg-dark text-warning fs-6 px-3 py-2 fw-bold letter-spacing-1"><?= htmlspecialchars($cp['code']) ?></span>
                                </td>
                                <td>
                                    <?php if ($cp['discount_type'] === 'percentage'): ?>
                                        <span class="fw-bold text-success fs-6"><?= (int)$cp['discount_value'] ?>% OFF</span>
                                    <?php else: ?>
                                        <span class="fw-bold text-primary fs-6"><?= format_price($cp['discount_value']) ?> Flat OFF</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_price($cp['min_order_amount']) ?></td>
                                <td><?= $cp['max_discount'] > 0 ? format_price($cp['max_discount']) : '<span class="text-muted">No Cap</span>' ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold"><?= $cp['used_count'] ?> / <?= $cp['usage_limit'] ?></span>
                                        <?php if ($cp['used_count'] >= $cp['usage_limit']): ?>
                                            <span class="badge bg-danger">Limit Reached</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_expired): ?>
                                        <span class="text-danger fw-semibold"><i class="bi bi-calendar-x me-1"></i><?= date('M d, Y', strtotime($cp['expiry_date'])) ?> (Expired)</span>
                                    <?php else: ?>
                                        <span class="text-dark"><i class="bi bi-calendar-check me-1 text-success"></i><?= date('M d, Y', strtotime($cp['expiry_date'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cp['status'] === 'Active' && !$is_expired): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= $is_expired ? 'Expired' : 'Inactive' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="coupons.php?toggle_status=<?= $cp['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Toggle Active/Inactive">
                                            <i class="bi <?= $cp['status'] === 'Active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' ?>"></i>
                                        </a>
                                        <a href="coupons.php?edit_id=<?= $cp['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit Coupon">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="coupons.php?del=<?= $cp['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete Coupon" onclick="return confirm('Delete this coupon code?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1" aria-labelledby="addCouponModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addCouponModalLabel"><i class="bi bi-plus-circle me-2 text-warning"></i>Create New Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="coupons.php">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Coupon Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control text-uppercase" placeholder="e.g. FESTIVE20" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Type</label>
                            <select name="discount_type" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="e.g. 20 for 20%" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Min Order Amount (₹)</label>
                            <input type="number" step="0.01" name="min_order_amount" class="form-control" value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Max Discount Cap (₹)</label>
                            <input type="number" step="0.01" name="max_discount" class="form-control" value="0.00" placeholder="0 for unlimited">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 months')) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" value="100">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_coupon" class="btn btn-warning fw-bold btn-sm px-4">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Coupon Modal -->
<?php if ($edit_coupon): ?>
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Coupon #<?= $edit_coupon['id'] ?></h5>
                <a href="coupons.php" class="btn-close"></a>
            </div>
            <form method="post" action="coupons.php">
                <input type="hidden" name="coupon_id" value="<?= $edit_coupon['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Coupon Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control text-uppercase" value="<?= htmlspecialchars($edit_coupon['code']) ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Type</label>
                            <select name="discount_type" class="form-select">
                                <option value="percentage" <?= $edit_coupon['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                                <option value="fixed" <?= $edit_coupon['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" value="<?= $edit_coupon['discount_value'] ?>" required>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Min Order Amount (₹)</label>
                            <input type="number" step="0.01" name="min_order_amount" class="form-control" value="<?= $edit_coupon['min_order_amount'] ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Max Discount Cap (₹)</label>
                            <input type="number" step="0.01" name="max_discount" class="form-control" value="<?= $edit_coupon['max_discount'] ?>">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" value="<?= $edit_coupon['expiry_date'] ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" value="<?= $edit_coupon['usage_limit'] ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= $edit_coupon['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="Inactive" <?= $edit_coupon['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="coupons.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" name="edit_coupon" class="btn btn-primary btn-sm fw-bold px-4">Update Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>