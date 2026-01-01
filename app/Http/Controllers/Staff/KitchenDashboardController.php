<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class KitchenDashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Staff/Kitchen/Dashboard');
    }
}
