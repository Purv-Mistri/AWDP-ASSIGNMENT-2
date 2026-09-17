<?php 
require_once __DIR__ . '/../config/database.php';
$base_url = get_base_url();
$page_title = $page_title ?? 'Eveneed - Everything You Need, All in One Place';
$flash = get_flash();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Theme Stylesheet -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/css/style.css">
</head>
<body>
<?php if ($flash): ?>
<div class="container mt-3 flash-message-container">
    <div class="alert alert-<?= htmlspecialchars($flash['type'] ?? 'info') ?> alert-dismissible fade show shadow-sm d-flex align-items-center" role="alert">
        <?php 
        $icon = match($flash['type'] ?? 'info') {
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default => 'bi-info-circle-fill'
        };
        ?>
        <i class="bi <?= $icon ?> fs-5 me-2"></i>
        <div><?= htmlspecialchars($flash['message'] ?? '') ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>