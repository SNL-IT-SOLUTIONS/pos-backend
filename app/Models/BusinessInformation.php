<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessInformation extends Model
{
    protected $table = 'business_informations';

    protected $fillable = [
        'business_name',
        'address',
        'city',
        'zip_code',
        'phone_number',
        'email_address',
        'website',
        'tax_id_ein',
        'updated_by',
    ];
}
