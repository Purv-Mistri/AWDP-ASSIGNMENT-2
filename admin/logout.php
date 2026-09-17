<?php 
require_once '../config/database.php';
unset($_SESSION['admin']);
set_flash('info', 'Administrator logged out successfully.');
header('Location: ../admin-login.php');
exit;
?>