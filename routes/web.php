<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return response()->json('1.0.0');
});
