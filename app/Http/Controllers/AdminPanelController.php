<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;

class AdminPanelController extends Controller
{
    public function getUsers()
    {
        return User::orderBy('name')->select('id', 'name')->get();
    }
}
