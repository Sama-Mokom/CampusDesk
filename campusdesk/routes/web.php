<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Authentication routes live in routes/auth.php and are registered once, from
// routes/api.php. Do not require them here as well: a second registration adds
// CSRF-protected duplicates of /login, /register and /logout, and the repeated
// route names make `php artisan route:cache` fail.
