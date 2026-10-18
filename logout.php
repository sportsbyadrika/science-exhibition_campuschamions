<?php
require __DIR__ . '/includes/bootstrap.php';

if (hash_equals(csrf_token(), (string) ($_GET['t'] ?? ''))) {
    logout();
    flash('success', 'You have been signed out.');
}
redirect('');
