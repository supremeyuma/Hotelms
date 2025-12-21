<?php
// app/Models/CleaningLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CleaningLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['room_id', 'user_id', 'action'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
