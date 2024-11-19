<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelnyxService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.telnyx.com/v2/',
            'headers' => [
                'Authorization' => 'Bearer ' . env('TELNYX_API_KEY'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }


    public function makeCall($to, $from)
    {
        // $phone = DB::table('phone_numbers')->where('phone_number', $from)->first();
        // logger([$phone]);
        // $basicDetails = $this->numberBasicDetails($phone->telnyx_phoneResourceId);
        // if (isset($basicDetails[0]['code']) && isset($basicDetails[0]['description'])) {
        //     logger('Error Phone Number Details: ' . $basicDetails);
        //     dd('error');
        // } else {
        //     $connection_id = $basicDetails['connection_id'];
        // }
        $response = $this->client->post('calls', [
            'json' => [
                'to' => $to,
                'from' => $from,
                'connection_id' => '2472569862463948031',
                'stream_url' => 'wss://callingfeature.scrumad.com:3001',
                'stream_track' => 'inbound_track',
                'preferred_codecs' => 'PCMU',
                'stream_bidirectional_mode' => 'rtp',
                'stream_bidirectional_codec' => 'PCMU'
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function numberBasicDetails($id)
    {
        logger([$id,'mgjhgjhgj']);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TELNYX_API_KEY'),
            'Content-Type' => 'application/json',
        ])->get('https://api.telnyx.com/v2/phone_numbers/' . $id);

        $result = $response->json();
        if (!$this->telnyx_error_check($result)) {
            throw new Exception($result['error']);
        }

        if (isset($result['data'])) {
            return $result['data'];
        } else {
            logger('Number Details Error');
            logger(json_encode($result));
            return $result;
        }
    }

    public static function telnyx_error_check(&$result, $route = null, $extra = null)
    {
        $result = json_encode($result);
        $result = json_decode($result, true);

        if (!empty($result) && is_array($result) && array_key_exists('errors', $result) && !empty($result['errors']) && is_array($result['errors']) && count($result['errors']) > 0) {
            $code = $result['errors'][0]['code'];
            $title = $result['errors'][0]['title'];
            $detail = $result['errors'][0]['detail'];
            $error = $result['errors'];
            if ($code != "40310" && $code != "40012" && $code != "10002" && $code != "40001" && $code != '40002' && $code != '40003' && $code != '40015' && $code != '40315' && $code != '40300') {
                Log::debug('Telnyx Error: ' . $code . ' - ' . $title);
                Log::debug(json_encode($error), ['result' => $result, 'route' => $route, 'extra' => json_encode($extra)]);
            }

            $result = ['error' => $code . ' - ' . $title . "\n" . $detail, 'code' => $code, 'title' => $title, 'detail' => $detail];
            return false;
        } else {
            // if (Functions::is_empty($result))
            // {
            //     return false;
            // }
            return true;
        }
    }
}
