<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BxorController extends Controller
{
    public function index()
    {
        return inertia('Bxor/index');
    }
}
