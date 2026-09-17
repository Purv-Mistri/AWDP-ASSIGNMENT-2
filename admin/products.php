<?php 
require_once '../includes/admin-auth.php';

// Handle Delete Product
if (isset($_GET['del'])) {
    $del_id = (int)$_GET['del'];
    $del_stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $del_stmt->execute([$del_id]);
    set_flash('success', 'Product deleted successfully.');
    header('Location: products.php');
    exit;
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 4.5);
    $image = trim($_POST['image'] ?? 'assets/images/default.jpg');
    $status = $_POST['status'] ?? 'Active';

    // Handle File Upload Attachment
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image_file']['tmp_name'];
        $fileName = $_FILES['image_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];

        if (in_array($ext, $allowed)) {
            $uploadDir = '../assets/images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $image = 'assets/images/uploads/' . $newFileName;
            }
        }
    }

    if (empty($image)) {
        $image = 'assets/images/default.jpg';
    }

    if ($stock <= 0 && $status === 'Active') {
        $status = 'Out of Stock';
    }

    if (!empty($name) && $category_id > 0 && $price > 0) {
        $ins = $pdo->prepare("INSERT INTO products (name, category_id, brand, description, price, discount, stock, rating, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$name, $category_id, $brand, $description, $price, $discount, $stock, $rating, $image, $status]);
        set_flash('success', "Product '{$name}' added to catalog successfully with attached image.");
    } else {
        set_flash('danger', 'Please provide a valid product name, category, and price.');
    }
    header('Location: products.php');
    exit;
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = (int)($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand = trim($_POST['brand'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 4.5);
    $image = trim($_POST['image'] ?? 'assets/images/default.jpg');
    $status = $_POST['status'] ?? 'Active';

    // Handle File Upload Attachment
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image_file']['tmp_name'];
        $fileName = $_FILES['image_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];

        if (in_array($ext, $allowed)) {
            $uploadDir = '../assets/images/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $newFileName = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                $image = 'assets/images/uploads/' . $newFileName;
            }
        }
    }

    if (empty($image)) {
        $image = 'assets/images/default.jpg';
    }

    if ($stock <= 0 && $status === 'Active') {
        $status = 'Out of Stock';
    }

    if ($id > 0 && !empty($name) && $category_id > 0 && $price > 0) {
        $up = $pdo->prepare("UPDATE products SET name=?, category_id=?, brand=?, description=?, price=?, discount=?, stock=?, rating=?, image=?, status=? WHERE id=?");
        $up->execute([$name, $category_id, $brand, $description, $price, $discount, $stock, $rating, $image, $status, $id]);
        set_flash('success', "Product #{$id} updated successfully.");
    } else {
        set_flash('danger', 'Failed to update product. Please check required fields.');
    }
    header('Location: products.php');
    exit;
}

// Fetch categories for dropdowns
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Product search and listing
$search = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');

$query = "SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)";
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($cat_filter > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}
if ($status_filter) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Edit product target
$edit_prod = null;
if (isset($_GET['edit_id'])) {
    $ep_id = (int)$_GET['edit_id'];
    $ep_stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $ep_stmt->execute([$ep_id]);
    $edit_prod = $ep_stmt->fetch();
}

$page_title = 'Manage Catalog Products - EveNeed Admin';
include '../includes/header.php';
include '../includes/admin-navbar.php';
?>

<div class="container-fluid px-lg-4 pb-5 pt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-box-seam me-2 text-warning"></i>Product Catalog & Stock Management</h2>
            <p class="text-muted mb-0">Create new products, modify prices, update stock levels, and control active/inactive catalog visibility</p>
        </div>
        <button class="btn btn-warning fw-bold btn-sm px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
            <i class="bi bi-plus-circle me-1"></i>Add New Product
        </button>
    </div>

    <!-- Filters Bar -->
    <div class="card p-3 shadow-sm border mb-4">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search product name, brand..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="cat" class="form-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $cat_filter === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="Out of Stock" <?= $status_filter === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                    <option value="Inactive" <?= $status_filter === 'Inactive' ? 'selected' : '' ?>>Inactive (Hidden)</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($search || $cat_filter || $status_filter): ?>
                    <a href="products.php" class="btn btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="card shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Product & Brand</th>
                        <th>Category</th>
                        <th>Price (MRP)</th>
                        <th>Discount</th>
                        <th>Final Price</th>
                        <th>Stock Qty</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No products found in catalog.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): 
                            $final_price = $p['price'] * (1 - ($p['discount'] / 100));
                        ?>
                            <tr>
                                <td><b>#<?= $p['id'] ?></b></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="../<?= htmlspecialchars($p['image']) ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;" alt="">
                                        <div>
                                            <span class="fw-bold text-dark d-block text-truncate" style="max-width: 240px;"><?= htmlspecialchars($p['name']) ?></span>
                                            <small class="text-muted">Brand: <b><?= htmlspecialchars($p['brand']) ?></b> · ★ <?= $p['rating'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['cat_name']) ?></span></td>
                                <td><?= format_price($p['price']) ?></td>
                                <td>
                                    <?php if ($p['discount'] > 0): ?>
                                        <span class="badge bg-danger"><?= (int)$p['discount'] ?>% OFF</span>
                                    <?php else: ?>
                                        <span class="text-muted small">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-success"><?= format_price($final_price) ?></td>
                                <td>
                                    <?php if ($p['stock'] <= 0): ?>
                                        <span class="badge bg-danger">0 (Out of Stock)</span>
                                    <?php elseif ($p['stock'] <= 5): ?>
                                        <span class="badge bg-warning text-dark fw-bold"><?= $p['stock'] ?> (Low Stock)</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success"><?= $p['stock'] ?> units</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'Active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($p['status'] === 'Out of Stock'): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive (Hidden)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="../product-details.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline-info btn-sm" title="Preview on public site">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="products.php?edit_id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm" title="Edit Product">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="products.php?del=<?= $p['id'] ?>" class="btn btn-outline-danger btn-sm" title="Delete Product" onclick="return confirm('Are you sure you want to permanently delete this product?');">
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="addProductModalLabel"><i class="bi bi-plus-circle me-2 text-warning"></i>Add New Product to Catalog</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="products.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Sony WH-1000XM5 Wireless Headphones" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Brand / Manufacturer <span class="text-danger">*</span></label>
                            <input type="text" name="brand" class="form-control" placeholder="e.g. Sony / Apple / Nike" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold"><i class="bi bi-paperclip me-1 text-primary"></i>Attach Product Image (Upload)</label>
                            <input type="file" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/gif" onchange="previewProductImage(this, 'add_preview_box', 'add_preview_img')">
                            <small class="text-muted">Supports JPG, PNG, WEBP, SVG</small>
                            <div id="add_preview_box" class="mt-2 text-center p-2 bg-light rounded border d-none">
                                <span class="small text-muted d-block mb-1">Image Preview:</span>
                                <img id="add_preview_img" src="" class="img-fluid rounded" style="max-height: 100px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Or Image Asset Path (Fallback)</label>
                            <input type="text" name="image" class="form-control" placeholder="assets/images/default.jpg" value="assets/images/default.jpg">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Price (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Discount (%)</label>
                            <input type="number" step="0.01" name="discount" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" value="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Catalog Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active (Visible on public store)</option>
                                <option value="Out of Stock">Out of Stock</option>
                                <option value="Inactive">Inactive (Hidden from public store)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Initial Rating</label>
                            <input type="number" step="0.1" min="1.0" max="5.0" name="rating" class="form-control" value="4.5">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Full Description & Specifications <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Enter detailed product description..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-warning fw-bold btn-sm px-4">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<?php if ($edit_prod): ?>
<div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2 text-primary"></i>Edit Product #<?= $edit_prod['id'] ?></h5>
                <a href="products.php" class="btn-close"></a>
            </div>
            <form method="post" action="products.php" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?= $edit_prod['id'] ?>">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_prod['name']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $edit_prod['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Brand / Manufacturer <span class="text-danger">*</span></label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($edit_prod['brand']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold"><i class="bi bi-paperclip me-1 text-primary"></i>Attach New Product Image (Upload)</label>
                            <input type="file" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/svg+xml,image/gif" onchange="previewProductImage(this, 'edit_preview_box', 'edit_preview_img')">
                            <small class="text-muted">Choose a new file to replace current image</small>
                            <div id="edit_preview_box" class="mt-2 text-center p-2 bg-light rounded border d-none">
                                <span class="small text-muted d-block mb-1">New Image Preview:</span>
                                <img id="edit_preview_img" src="" class="img-fluid rounded" style="max-height: 100px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Current Attached Image</label>
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border">
                                <img src="../<?= htmlspecialchars($edit_prod['image']) ?>" onerror="this.src='../assets/images/default.jpg'" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;">
                                <input type="text" name="image" class="form-control form-control-sm" value="<?= htmlspecialchars($edit_prod['image']) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Original Price (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" value="<?= $edit_prod['price'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Discount (%)</label>
                            <input type="number" step="0.01" name="discount" class="form-control" value="<?= $edit_prod['discount'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Stock Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" value="<?= $edit_prod['stock'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Catalog Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?= $edit_prod['status'] === 'Active' ? 'selected' : '' ?>>Active (Visible on public store)</option>
                                <option value="Out of Stock" <?= $edit_prod['status'] === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
                                <option value="Inactive" <?= $edit_prod['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive (Hidden from public store)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Customer Rating</label>
                            <input type="number" step="0.1" min="1.0" max="5.0" name="rating" class="form-control" value="<?= $edit_prod['rating'] ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Description & Specifications</label>
                            <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($edit_prod['description']) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <a href="products.php" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" name="edit_product" class="btn btn-primary btn-sm fw-bold px-4">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function previewProductImage(input, boxId, imgId) {
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