<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    use HasFactory;

    public $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'message_id' => 'integer',
        'contact_id' => 'integer',
        'phone_number_id' => 'integer',
        'record' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function phone_number()
    {
        return $this->belongsTo(PhoneNumber::class, 'phone_number_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
