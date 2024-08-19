<?php

namespace App\Http\Controllers;

use App\Helpers\Functions;
use App\Models\AreaCode;
use App\Models\Attachment;
use App\Models\Call;
use App\Models\Contact;
use App\Models\ContactPhone;
use App\Models\PhoneNumber;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Telnyx\Telnyx;
use Telnyx\Webhook as TelnyxWebhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;


class TelnyxWebhooksController extends Controller
{
    public function callControlWebhook(Request $request)
    {
        try {
            Telnyx::setApiKey(config('services.telnyx.api_key'));
            $webhookEvent = TelnyxWebhook::constructFromRequest(config('services.telnyx.public_key'));

            $user = User::first();
            if (Functions::is_empty($user)) {
                return false;
            }
           
            if ($request->exists('data')) {
                $webhookData = $request->all()['data'];
                $eventType = $webhookData['event_type'];

                // $data = $webhookData['data'];
                if (isset($webhookData['payload'])) {
                    $payload = $webhookData['payload'];
                }

                $call_control_id = null;
                $call = null;
                if (isset($payload) && isset($payload['call_control_id'])) {
                    $call_control_id = $payload['call_control_id'];
                    $call = Call::where('call_control_id', $call_control_id)->get()->first();
                }

                logger(['direction' => $payload['direction']]);
                switch ($eventType) {
                    case 'call.initiated':
                        $direction = $payload['direction'];
                        if ($direction == 'outgoing') {
                            $activeCall = $user->hasActiveCall();
                            $from = Functions::format_phone_number($payload['from']);
                            $to = Functions::format_phone_number($payload['to']);
                            $phone = PhoneNumber::where('phone_number', $to)->where('user_id', $user->id)->get()->first();

                            // $contact_phone = ContactPhone::where('phone', $from)->first();


                            $contact_phone = ContactPhone::where('phone', $from)->first();
                            $code = Functions::get_area_code_from_number($contact_phone->phone);
                            $area = AreaCode::where('code', $code)->first();
                            if (Functions::is_empty($area)) {
                                $timezone = 'America/New_York';
                            } else {
                                $timezone = $area->tzn;
                                if (Functions::is_empty($timezone)) {
                                    $timezone = 'America/New_York';
                                }
                            }
                            if (!empty($contact_phone)) {
                                $contact = Contact::where('contact_phone_id', $contact_phone->id)->where('user_id', $user->id)->first();

                                if (Functions::is_empty($contact_phone->timezone)) {
                                    $contact_phone->update([
                                        'timezone' => $timezone  // if contact_phone timezone is empty then update it
                                    ]);
                                }

                                if (Functions::is_empty($contact)) {
                                    $contactData = [
                                        'user_id' => $user->id,
                                        'first_name' => $from,
                                        'contact_phone_id' => $contact_phone->id
                                    ];
                                    $contact = new Contact($contactData);
                                    $contact->save();

                                    $user->update([
                                        'contacts_count' => $user->contacts_count + 1
                                    ]);

                                    $contactData['phone'] = $from;
                                    $contactData['id'] = $contact->id;
                                    unset($contactData['contact_phone_id']);

                                    $backupExists = DB::table('contacts_backup')->where('user_id', $user->id)->where('phone', $from)->exists();
                                    if (!$backupExists) {
                                        DB::table('contacts_backup')->insert($contactData);
                                    }
                                }
                            } else {
                                //addition
                                $contact_phone = new ContactPhone([
                                    'phone' => $from,
                                    'timezone' => $timezone
                                ]);
                                $contact_phone->save();

                                $contactData = [
                                    'user_id' => $user->id,
                                    'first_name' => $from,
                                    'contact_phone_id' => $contact_phone->id,
                                ];
                                $contact = new Contact($contactData);
                                $contact->save();

                                $user->update([
                                    'contacts_count' => $user->contacts_count + 1
                                ]);

                                $contactData['phone'] = $from;
                                $contactData['id'] = $contact->id;
                                unset($contactData['contact_phone_id']);

                                // $backupExists = DB::table('contacts_backup')->where('user_id', $user->id)->where('phone', $from)->exists();
                                // if (!$backupExists) {
                                //     DB::table('contacts_backup')->insert($contactData);
                                // }
                            }
                            $call = new Call([
                                'user_id' => $user->id,
                                'contact_id' => $contact->id,
                                'phone_number_id' => $phone->id,

                                'call_control_id' => $payload['call_control_id'],
                                'call_leg_id' => $payload['call_leg_id'],
                                'call_session_id' => $payload['call_session_id'],
                                'client_state' => $payload['client_state'],
                                'call_duration' => 0,

                                'status' => $activeCall ? 'missed' : 'receiving',
                                'direction' => 'inbound',
                                'call_start_time' => Carbon::now()->toDateTimeString(),
                                'last_outbound_call_activity' => Carbon::now()->toDateTimeString(),
                            ]);

                            $call->save();
                            logger(['call initiated' => $call]);
                            if ($activeCall) {
                                break;
                            }
                        }
                        if ($direction == 'outgoing') {
                            if (Functions::not_empty($call)) {
                                $call->client_state = $payload['client_state'];
                                $call->save();
                            }
                        }
                        break;
                    case 'call.answered':
                        if (Functions::not_empty($call)) {
                            $activeCall = $user->getActiveCall();

                            $call->client_state = $payload['client_state'];
                            $call->status = 'active';
                            $call->save();
                            $call->refresh();
                        }
                        break;
                    case 'streaming.started':
                        if (Functions::not_empty($call)) {
                            $activeCall = $user->getActiveCall();

                            $call->stream_id = $payload['stream_id'];
                            $call->save();
                            $call->refresh();
                        }
                        break;
                    case 'streaming.failed':
                    case 'streaming.stopped':
                        $activeCall = $user->getActiveCall();

                        $call->stream_id = null;
                        if ($eventType == 'streaming.failed') {
                            $call->status = 'disconnected';
                        }

                        $call->save();

                        if ($activeCall && $activeCall->call_control_id == $call->call_control_id) {
                            $data = app(TelnyxController::class)->terminateCall($user, $call);
                        }

                        break;
                    case 'call.hangup':
                        if (Functions::not_empty($call)) {
                            $activeCall = $user->getActiveCall();
                            $cause = $payload['hangup_cause'];
                            if ($cause == 'normal_clearing') {
                                if ($call->status != 'active' && $call->status != 'disconnected') {
                                    $call->status = 'declined';
                                } else if ($call->status == 'active') {
                                    $call->status = 'completed';
                                }
                            } else if ($cause == 'originator_cancel') {
                                if ($call->direction == 'outbound') {
                                    $call->status = 'declined';
                                } else {
                                    $call->status = 'missed';
                                }
                            } else if ($cause == 'user_busy') {
                                $call->status = 'not attended';
                            } else if ($cause == 'call_rejected') {
                                if ($call->status == 'active') {
                                    $call->status = 'completed';
                                    // Make Call Information API Request
                                } else if ($call->status != 'active' && $call->status != 'disconnected') {
                                    $call->status = 'declined';
                                }
                            } else {
                                $call->status = 'completed';
                            }

                            if (isset($payload['end_time'])) {
                                $call->call_end_time = Carbon::parse($payload['end_time'])->toDateTimeString();
                            } else {
                                $call->call_end_time = Carbon::now()->toDateTimeString();
                            }

                            $call->save();
                        }

                        break;
                    case 'call.cost':
                        if (isset($payload) && isset($payload['total_cost'])) {
                            $call->actual_cost = $payload['total_cost'];
                            $call->save();
                        }
                    case "call.recording.saved":
                        if (Functions::not_empty($call)) {
                            $filename = $call->recording_id;
                            if (Functions::not_empty($filename)) {
                                $filename = $filename . uniqid() . '.mp3';
                                $urls = $payload['recording_urls'];
                                $count = 1;
                                foreach ($urls as $type => $url) {
                                    if ($type == 'mp3') {
                                        $this->downloadAndSaveMp3($url, $filename, $call, $count);
                                        $count++;
                                    }
                                }
                                $call->recording_available = 1;
                                $call->save();
                            }
                        }
                        break;
                    default:
                        break;
                }

                logger("==========================================================================");
                logger("Telnyx Call Control Webhook Start");
                logger("==========================================================================");

                logger(json_encode($webhookData));

                logger("==========================================================================");
                logger("Telnyx Call Control Webhook End");
                logger("==========================================================================");
            }
        } catch (Exception $e) {
            logger($e->getMessage() . ' - ' . $e->getCode() . ' - ' . $e->getLine() . ' - ' . $e->getTraceAsString());
        }
    }

    public function downloadAndSaveMp3($url, $fileName, $call, $count)
    {
        $response = Http::get($url);

        if ($response->successful()) {
            $contents = $response->getBody()->getContents();
            $fileSize = strlen($contents);

            $path = storage_path('app/public/calls/' . $call->id . '/' . $fileName);

            $directory_path = storage_path('app/public/calls/' . $call->id);
            if (!File::exists($directory_path)) {
                File::makeDirectory($directory_path, 0755, true);
            }

            file_put_contents($path, $contents);

            $relative_path = '/storage/calls/' . $call->id . '/' . $fileName;

            $data['name'] = 'Call ' . $call->id . ' Recording - ' . $count;
            $data['disk_name'] = $fileName;
            $data['link'] = $relative_path;
            $data['extension'] = 'mp3';
            $data['mime'] = 'audio/mpeg';
            $data['size'] = $fileSize;
            $data['type'] = "audio";

            $attachment = new Attachment($data);
            $call->attachments()->save($attachment);
            $attachment->save();
        } else {
            return false;
        }

        return true;
    }
}
