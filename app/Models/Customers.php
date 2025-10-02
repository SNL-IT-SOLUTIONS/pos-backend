<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customers extends Model
{
    use HasFactory;
    protected $table = 'customers';
    protected $fillable = [
        'profile_picture',
        'customer_number',
        'total_spent',
        'first_name',
        'last_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'comments',
        'is_archived'
    ];

    public function giftCards()
    {
        return $this->hasMany(GiftCards::class, 'customer_id')
            ->where('is_active', 1) // only active
            ->where('is_archived', 0); // not archived
    }
}
