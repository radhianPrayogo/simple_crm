<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['Hello there, how are you?']);
});
