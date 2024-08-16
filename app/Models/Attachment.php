<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'attachable_id' => 'integer',
        'size' => 'integer'
    ];

    public function attachable(){
        return $this->morphTo();
    }
}
