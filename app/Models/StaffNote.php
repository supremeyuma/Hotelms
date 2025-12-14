<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffNote extends Model
{
    use HasFactory;

    protected $fillable = ['staff_id','admin_id','type','message'];

    public function staff()
    {
        return $this->belongsTo(User::class,'staff_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class,'admin_id');
    }
}
