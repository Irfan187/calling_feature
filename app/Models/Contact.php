<?php

namespace App\Models;

use App\Helpers\Functions;
use App\Http\Controllers\AttachmentController;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Contact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'archived' => 'integer',
        'responded' => 'integer',
        'has_readable_msg' => 'integer',
        'message_sent_via_workflow' => 'integer',
        'check_drip_message' => 'integer',
        'landline_remover_id' => 'integer',
    ];

    // protected $appends = ['unread', 'last_message', 'last_message_time'];
    protected $appends = [];

    public static $withoutAppends = false;

    public $recentMessage = null;
    public $recentMessageFetched = false;

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($object) {
            $attachments = $object->attachments;
            foreach ($attachments as $attachment) {
                app(AttachmentController::class)->destroy($object->id, 'Contact', $attachment);
            }
        });
    }

    // public function getUnreadAttribute()
    // {
    //     $unread_count = $this->messages()->where('read', 0)->count();
    //     if ($unread_count > 0)
    //     {
    //         return $unread_count;
    //     }
    //     return 0;
    // }


    // public function getLastMessageAttribute()
    // {
    //     $message_id = $this->messages()->select('contact_id', DB::raw('MAX(messages.id) as max_id'))->groupBy('contact_id')->get()->pluck('max_id')->first();
    //     $message = Message::where('id', $message_id)->withCount('attachments')->first();

    //     $this->recentMessage = $message;
    //     $this->recentMessageFetched = true;

    //     // $message = $this->messages()->withCount('attachments')->orderBy('created_at', 'desc')->first();

    //     if (Functions::not_empty($message))
    //     {
    //         if ($message->type == 'mms')
    //         {
    //             $media_count = $message->attachments_count;
    //             $text = $media_count . ' Media Files';
    //         }
    //         else
    //         {
    //             $text = Str::limit($message->text, 14, '...');
    //         }
    //         return $text;
    //     }
    //     return '';
    // }

    // public function getLastMessageTimeAttribute()
    // {
    //     while(!$this->recentMessageFetched){
    //         continue;
    //     }

    //     $message_time = $this->recent_msg_time;
    //     if (Functions::not_empty($message_time))
    //     {
    //         // $message_time = Carbon::parse($message_time)->toDateTimeLocalString();
    //         return $message_time;
    //     }
    //     else
    //     {
    //         // $message = $this->messages()->withCount('attachments')->orderBy('created_at', 'desc')->first();
    //         if (Functions::not_empty($this->recentMessage))
    //         {
    //             // $message_time = Carbon::parse($message->created_at)->toDateTimeLocalString();
    //             return $this->recentMessage->created_at;
    //         }
    //     }

    //     return '';
    // }

    public function scopeWithoutAppends($query)
    {
        self::$withoutAppends = true;
        return $query;
    }

    protected function getArrayableAppends()
    {
        if (self::$withoutAppends) {
            return [];
        }
        return parent::getArrayableAppends();
    }

    // ----------------------
    // Relations
    // ----------------------

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }


    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function calls()
    {
        return $this->hasMany(Call::class, 'contact_id');
    }

    // public function workflows() // new
    // {
    //     return $this->belongsToMany(Workflow::class)->withTimestamps()->withPivot(['id', 'status', 'step', 'last_process_time', 'waiting_list', 'under_process', 'tries']);
    // }

    //Contact_phone relation functions
    public function phone()
    {
        return $this->contact_phone->phone;
    }
    function timezone()
    {
        return $this->contact_phone->timezone;
    }
    public function LRN()
    {
        return $this->contact_phone->LRN;
    }
    public function OCN()
    {
        return $this->contact_phone->OCN;
    }
    public function line_type()
    {
        return $this->contact_phone->line_type;
    }
    public function spid_carrier_name()
    {
        return $this->contact_phone->spid_carrier_name;
    }
    public function spid_city()
    {
        return $this->contact_phone->spid_city;
    }
    public function spid_state()
    {
        return $this->contact_phone->spid_state;
    }

    
    function first_name()
    {
        return $this->first_name;
    }
    function last_name()
    {
        return $this->last_name;
    }
    function email()
    {
        return $this->email;
    }

    //Contact_meta relation functions
    function notes()
    {
        return $this->contact_meta->notes;
    }
    function address()
    {
        return $this->contact_meta->address;
    }
    function city()
    {
        return $this->contact_meta->city;
    }
    function state()
    {
        return $this->contact_meta->state;
    }
    function country()
    {
        return $this->contact_meta->country;
    }
    function postal_code()
    {
        return $this->contact_meta->postal_code;
    }
    function gender()
    {
        return $this->contact_meta->gender;
    }
    function date_of_birth()
    {
        return $this->contact_meta->date_of_birth;
    }
    function type()
    {
        return $this->contact_meta->type;
    }
    function company()
    {
        return $this->contact_meta->company;
    }
    function designation()
    {
        return $this->contact_meta->designation;
    }
    function message_sent_via_workflow()
    {
        return $this->contact_meta->message_sent_via_workflow;
    }
    function county()
    {
        return $this->contact_meta->county;
    }
}
