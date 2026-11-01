<?php
require __DIR__ . '/config.php';

if (current_user()) {
    log_activity((int) current_user()['id'], 'auth.signout', 'Signed out');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
session_start();
flash('success', 'You are signed out.');
redirect('index.php');

?>
