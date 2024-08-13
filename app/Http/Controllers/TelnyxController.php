<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TelnyxService;
use Exception;
use GuzzleHttp\Client;

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

    public function callback(Request $request)
    {
        $event = $request['data']['event_type'];
        logger('telnyx event', $event);
        return true;
    }

    /**  Call Recording Feature Start */
    public function startCallRecording(Request $request){
        $to = $request->to;
        $from = $request->from;
        $call_control_id = $request->call_control_id;
        $response = $this->client->post('https://api.telnyx.com/v2/calls/'.$call_control_id.'/actions/record_start', [
            'json' => [
                'channels' => 'single',
                'format' => 'mp3'
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function endCallRecording(Request $request){
        $to = $request->to;
        $from = $request->from;
        $call_control_id = $request->call_control_id;
        $response = $this->client->post('https://api.telnyx.com/v2/calls/'.$call_control_id.'/actions/record_stop');

        return json_decode($response->getBody(), true);
    }

    /**  Call Recording Feature End */

}
