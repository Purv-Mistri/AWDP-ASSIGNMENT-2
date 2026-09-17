<?php
require_once __DIR__ . '/../config/database.php';

if (!is_user_logged_in()) {
    set_flash('warning', 'Please sign in to your EveNeed customer account to continue.');
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
    $base = get_base_url();
    header("Location: {$base}user-login.php?redirect={$redirect}");
    exit;
}
?>