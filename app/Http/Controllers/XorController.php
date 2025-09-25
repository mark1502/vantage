<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class XorController extends Controller
{
    public function index()
    {
        return inertia('Bf/xor2');
    }
}
