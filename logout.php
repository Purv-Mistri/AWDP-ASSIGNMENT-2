<?php
require_once 'config/database.php';

if (isset($_SESSION['user'])) {
    unset($_SESSION['user']);
}
// Clear applied coupon and transient checkout data
unset($_SESSION['applied_coupon']);
unset($_SESSION['checkout_shipping']);
unset($_SESSION['checkout_billing']);

set_flash('info', 'You have been successfully logged out of EveNeed.');
header('Location: user-login.php');
exit;
?>