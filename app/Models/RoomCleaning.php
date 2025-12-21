<?php
// app/Models/RoomCleaning.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomCleaning extends Model
{
    protected $fillable = [
        'room_id',
        'staff_id',
        'status',
        'cleaned_at'
    ];

    protected $casts = [
        'cleaned_at' => 'datetime'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
