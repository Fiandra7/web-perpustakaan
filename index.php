<?php
require __DIR__ . '/config.php';
redirect(empty($_SESSION['uid']) ? 'login.php' : 'dashboard.php');