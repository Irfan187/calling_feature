<?php

namespace App\Models;

use App\Helpers\Functions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneNumber extends Model
{
    use HasFactory, SoftDeletes;

    public $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'phone_number_order_id' => 'integer',
        'porting_order_id' => 'integer',
        'is_primary' => 'integer',
        'requirements_met' => 'integer',
        'sms' => 'integer',
        'mms' => 'integer',
        'voice' => 'integer',
        'emergency' => 'integer',
        'fax' => 'integer',
        'forwarding_status' => 'integer',
        'international' => 'integer',
        'sms_domestic_two_way' => 'integer',
        'sms_international_inbound' => 'integer',
        'sms_international_outbound' => 'integer',
        'mms_domestic_two_way' => 'integer',
        'mms_international_inbound' => 'integer',
        'mms_international_outbound' => 'integer',
        'message_count' => 'integer',
        'call_forwarding_enabled' => 'integer',
        'call_recording_enabled' => 'integer',
        'caller_id_name_enabled' => 'integer',
        'cnam_listing_enabled' => 'integer',
        'emergency_enabled' => 'integer',
        'accept_any_rtp_packets_enabled' => 'integer',
        'rtp_auto_adjust_enabled' => 'integer',
        't38_fax_gateway_enabled' => 'integer',
        'tech_prefix_enabled' => 'integer',
    ];

    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function calls()
    {
        return $this->hasMany(Call::class, 'phone_number_id');
    }

    public function is_number_registered () {
        if($this->campaign_mapping_error != null){
            return false;
        }
        if($this->campaign_status != 'linked'){
            return false;
        }
        $campaign = $this->campaign()->get()->first();
        if(Functions::not_empty($campaign)){
            return true;
        }
        return false;
    }

    public function is_toll_free_registered () {
        return false;
    }
}
