<?php

use Illuminate\Support\Facades\Route;

// The pages are static files in /public; the backend is the JSON API in routes/api.php.
Route::redirect('/', '/index.html');
