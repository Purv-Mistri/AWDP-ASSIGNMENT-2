<?php 
require_once '../includes/admin-auth.php';

// Handle Delete Category
if (isset($_GET['del'])) {
    $del_id = (int)$_GET['del'];

    // Requirement: Check if products belong to this category before deleting
    $p_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $p_check->execute([$del_id]);
    $prod_count = (int)$p_check->fetchColumn();

    if ($prod_count > 0 && !isset($_GET['force'])) {
        set_flash('danger', "Cannot delete category: {$prod_count} products belong to this category. Please reassign or delete the products first, or use force delete.");
    } else {
        $del_stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $del_stmt->execute([$del_id]);
        set_flash('success', 'Category deleted successfully.');
    }
    header('Location: categories.php');
    exit;
}

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-tag-fill');
    $image = trim($_POST['image'] ?? 'assets/images/default.jpg');
    $status = $_POST['status'] ?? 'Active';

    // Handle File Upload
    if (isset($_FILES['cat_image_file']) && $_FILES['cat_image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cat_image_file']['tmp_name'];
        $fileName = $_FILES['cat_image_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];

        if (in_array($ext, $allowed)) {
            $uploadDir = '../assets/images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'cat_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $image = 'assets/images/uploads/' . $newFileName;
            }
        }
    }

    if (!empty($name)) {
        // Check name uniqueness
        $chk = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            set_flash('danger', "A category named '{$name}' already exists.");
        } else {
            $ins = $pdo->prepare("INSERT INTO categories (name, description, icon, image, status) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$name, $description, $icon, $image, $status]);
            set_flash('success', "Category '{$name}' created successfully.");
        }
    } else {
        set_flash('danger', 'Category name is required.');
    }
    header('Location: categories.php');
    exit;
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'bi-tag-fill');
    $image = trim($_POST['image'] ?? 'assets/images/default.jpg');
    $status = $_POST['status'] ?? 'Active';

    // Handle File Upload
    if (isset($_FILES['cat_image_file']) && $_FILES['cat_image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['cat_image_file']['tmp_name'];
        $fileName = $_FILES['cat_image_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];

        if (in_array($ext, $allowed)) {
            $uploadDir = '../assets/images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'cat_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $image = 'assets/images/uploads/' . $newFileName;
            }
        }
    }

    if ($id > 0 && !empty($name)) {
        $up = $pdo->prepare("UPDATE categories SET name=?, description=?, icon=?, image=?, status=? WHERE id=?");
        $up->execute([$name, $description, $icon, $image, $status, $id]);
        set_flash('success', "Category #{$id} updated successfully.");
    } else {
        set_flash('danger', 'Category name is required.');
    }
    header('Location: categories.php');
    exit;
}

// Fetch all categories with product counts
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll();

// Edit modal data
$edit_cat = null;
if (isset($_GET['edit_id'])) {
    $ec_id = (int)$_GET['edit_id'];
    $ec_stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $ec_stmt->execute([$ec_id]);
    $edit_cat = $ec_stmt->fetch();
}

$page_title = 'Manage Categories - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-grid me-2 text-warning"></i>Category Management</h2>
            <p class="text-muted mb-0">Organize store departments, manage category visuals, and control storefront availability</p>
        </div>
        <button class="btn btn-warning fw-bold btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Category
        </button>
    </div>

    <!-- Categories Table -->
    <div class="card shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Category Visual & Name</th>
                        <th>Description</th>
                        <th>Bootstrap Icon</th>
                        <th>Active Products</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No categories created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><b>#<?= $c['id'] ?></b></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../<?= htmlspecialchars($c['image']) ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;" alt="">
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($c['name']) ?></span>
                                            <small class="text-muted">Slug: <?= strtolower(str_replace(' ', '-', $c['name'])) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-secondary text-truncate d-block" style="max-width: 320px;">
                                        <?= htmlspecialchars($c['description'] ?? 'No description provided') ?>
                                    </small>
                                </td>
                                <td>
                                    <code><i class="bi <?= htmlspecialchars($c['icon'] ?? 'bi-tag') ?> me-1"></i><?= htmlspecialchars($c['icon'] ?? 'bi-tag') ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $c['product_count'] ?> Products</span>
                                </td>
                                <td>
                                    <?php if ($c['status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="../products.php?cat=<?= $c['id'] ?>" target="_blank" class="btn btn-outline-info btn-sm" title="View category on store">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="categories.php?edit_id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit Category">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="categories.php?del=<?= $c['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete Category" onclick="return confirm('Delete this category? (If products exist in this category, deletion will be blocked)');">
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addCategoryModalLabel"><i class="bi bi-plus-circle me-2 text-warning"></i>Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="categories.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Smart Watches" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief summary of items in this department..."></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bootstrap Icon</label>
                            <input type="text" name="icon" class="form-control" value="bi-tag-fill" placeholder="bi-phone-fill">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold"><i class="bi bi-paperclip me-1 text-primary"></i>Attach Category Image (Upload)</label>
                        <input type="file" name="cat_image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/gif" onchange="previewCatImage(this, 'add_cat_prev_box', 'add_cat_prev_img')">
                        <div id="add_cat_prev_box" class="mt-2 text-center p-2 bg-light rounded border d-none">
                            <img id="add_cat_prev_img" src="" class="img-fluid rounded" style="max-height: 80px; object-fit: contain;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Or Image Asset Path (Fallback)</label>
                        <input type="text" name="image" class="form-control" value="assets/images/default.jpg" placeholder="assets/images/cat_electronics.svg">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_category" class="btn btn-warning fw-bold btn-sm px-4">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<?php if ($edit_cat): ?>
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Category #<?= $edit_cat['id'] ?></h5>
                <a href="categories.php" class="btn-close"></a>
            </div>
            <form method="post" action="categories.php" enctype="multipart/form-data">
                <input type="hidden" name="category_id" value="<?= $edit_cat['id'] ?>">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_cat['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_cat['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Bootstrap Icon</label>
                            <input type="text" name="icon" class="form-control" value="<?= htmlspecialchars($edit_cat['icon'] ?? 'bi-tag-fill') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $edit_cat['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $edit_cat['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold"><i class="bi bi-paperclip me-1 text-primary"></i>Attach New Category Image (Upload)</label>
                        <input type="file" name="cat_image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/gif" onchange="previewCatImage(this, 'edit_cat_prev_box', 'edit_cat_prev_img')">
                        <div id="edit_cat_prev_box" class="mt-2 text-center p-2 bg-light rounded border d-none">
                            <img id="edit_cat_prev_img" src="" class="img-fluid rounded" style="max-height: 80px; object-fit: contain;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current Attached Image</label>
                        <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border">
                            <img src="../<?= htmlspecialchars($edit_cat['image']) ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded border" style="width: 45px; height: 45px; object-fit: cover;">
                            <input type="text" name="image" class="form-control form-control-sm" value="<?= htmlspecialchars($edit_cat['image'] ?? 'assets/images/default.jpg') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="categories.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" name="edit_category" class="btn btn-primary btn-sm fw-bold px-4">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function previewCatImage(input, boxId, imgId) {
    const box = document.getElementById(boxId);
    const img = document.getElementById(imgId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            box.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
