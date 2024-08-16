<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMeta extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'contact_meta';

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
