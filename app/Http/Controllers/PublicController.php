<?php
// app/Http/Controllers/PublicController.php
namespace App\Http\Controllers;

use App\Models\Gallery;
use Inertia\Inertia;

class PublicController extends Controller
{
    public function home()
    {
        
        return Inertia::render('Public/Home', [
            'gallery' => [
                'hero' => Gallery::where('category', 'home.hero')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),

                'beach' => Gallery::where('category', 'beach')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),

                'club' => Gallery::where('category', 'club')
                    ->where('is_active', true)
                    ->orderBy('order')
                    ->get(),
            ],
        ]);
    }

    public function gallery()
    {
        return Inertia::render('Public/Gallery', [
            'items' => Gallery::where('is_active', true)->get()->groupBy('category')
        ]);
    }

    public function amenities()
    {
        return Inertia::render('Public/Amenities');
    }

    public function club()
    {
        return Inertia::render('Public/ClubLounge');
    }

    public function policies()
    {
        return Inertia::render('Public/Policies');
    }
}
