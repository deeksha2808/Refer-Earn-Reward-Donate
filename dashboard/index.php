<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
$user = require_login();
redirect(dashboard_path($user['role']));
