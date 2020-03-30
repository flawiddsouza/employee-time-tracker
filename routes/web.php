<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if(Auth::check()) {
        return view('home');
    } else {
        return view('auth.login');
    }
});

Auth::routes();
