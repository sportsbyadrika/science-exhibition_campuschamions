<?php
require __DIR__ . '/includes/bootstrap.php';
captcha_output((string) ($_GET['k'] ?? 'default'));
