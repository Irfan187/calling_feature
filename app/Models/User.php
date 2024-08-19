<?php

namespace App\Models;

use App\Http\Controllers\AttachmentController;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'email_verified_at', 'status', 'provider', 'provider_id', 'profile_photo', 'phone', 'country', 'state', 'city', 'address', 'postal_code', 'timezone', 'birthdate', 'uuid', 'has_new_message', 'social_token', 'balance', 'brand_id', 'loggedin_user', 'loggedin_user_name', 'loggedin_user_email', 'special_package_visibility', 'attempting_auto_reload', 'negative_balance_error'];

    protected $hidden = ['password', 'remember_token', 'telnyx_webrtc_id', 'telnyx_webrtc_ondemand_id', 'telnyx_webrtc_token_expiry'];

    protected $casts = ['email_verified_at' => 'datetime', 'id' => 'integer', 'has_new_message' => 'integer', 'balance' => 'float', 'attempting_auto_reload' => 'integer', 'brand_id' => 'integer'];


    protected $appends = ['profile_photo_url', 'type', 'subscription'];

    protected static function booted()
    {
        static::creating(fn ($user) => $user->uuid = Str::uuid()->toString());
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($object)
        {
            $attachments = $object->attachments;
            foreach ($attachments as $attachment)
            {
                app(AttachmentController::class)->destroy($object->id, 'User', $attachment);
            }
        });
    }


    // ----------------------
    // Methods
    // ----------------------


    public function getName()
    {
        return $this->first_name . " " . $this->last_name;
    }

    

    public function getTypeAttribute()
    {
        return $this->getRoleNames()->first();
    }

    public function getSubscriptionAttribute()
    {
        return $this->subscription_package();
    }

    
    public function hasActiveCall()
    {
        $callsCount = $this->calls()->where(function ($q)
        {
            $q->where('calls.status', 'receiving')->orWhere('calls.status', 'dialing')->orWhere('calls.status', 'active');
        })->count();
        return $callsCount > 0;
    }

    public function getActiveCall()
    {
        $call = $this->calls()->where(function ($q)
        {
            $q->where('calls.status', 'receiving')->orWhere('calls.status', 'dialing')->orWhere('calls.status', 'active');
        })->get()->first();
        return $call;
    }

    // ----------------------
    // Relations
    // ----------------------

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'user_id');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function subscription_package()
    {
        return $this->settings()->with('subscription_package')->get()->pluck('subscription_package')->flatten()->first();
    }


    // public function campaigns()
    // {
    //     return $this->hasManyThrough(Campaign::class, Brand::class);
    // }

    public function phone_numbers()
    {
        return $this->hasMany(PhoneNumber::class, 'user_id');
    }


    public function calls()
    {
        return $this->hasMany(Call::class, 'user_id');
    }
}
