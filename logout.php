<?php
declare(strict_types=1);

require __DIR__ . '/inc/layout.php';

use UMP\Core\Auth;

admin_post(); // logout is POST + CSRF so a stray <img src="logout"> can't sign users out
Auth::logout();
session_start();
flash('success', 'You have been signed out.');
redirect(admin_url('login'));
