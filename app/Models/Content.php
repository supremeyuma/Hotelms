<?php
// app/Models/Content.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $fillable = ['key','value','type','updated_by'];
}
