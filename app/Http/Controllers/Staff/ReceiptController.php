<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use Inertia\Inertia;

class ReceiptController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Receipts/Index', [
            'receipts' => Receipt::with('room','booking')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Receipt $receipt)
    {
        return Inertia::render('Admin/Receipts/Show', [
            'receipt' => $receipt->load('room','booking'),
        ]);
    }
}
