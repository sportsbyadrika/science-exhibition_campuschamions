<?php
require __DIR__ . '/includes/bootstrap.php';

if (hash_equals(csrf_token(), (string) ($_GET['t'] ?? ''))) {
    logout();
    session_start();
    flash('success', 'You have been signed out.');
}
redirect('');
