<?php
require __DIR__ . '/includes/bootstrap.php';
unset($_SESSION['customer_id']);
session_regenerate_id(true);
redirect('index.php');
