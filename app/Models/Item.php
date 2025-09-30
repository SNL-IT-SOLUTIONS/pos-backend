<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;
    protected $table = 'items';

    protected $fillable = [
        'item_name',
        'description',
        'category_id',
        'supplier_id',
        'cost',
        'price',
        'stock',
        'min_stock',
        'barcode',
        'is_active',
    ];
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    // Accessor for margin if not using generated column in DB
    public function getMarginAttribute()
    {
        return $this->price - $this->cost;
    }
}
