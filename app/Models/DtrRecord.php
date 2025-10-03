<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DtrRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'login_start_time',
        'login_end_time',
        'total_hours',
        'remarks',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
