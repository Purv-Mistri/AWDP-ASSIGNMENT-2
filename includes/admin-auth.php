<?php
require_once __DIR__ . '/../config/database.php';

if (!is_admin_logged_in()) {
    set_flash('danger', 'Unauthorized administrative access. Please log in with admin credentials.');
    $base = get_base_url();
    header("Location: {$base}admin-login.php");
    exit;
}
?>