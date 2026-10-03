<?php
require_once 'xeff/includes/config.php';
session_destroy();
header('Location: login.php');
exit;
?>
