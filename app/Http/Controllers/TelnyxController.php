<?php

namespace App\Http\Controllers;

use App\Helpers\Functions;
use Illuminate\Http\Request;
use App\Services\TelnyxService;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TelnyxController extends Controller
{
    protected $telnyxService;
    protected $client;



    public function __construct(TelnyxService $telnyxService)
    {
        $this->telnyxService = $telnyxService;

        $this->client = new Client([
            'base_uri' => 'https://api.telnyx.com/v2/',
            'headers' => [
                'Authorization' => 'Bearer ' . env('TELNYX_API_KEY'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function makeCall(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|string',
            'from' => 'required|string',
        ]);
        $result = $this->telnyxService->makeCall($validated['to'], $validated['from']);
        return response()->json($result);
    }



    /**  Call Recording Feature Start */
    public function startCallRecording(Request $request)
    {
        $to = $request->to;
        $from = $request->from;
        $call_control_id = $request->call_control_id;
        $response = $this->client->post('https://api.telnyx.com/v2/calls/' . $call_control_id . '/actions/record_start', [
            'json' => [
                'channels' => 'single',
                'format' => 'mp3'
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function endCallRecording(Request $request)
    {
        $to = $request->to;
        $from = $request->from;
        $call_control_id = $request->call_control_id;
        $response = $this->client->post('https://api.telnyx.com/v2/calls/' . $call_control_id . '/actions/record_stop');

        return json_decode($response->getBody(), true);
    }

    /**  Call Recording Feature End */

    public function terminateCall($user, $call)
    {
        $uuid = Str::uuid()->toString();
        $payload = [
            "client_state" => $call->client_state ? $call->client_state : base64_encode($user->id . "-call-" . md5($user->uuid) . '-' . time()),
            "command_id" => $uuid,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.telnyx.api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.telnyx.com/v2/calls/' . $call->call_control_id . '/actions/hangup', $payload);

        $result = $response->json();

        if (!Functions::telnyx_error_check($result)) {
            $call->status = 'completed';
            $call->save();
            throw new Exception($result['error']);
        }

        logger($result);
        return $result['data'];
    }

    public function answerCall(Request $request){
        $call_control_id = 'v3:VSuhDkJKpB2keH63Vxa2K1gcPQ8XIUt4XFzoptWLw9qFGOInuTRyqg';
        $response = $this->client->post('https://api.telnyx.com/v2/calls/' . $call_control_id . '/actions/answer');
        logger(['answer api call response : '=> json_decode($response->getBody(), true)]);
    }

    public function createConference(Request $request)
    {
        $call_control_id = $request->call_control_id;
        $response = $this->client->post(
            'https://api.telnyx.com/v2/conferences',
            [
                'json' => [
                    "call_control_id" => $call_control_id,
                    "name" => "Business",
                    "start_conference_on_create" => false,
                ],
            ]
        );
        $res = json_decode($response->getBody(), true);
        logger(['createConference' => $res]);
        return $res['data']['id'];
    }

    public function joinConference(Request $request){

        $id = $request->conference_id;
        $call_control_id = $request->call_control_id;
        $response = $this->client->post(
            'https://api.telnyx.com/v2/conferences/'.$id.'/actions/join',
            [
                'json' => [
                    "call_control_id" => $call_control_id,
                ],
            ]
        );
        $res = json_decode($response->getBody(), true);
        return $res;
    }
}
