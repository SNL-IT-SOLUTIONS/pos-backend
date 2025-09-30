<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    protected $table = 'receivings';
    protected $fillable = [
        'supplier_id',
        'expected_delivery_date',
        'order_notes',
        'total',
        'discount_total',
        'amount_due',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(ReceivingItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
