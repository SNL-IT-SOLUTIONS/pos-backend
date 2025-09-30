<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $table = 'suppliers';
    protected $fillable = [
        'company_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'zipcode',
        'payment_terms',
        'category_id',
        'certificates',
        'is_active',
    ];
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
