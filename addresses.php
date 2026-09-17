<?php 
require_once 'includes/auth.php';

$user_id = current_user_id();

// Handle Delete Address
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $del = $pdo->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
    $del->execute([$del_id, $user_id]);
    set_flash('success', 'Address deleted successfully.');
    header('Location: addresses.php');
    exit;
}

// Handle Set Default Address
if (isset($_GET['set_default'])) {
    $def_id = (int)$_GET['set_default'];
    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$def_id, $user_id]);
    set_flash('success', 'Default address updated.');
    header('Location: addresses.php');
    exit;
}

$error_msg = '';

// Handle Add / Edit Address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addr_id = (int)($_POST['address_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $address_type = trim($_POST['address_type'] ?? 'Home');
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if (empty($full_name) || empty($mobile) || empty($address) || empty($city) || empty($state) || empty($pincode)) {
        $error_msg = 'Please fill out all address fields.';
    } else {
        if ($is_default) {
            $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
        }

        if ($addr_id > 0) {
            // Update
            $up = $pdo->prepare("UPDATE addresses SET full_name=?, mobile=?, address=?, city=?, state=?, pincode=?, address_type=?, is_default=? WHERE id=? AND user_id=?");
            $up->execute([$full_name, $mobile, $address, $city, $state, $pincode, $address_type, $is_default, $addr_id, $user_id]);
            set_flash('success', 'Address updated successfully!');
        } else {
            // If first address, make it default automatically
            $count = (int)$pdo->query("SELECT COUNT(*) FROM addresses WHERE user_id = {$user_id}")->fetchColumn();
            if ($count === 0) {
                $is_default = 1;
            }

            // Insert
            $ins = $pdo->prepare("INSERT INTO addresses (user_id, full_name, mobile, address, city, state, pincode, address_type, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$user_id, $full_name, $mobile, $address, $city, $state, $pincode, $address_type, $is_default]);
            set_flash('success', 'New address added successfully!');
        }
        header('Location: addresses.php');
        exit;
    }
}

// Fetch addresses
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$user_id]);
$addresses = $stmt->fetchAll();

// Edit modal data
$edit_addr = null;
if (isset($_GET['edit'])) {
    $e_id = (int)$_GET['edit'];
    $e_stmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
    $e_stmt->execute([$e_id, $user_id]);
    $edit_addr = $e_stmt->fetch();
}

$page_title = 'Manage Saved Addresses - EveNeed';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="profile.php" class="text-decoration-none">My Profile</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Addresses</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-geo-alt me-2"></i>My Saved Addresses</h2>
            <p class="text-muted mb-0">Manage delivery locations for quick, one-click checkouts</p>
        </div>
        <button class="btn btn-warning fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addressModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Address
        </button>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger py-2 small mb-4"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (empty($addresses)): ?>
        <div class="card text-center p-5 shadow-sm">
            <i class="bi bi-geo-alt fs-1 text-muted"></i>
            <h4 class="fw-bold mt-3">No Saved Addresses Found</h4>
            <p class="text-muted">Add your home or office address to enable streamlined checkout.</p>
            <div>
                <button class="btn btn-warning fw-bold px-4" data-bs-toggle="modal" data-bs-target="#addressModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Address Now
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($addresses as $addr): ?>
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm h-100 position-relative <?= $addr['is_default'] ? 'border-primary border-2' : '' ?>">
                        <?php if ($addr['is_default']): ?>
                            <span class="badge bg-primary position-absolute top-0 end-0 m-3 px-3 py-2">
                                <i class="bi bi-check2-circle me-1"></i>Default Address
                            </span>
                        <?php endif; ?>

                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($addr['address_type']) ?></span>
                            <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($addr['full_name']) ?></span>
                        </div>

                        <p class="text-secondary small mb-2 flex-grow-1">
                            <?= nl2br(htmlspecialchars($addr['address'])) ?><br>
                            <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['state']) ?> - <b><?= htmlspecialchars($addr['pincode']) ?></b>
                        </p>
                        
                        <div class="small text-muted mb-3">
                            <i class="bi bi-telephone me-1"></i>Mobile: <b><?= htmlspecialchars($addr['mobile']) ?></b>
                        </div>

                        <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                            <a href="addresses.php?edit=<?= $addr['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                            <?php if (!$addr['is_default']): ?>
                                <a href="addresses.php?set_default=<?= $addr['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    Set as Default
                                </a>
                                <a href="addresses.php?delete=<?= $addr['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this address?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Address Modal -->
<div class="modal fade <?= $edit_addr ? 'show d-block' : '' ?>" id="addressModal" tabindex="-1" aria-labelledby="addressModalLabel" aria-hidden="<?= $edit_addr ? 'false' : 'true' ?>" style="<?= $edit_addr ? 'background: rgba(0,0,0,0.5);' : '' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addressModalLabel">
                    <i class="bi bi-geo-alt me-1 text-warning"></i><?= $edit_addr ? 'Edit Address' : 'Add New Address' ?>
                </h5>
                <?php if ($edit_addr): ?>
                    <a href="addresses.php" class="btn-close" aria-label="Close"></a>
                <?php else: ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php endif; ?>
            </div>
            <form method="post" action="addresses.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="address_id" value="<?= $edit_addr['id'] ?? 0 ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Recipient Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($edit_addr['full_name'] ?? $_SESSION['user']['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="mobile" maxlength="10" pattern="[0-9]{10}" value="<?= htmlspecialchars($edit_addr['mobile'] ?? $_SESSION['user']['mobile'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Street Address / House No. <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="address" rows="2" required><?= htmlspecialchars($edit_addr['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($edit_addr['city'] ?? 'Bengaluru') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" value="<?= htmlspecialchars($edit_addr['state'] ?? 'Karnataka') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Pincode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="pincode" maxlength="6" value="<?= htmlspecialchars($edit_addr['pincode'] ?? '560001') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Address Type</label>
                            <select name="address_type" class="form-select">
                                <option value="Home" <?= ($edit_addr['address_type'] ?? '') === 'Home' ? 'selected' : '' ?>>Home (All day delivery)</option>
                                <option value="Work" <?= ($edit_addr['address_type'] ?? '') === 'Work' ? 'selected' : '' ?>>Work (10 AM - 6 PM)</option>
                                <option value="Other" <?= ($edit_addr['address_type'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-center mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="isDefaultCheck" <?= !empty($edit_addr['is_default']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="isDefaultCheck">
                                    Set as default address
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <?php if ($edit_addr): ?>
                        <a href="addresses.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-warning fw-bold btn-sm px-4">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
