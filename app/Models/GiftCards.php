<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCards extends Model
{
    use HasFactory;

    protected $table = 'gift_cards';

    protected $fillable = [
        'gift_card_name',
        'description',
        'value',
        'customer_id',
        'is_active',
    ];
}
