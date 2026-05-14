<?php
require_once 'includes/config.php';
if (!isLoggedIn()) redirect('login.php');
redirect_by_role($_SESSION['role']);