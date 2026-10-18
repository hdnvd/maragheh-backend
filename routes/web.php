<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Maragheh API',
    'version' => 'v1',
]));
