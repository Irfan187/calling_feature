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
        logger($event);
        logger($request->all());
        if ($event === 'call.initiated') {
            $callControlId = $request['data']['payload']['call_control_id'];
            

            try {
                // $response = $this->client->post('calls/'.$callControlId.'/actions/stream_start', [
                //     'json' => [
                //         'stream_url' => 'https://websocket.ngrok-free.dev',
                //         'media_format' => 'audio/opus',
                //         'direction' => 'both',
                //     ],
                // ]);

                // logger(json_decode($response->getBody(), true));
                

                logger('Stream started');
            } catch (Exception $e) {
                logger(['Error starting stream:', $e->getMessage()]);
            }
        }else{
            logger($request['data']['event_type']);
        }

    }
}
