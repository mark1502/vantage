<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class AdminController extends Controller
{
    public function menu()
    {
        return Inertia::render('AdminMenu');
    }
}
