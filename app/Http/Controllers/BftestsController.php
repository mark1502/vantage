<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BftestsController extends Controller
{
    public function index()
    {
        return inertia('Bftests/one');
    }
}
