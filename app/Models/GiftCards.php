<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCards extends Model
{
    use HasFactory;

    protected $table = 'gift_cards';

    protected $fillable = [
        'card_id',
        'gift_card_number',
        'gift_card_name',
        'description',
        'value',
        'balance',
        'expiration_date',
        'customer_id',
        'is_active',
        'is_archived',
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function card()
    {
        return $this->belongsTo(Card::class, 'card_id');
    }
}
