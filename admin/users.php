<?php 
require_once '../includes/admin-auth.php';

// Handle Unblock / Approve
if (isset($_GET['unblock'])) {
    $id = (int)$_GET['unblock'];
    $pdo->prepare("UPDATE users SET status = 'Active' WHERE id = ?")->execute([$id]);
    set_flash('success', "User account #{$id} has been Approved and Unblocked successfully.");
    header('Location: users.php');
    exit;
}

// Handle Block
if (isset($_GET['block'])) {
    $id = (int)$_GET['block'];
    $pdo->prepare("UPDATE users SET status = 'Blocked' WHERE id = ?")->execute([$id]);
    set_flash('warning', "User account #{$id} has been Blocked. This user cannot sign in until approved.");
    header('Location: users.php');
    exit;
}

// Handle Delete User
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    set_flash('success', 'User account permanently deleted.');
    header('Location: users.php');
    exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $status = $_POST['status'] ?? 'Blocked';
    $address = trim($_POST['address'] ?? '');

    if ($id > 0 && !empty($name) && !empty($mobile)) {
        $up = $pdo->prepare("UPDATE users SET name = ?, mobile = ?, status = ?, address = ? WHERE id = ?");
        $up->execute([$name, $mobile, $status, $address, $id]);
        set_flash('success', "User #{$id} updated successfully.");
    } else {
        set_flash('danger', 'Failed to update user. Please fill required fields.');
    }
    header('Location: users.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$query = "
    SELECT u.*, 
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(o.total_amount), 0) AS total_spend
    FROM users u 
    LEFT JOIN orders o ON o.user_id = u.id AND o.order_status != 'Cancelled'
    WHERE 1=1
";
$params = [];

if ($search) {
    $query .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($status_filter) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

$query .= " GROUP BY u.id ORDER BY u.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Edit modal target
$edit_user_data = null;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $e_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $e_stmt->execute([$eid]);
    $edit_user_data = $e_stmt->fetch();
}

$page_title = 'Manage Customer Accounts - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-people me-2 text-warning"></i>Customer Accounts & Approvals</h2>
            <p class="text-muted mb-0">Approve newly registered accounts (Default Status: Blocked), toggle user status, or inspect order histories</p>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card p-3 shadow-sm border mb-4">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, username, email, mobile..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Account Statuses</option>
                    <option value="Blocked" <?= $status_filter === 'Blocked' ? 'selected' : '' ?>>Blocked / Pending Approval Only</option>
                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active / Approved Accounts Only</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($search || $status_filter): ?>
                    <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>User Profile</th>
                        <th>Contact Details</th>
                        <th>Location</th>
                        <th>Orders / Spend</th>
                        <th>Account Status</th>
                        <th>Joined Date</th>
                        <th class="text-end" style="min-width: 220px;">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No customer accounts match the filter criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): 
                            $is_blocked = ($u['status'] === 'Blocked');
                        ?>
                            <tr class="<?= $is_blocked ? 'table-danger-subtle' : '' ?>">
                                <td><b>#<?= $u['id'] ?></b></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-dark text-warning rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px;">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($u['name']) ?></span>
                                            <small class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="bi bi-envelope me-1 text-secondary"></i><?= htmlspecialchars($u['email']) ?></div>
                                        <div><i class="bi bi-telephone me-1 text-secondary"></i><?= htmlspecialchars($u['mobile']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-secondary text-truncate d-block" style="max-width: 180px;" title="<?= htmlspecialchars($u['address'] ?? 'No address saved') ?>">
                                        <?= htmlspecialchars($u['address'] ?? 'N/A') ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $u['order_count'] ?> Orders</span>
                                    <div class="small fw-bold text-success mt-1"><?= format_price($u['total_spend']) ?></div>
                                </td>
                                <td>
                                    <?php if ($is_blocked): ?>
                                        <span class="badge bg-danger px-3 py-2">
                                            <i class="bi bi-lock-fill me-1"></i>Blocked (Pending)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success px-3 py-2">
                                            <i class="bi bi-check-circle-fill me-1"></i>Active / Approved
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <?php if ($is_blocked): ?>
                                            <!-- Approve / Unblock Action -->
                                            <a href="users.php?unblock=<?= $u['id'] ?>" class="btn btn-success btn-sm fw-bold px-3 shadow-sm" title="Approve & Unblock this User">
                                                <i class="bi bi-unlock-fill me-1"></i>Approve / Unblock
                                            </a>
                                        <?php else: ?>
                                            <!-- Block Action -->
                                            <a href="users.php?block=<?= $u['id'] ?>" class="btn btn-outline-warning btn-sm text-dark fw-semibold" title="Block this User" onclick="return confirm('Block this user? They will not be able to log in.');">
                                                <i class="bi bi-lock-fill me-1"></i>Block
                                            </a>
                                        <?php endif; ?>

                                        <a href="users.php?edit_id=<?= $u['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Edit Profile">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a href="users.php?del=<?= $u['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete User" onclick="return confirm('Permanently delete this user account and associated records?');">
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

<!-- Edit User Modal -->
<?php if ($edit_user_data): ?>
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Edit Customer #<?= $edit_user_data['id'] ?></h5>
                <a href="users.php" class="btn-close"></a>
            </div>
            <form method="post" action="users.php">
                <input type="hidden" name="user_id" value="<?= $edit_user_data['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_user_data['name']) ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Username</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($edit_user_data['username']) ?>" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Email</label>
                            <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($edit_user_data['email']) ?>" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Mobile Number</label>
                        <input type="tel" name="mobile" maxlength="10" class="form-control" value="<?= htmlspecialchars($edit_user_data['mobile']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= $edit_user_data['status'] === 'Active' ? 'selected' : '' ?>>Active (Can Login & Shop)</option>
                            <option value="Blocked" <?= $edit_user_data['status'] === 'Blocked' ? 'selected' : '' ?>>Blocked (Denied Login Access)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Delivery Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($edit_user_data['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="users.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" name="edit_user" class="btn btn-primary btn-sm fw-bold px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>