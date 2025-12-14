<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffThreadMessage extends Model
{
    use HasFactory;

    protected $fillable = ['thread_id','sender_id','message','attachments'];

    protected $casts = ['attachments' => 'array'];

    public function thread()
    {
        return $this->belongsTo(StaffThread::class,'thread_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class,'sender_id');
    }
}
