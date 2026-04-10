<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffThread extends Model
{
    use HasFactory;

    protected $fillable = ['staff_id','admin_id','type','title'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class,'staff_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class,'admin_id');
    }

    public function messages()
    {
        return $this->hasMany(StaffThreadMessage::class,'thread_id')->orderBy('created_at','asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(StaffThreadMessage::class, 'thread_id')->latestOfMany();
    }
}
