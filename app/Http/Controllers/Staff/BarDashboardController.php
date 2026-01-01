<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class BarDashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Staff/Bar/Dashboard');
    }
}
