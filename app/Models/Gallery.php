<?php
// app/Models/Gallery.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    protected $fillable = ['category','image_path','caption','order','is_active'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        // Normalize paths that may or may not start with "public/"
        $path = str_starts_with($this->image_path, 'public/')
            ? str_replace('public/', '', $this->image_path)
            : $this->image_path;

        return asset('storage/' . $path);
    }
}
