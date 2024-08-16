<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactPhone extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'contact_phone';

    public function contacts(){
        return $this->hasMany(Contact::class,'contact_phone_id');
    }
}
