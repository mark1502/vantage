<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function menu() {
        return Inertia::render('AdminMenu');
    }

    public function test_form() {
        return Inertia::render('Test_form');
    }
}
