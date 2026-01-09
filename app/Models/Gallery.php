<?php
// app/Models/Gallery.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    protected $fillable = ['category','image_path','caption','order','is_active'];
}
