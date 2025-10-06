<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sales extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'total_amount',
        'gift_card_id',
        'discount',
        'net_amount',
        'payment_type',
        'amount_paid',
        'change',
        'status',
        'held_by'
    ];

    // Each sale has many sale items
    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    // Optional: link to customer
    public function customer()
    {
        return $this->belongsTo(Customers::class);
    }
}
