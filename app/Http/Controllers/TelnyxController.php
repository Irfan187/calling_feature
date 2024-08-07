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
        return response('', 200);
    }
}
