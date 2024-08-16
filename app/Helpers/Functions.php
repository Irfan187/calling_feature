<?php

namespace App\Helpers;

use App\Events\LowBalance;
use App\Events\ResetWorkflowAndDeleteJobsEvent;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\TelnyxController;
use App\Http\Controllers\WorkflowHistoryController;
use App\Jobs\UpdateDripBalanceJob;
use App\Models\AreaCode;
use App\Models\Attachment;
use App\Models\ContactMeta;
use App\Models\ContactPhone;
use App\Models\Country;
use App\Models\Message;
use App\Models\PhoneNumber;
use App\Models\State;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\Workflow;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as FileModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image as Images;
use PhpParser\Node\Expr\FuncCall;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;

class Functions
{

    public static $month_array = [
        '1' => 'Jan',
        '2' => 'Feb',
        '3' => 'Mar',
        '4' => 'Apr',
        '5' => 'May',
        '6' => 'Jun',
        '7' => 'Jul',
        '8' => 'Aug',
        '9' => 'Sep',
        '10' => 'Oct',
        '11' => 'Nov',
        '12' => 'Dec'
    ];

    public static $month_names = ['january', 'jan', 'february', 'feb', 'march', 'mar', 'april', 'apr', 'may', 'june', 'jun', 'july', 'jul', 'august', 'aug', 'september', 'sep', 'october', 'oct', 'november', 'nov', 'december', 'dec'];

    public static $area_codes = [
        201, 202, 203, 204, 205, 206, 207, 208, 209, 210,
        212, 213, 214, 215, 216, 217, 218, 219, 220, 223,
        224, 225, 226, 227, 228, 229, 231, 234, 235, 236,
        239, 240, 242, 246, 248, 249, 250, 251, 252, 253,
        254, 256, 257, 260, 262, 263, 264, 267, 268, 269,
        270, 272, 274, 276, 279, 281, 283, 284, 289, 301,
        302, 303, 304, 305, 306, 307, 308, 309, 310, 312,
        313, 314, 315, 316, 317, 318, 319, 320, 321, 323,
        324, 325, 326, 327, 329, 330, 331, 332, 334, 336,
        337, 339, 340, 341, 343, 345, 346, 347, 350, 351,
        352, 353, 354, 357, 360, 361, 363, 364, 365, 367,
        368, 369, 380, 382, 385, 386, 401, 402, 403, 404,
        405, 406, 407, 408, 409, 410, 412, 413, 414, 415,
        416, 417, 418, 419, 423, 424, 425, 428, 430, 431,
        432, 434, 435, 436, 437, 438, 440, 441, 442, 443,
        445, 447, 448, 450, 457, 458, 463, 464, 468, 469,
        470, 472, 473, 474, 475, 478, 479, 480, 483, 484,
        501, 502, 503, 504, 505, 506, 507, 508, 509, 510,
        512, 513, 514, 515, 516, 517, 518, 519, 520, 530,
        531, 532, 534, 539, 540, 541, 548, 551, 557, 559,
        561, 562, 563, 564, 567, 570, 571, 572, 573, 574,
        575, 579, 580, 581, 582, 584, 585, 586, 587, 601,
        602, 603, 604, 605, 606, 607, 608, 609, 610, 612,
        613, 614, 615, 616, 617, 618, 619, 620, 621, 623,
        624, 626, 628, 629, 630, 631, 636, 639, 640, 641,
        645, 646, 647, 649, 650, 651, 656, 657, 658, 659,
        660, 661, 662, 664, 667, 669, 670, 671, 672, 678,
        679, 680, 681, 682, 683, 684, 686, 689, 700, 701,
        702, 703, 704, 705, 706, 707, 708, 709, 712, 713,
        714, 715, 716, 717, 718, 719, 720, 721, 724, 725,
        726, 727, 728, 729, 730, 731, 732, 734, 737, 738,
        740, 742, 743, 747, 748, 753, 754, 757, 758, 760,
        762, 763, 765, 767, 769, 770, 771, 772, 773, 774,
        775, 778, 779, 780, 781, 782, 784, 785, 786, 787,
        800, 801, 802, 803, 804, 805, 806, 807, 808, 809,
        810, 812, 813, 814, 815, 816, 817, 818, 819, 820,
        821, 825, 826, 828, 829, 830, 831, 832, 833, 835,
        837, 838, 839, 840, 843, 844, 845, 847, 848, 849,
        850, 854, 855, 856, 857, 858, 859, 860, 861, 862,
        863, 864, 865, 866, 867, 868, 869, 870, 872, 873,
        876, 877, 878, 879, 888, 900, 901, 902, 903, 904,
        905, 906, 907, 908, 909, 910, 912, 913, 914, 915,
        916, 917, 918, 919, 920, 924, 925, 928, 929, 930,
        931, 934, 936, 937, 938, 939, 940, 941, 943, 945,
        947, 948, 949, 951, 952, 954, 956, 959, 970, 971,
        972, 973, 975, 978, 979, 980, 983, 984, 985, 986,
        989
    ];

    public static function is_empty($var)
    {
        return empty($var) || is_null($var);
    }

    public static function not_empty($var)
    {
        return !Functions::is_empty($var);
    }

    public static function verifyRecaptcha($token, $remoteIp)
    {
        $secret = '6LdsWkMpAAAAAEJoPqAExu97UBPQ0CdaVH2Dsddk';

        $data = [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
        ])->get('https://www.google.com/recaptcha/api/siteverify', $data);

        $result = $response->json();

        if ($result['success']) {
            return $result['score'];
        } else {
            return 0;
        }
    }

    public static function format_phone_number($number)
    {
        $number = preg_replace('/[^\d]+/', '', $number);
        $startsWithPlus = substr($number, 0, 1) === "+";
        $trimmedNumber = $startsWithPlus ? substr($number, 1) : $number;

        $length = strlen($trimmedNumber);
        if ($startsWithPlus && $length !== 11) {
            return '';
        }
        else if ($length === 10)
        {
            $number = "+1" . $trimmedNumber;
        }
        elseif ($length === 11 && $trimmedNumber[0] === "1")
        {
            $number = "+" . $trimmedNumber;
        }
        elseif ($length !== 12 || !$startsWithPlus)
        {
            return '';
        }

        if (strlen($number) === 12) {
            $areaCode = substr($number, 2, 3);
            if (!in_array($areaCode, Functions::$area_codes)) {
                return '';
            }
            return $number;
        }
        return '';
    }

    public static function isValidNumber($number)
    {
        $formattedNumber = self::format_phone_number($number);
        return $formattedNumber !== '';
    }

    public static function isValidPhoneNumber($number)
    {
        $number = trim($number);
        if ($number == null || $number == '') {
            return false;
        }

        $number = preg_replace('/[\s_\-\(\)\[\]\{\}]/', '', $number);

        $startsWithPlus = substr($number, 0, 1) === "+";
        $startsWithOne = substr($number, 0, 1) === "1";

        if (strlen($number) == 10 && !$startsWithPlus) {
            $number = "1" . $number;
        }

        if (!$startsWithPlus) {
            $number = "+" . $number;
        }

        $length_of_number = strlen($number);
        if ($length_of_number < 11 || $length_of_number > 13) {
            return false;
        }

        return true;
    }

    public static function beautify_phone_number($val)
    {
        if ($val) {
            $cleanNumber = preg_replace('/[\t\s_\-\(\)\[\]\{\}]/', '', $val);
            $cleanNumber = preg_replace('/\D/', '', $cleanNumber);
            $cleanNumber = trim($cleanNumber);

            if (strlen($cleanNumber) === 10) {
                $cleanNumber = "+1" . $cleanNumber;
            }

            if (strlen($cleanNumber) === 11 && strpos($cleanNumber, '+') !== 0)
            {
                $cleanNumber = "+1 " . substr($cleanNumber, 1, 3) . "-" . substr($cleanNumber, 4, 3) . "-" . substr($cleanNumber, 7);
                return $cleanNumber;
            }
            elseif (strlen($cleanNumber) === 12 && strpos($cleanNumber, '+') === 0)
            {
                $cleanNumber = "+1 " . substr($cleanNumber, 2, 3) . "-" . substr($cleanNumber, 5, 3) . "-" . substr($cleanNumber, 8);
                return $cleanNumber;
            } else {
                return $val;
            }
        }
        return $val;
    }

    public static function get_area_code_from_number($number)
    {
        $number = Functions::format_phone_number($number);
        $phoneNumber = ltrim($number, '+');
        $areaCode = substr($phoneNumber, 1, 3);
        return $areaCode;
    }

    public static function filtered_request_data($data, $same_case = false)
    {
        foreach ($data as $i => $d) {
            if ($d === "null" || $d === "") {
                $data[$i] = null;
            }
            if ($same_case && !($d === "null" || $d === "")) {
                $data[$i] = strtolower($data[$i]);
            }
        }
        return $data;
    }

    public static function processMessageText($text, $contact, $timeOfDay)
    {
        // Replace placeholders with contact field values
        $text = preg_replace_callback('/\[\[(.*?)\]\]/', function ($matches) use ($contact, $timeOfDay) {
            $fieldName = strtolower(trim($matches[1]));

            if ($fieldName == 'intro_time') {
                $contactFieldValue = $timeOfDay;
            } else {
                $contactFieldValue = $contact->$fieldName();
            }

            return $contactFieldValue ?? ''; // Replace with actual value
        }, $text);

        // Select randomly from alternating text
        $text = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) {
            $alternatives = explode('|', $matches[1]);
            $randomIndex = array_rand($alternatives);
            return trim($alternatives[$randomIndex]);
        }, $text);

        return trim($text);
    }

    public static function getTimeByLocation($area_code)
    {
        $area = AreaCode::where('code', $area_code)->first();

        if ($area) {
            $utcOffset = $area->utc_offset;
            if ($utcOffset > 0) {
                $timestamp = now()->addHours($utcOffset);
            } else {
                $timestamp = now()->subHours($utcOffset);
            }

            $timeOfDay = Functions::getTimeOfDay($timestamp);

            return $timeOfDay;
        } else {
            return "";
        }
    }

    public static function getTimeOfDay($timestamp)
    {
        $hour = $timestamp->hour;

        if ($hour >= 6 && $hour < 12) {
            return 'Morning';
        } elseif ($hour >= 12 && $hour < 17) {
            return 'Afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            return 'Evening';
        } else {
            return 'Night';
        }
    }

    public static function file_upload(UploadedFile $image, $path, $size = [])
    {

        if (count($size)) {
            $width = $size[0];
            $height = $size[0];
            if (isset($size[1])) {
                $height = $size[1];
            }
            $img = Images::make($image)->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->encode('webp', 100);
            $img->save($path);
        } else {
            $img = Images::make($image)->encode('webp', 100);
            $img->save($path);
        }
        Artisan::call('storage:link');
    }

    public static function seconds_to_time($seconds)
    {
        $time = "";

        $hours = floor($seconds / 3600);
        if ($hours > 0) {
            $time .= $hours . ' hr ';
            $seconds = $seconds - ($hours * 3600);
        }

        $minutes = floor($seconds / 60);
        if ($minutes > 0) {
            $time .= $minutes . ' min ';
            $seconds = $seconds - ($minutes * 60);
        }

        if ($seconds > 0) {
            $time .= $seconds . ' sec';
        }

        $time = trim($time);

        return $time;
    }

    public static function get_time_ago($time_stamp)
    {
        $time_difference = strtotime('now') - $time_stamp;

        if ($time_difference >= 60 * 60 * 24 * 365.242199) {
            /*
         * 60 seconds/minute * 60 minutes/hour * 24 hours/day * 365.242199 days/year
         * This means that the time difference is 1 year or more
         */
            return Functions::get_time_ago_string($time_stamp, 60 * 60 * 24 * 365.242199, 'year');
        } elseif ($time_difference >= 60 * 60 * 24 * 30.4368499) {
            /*
         * 60 seconds/minute * 60 minutes/hour * 24 hours/day * 30.4368499 days/month
         * This means that the time difference is 1 month or more
         */
            return Functions::get_time_ago_string($time_stamp, 60 * 60 * 24 * 30.4368499, 'month');
        } elseif ($time_difference >= 60 * 60 * 24 * 7) {
            /*
         * 60 seconds/minute * 60 minutes/hour * 24 hours/day * 7 days/week
         * This means that the time difference is 1 week or more
         */
            return Functions::get_time_ago_string($time_stamp, 60 * 60 * 24 * 7, 'week');
        } elseif ($time_difference >= 60 * 60 * 24) {
            /*
         * 60 seconds/minute * 60 minutes/hour * 24 hours/day
         * This means that the time difference is 1 day or more
         */
            return Functions::get_time_ago_string($time_stamp, 60 * 60 * 24, 'day');
        } elseif ($time_difference >= 60 * 60) {
            /*
         * 60 seconds/minute * 60 minutes/hour
         * This means that the time difference is 1 hour or more
         */
            return Functions::get_time_ago_string($time_stamp, 60 * 60, 'hour');
        } elseif ($time_difference >= 60) {
            /*
         * 60 seconds/minute
         * This means that the time difference is a matter of minutes
         */
            return Functions::get_time_ago_string($time_stamp, 60, 'minute');
        } else {
            /*
         * 60 seconds
         * This means that the time difference is a matter of seconds
         */
            return Functions::get_time_ago_string($time_stamp, 1, 'second');
        }
    }

    public static function get_time_ago_string($time_stamp, $divisor, $time_unit)
    {
        $time_difference = strtotime("now") - $time_stamp;
        $time_units      = floor($time_difference / $divisor);

        settype($time_units, 'string');

        if ($time_units === '0') {
            return 'less than 1 ' . $time_unit . ' ago';
        } elseif ($time_units === '1') {
            return '1 ' . $time_unit . ' ago';
        } else {
            /*
         * More than "1" $time_unit. This is the "plural" message.
         */
            // TODO: This pluralizes the time unit, which is done by adding "s" at the end; this will not work for i18n!
            return $time_units . ' ' . $time_unit . 's ago';
        }
    }

    public static function get_query_params(Request $request)
    {
        // $starts_with = "";
        $filter = "";
        $dir = "asc";
        $limit = 10;
        $sort = "";
        $page = 1;
        // $search = "no";
        $filternames = [];
        $filtervalues = [];
        $filtercond = [];
        $filterfieldtype = [];
        $filtersearchmethod = [];

        // $filteraltnames = [];

        // if ($request->filled('startsWith'))
        // {
        //     $starts_with = $request->query('startsWith');
        // }
        if ($request->filled('limit')) {
            $limit = $request->query('limit');
        }
        if ($request->filled('page')) {
            $page = $request->query('page');
        }
        if ($request->filled('sort')) {
            $sort = $request->query('sort');
        }
        if ($request->filled('dir')) {
            $dir = $request->query('dir');
        }
        if ($request->filled('filter')) {
            $filter = $request->query('filter');
            $filter = str_replace("'", "''", $filter);
        }
        // if ($request->filled('search'))
        // {
        //     $search = $request->query('search');
        // }
        if ($request->filled('filternames')) {
            $filternames = $request->query('filternames');
        }
        if ($request->filled('filtervalues')) {
            $filtervalues = $request->query('filtervalues');
        }
        if ($request->filled('filterfieldtype')) {
            $filterfieldtype = $request->query('filterfieldtype');
        }
        if ($request->filled('filtersearchmethod')) {
            $filtersearchmethod = $request->query('filtersearchmethod');
        }
        if ($request->filled('filtercond')) {
            $filtercond = $request->query('filtercond');
        }
        // if ($request->filled('filteraltnames'))
        // {
        //     $filteraltnames = $request->query('filteraltnames');
        // }

        return compact('page', 'filter', 'dir', 'limit', 'sort', 'filternames', 'filtervalues', 'filterfieldtype', 'filtercond', 'filtersearchmethod');
    }

    public static function query_generator(Builder $query, $params, $selectCols, $selectRaw = '', $searchCols = [], $whereRaw = '')
    {
        $query = $query->select($selectCols);
        if ($selectRaw != '') {
            $query = $query->selectRaw($selectRaw);
        }

        $query = Functions::apply_query_filters($query, $params, $searchCols, $whereRaw);
        if ($params['sort'] != "") {
            $query->orderBy($params['sort'], $params['dir']);
        }

        return $query;
    }

    public static function apply_query_filters(Builder $query, $params, $searchCols, $whereRaw)
    {
        if (isset($params['filter'])) {
            $query = $query->where(function ($q) use ($query, $searchCols, $params) {
                foreach ($searchCols as $index => $searchCol) {

                    if ($searchCol[1] == 'datetime') {
                        try {
                            $search_value = $params['filter'];
                            $temp_val = $params['filter'];
                            if (is_numeric($temp_val)) {
                                $temp_val = (int) $temp_val;
                            }
                            $date = Carbon::parse($temp_val);
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                    $col = $searchCol[0];

                    if ($index == 0) {
                        if ($searchCol[1] == 'raw') {
                            $q = $q->whereRaw($searchCol[0]);
                        } else if ($searchCol[1] == 'string') {
                            $q = $q->where($searchCol[0], 'like', '%' . $params['filter'] . '%');
                        } else if ($searchCol[1] == 'numeric') {
                            $q = $q->where($searchCol[0], '=', $params['filter']);
                        } else if ($searchCol[1] == 'datetime') {

                            $q = $q->where(function ($qr) use ($col, $date, $temp_val, $search_value) {
                                if (in_array(strtolower($search_value), Functions::$month_names)) {
                                    $qr->whereMonth($col, "=", Carbon::parse($temp_val)->month);
                                } elseif (($date->toDateString() == "1970-01-01" && ((int) $temp_val !== 1970 || (string) $temp_val !== "1970-01-01"))) {
                                    $qr->whereDate($col, "=", $date->toDateString())->orWhereDay($col, "=", $temp_val)->orWhereMonth($col, "=", Carbon::parse($temp_val)->month)->orWhereYear($col, "=", $date->year == 1970 && (int) $temp_val !== 1970 ? $temp_val : $date->year);
                                } else {
                                    $qr->whereDate($col, "=", $date->toDateString())->whereDay($col, "=", $date->day)->whereMonth($col, "=", $date->month)->whereYear($col, "=", $date->year == 1970 && (int) $temp_val !== 1970 ? $temp_val : $date->year);
                                }
                            });
                            if (!($date->toDateString() == "1970-01-01" && ((int) $temp_val !== 1970 || (string) $temp_val !== "1970-01-01"))) {
                                $q = $q->orWhereRaw('WEEKDAY(' . $col . ') = ' . ($date->dayOfWeek - 1));
                            }
                        }
                    } else {
                        if ($searchCol[1] == 'raw') {
                            $q = $q->orWhereRaw($searchCol[0]);
                        } else if ($searchCol[1] == 'string') {
                            $q = $q->orWhere($searchCol[0], 'like', '%' . $params['filter'] . '%');
                        } else if ($searchCol[1] == 'numeric') {
                            $q = $q->orWhere($searchCol[0], '=', $params['filter']);
                        } else if ($searchCol[1] == 'datetime') {
                            $q = $q->orWhere(function ($qr) use ($col, $date, $temp_val, $search_value) {
                                if (in_array(strtolower($search_value), Functions::$month_names)) {
                                    $qr->whereMonth($col, "=", Carbon::parse($temp_val)->month);
                                } elseif (($date->toDateString() == "1970-01-01" && ((int) $temp_val !== 1970 || (string) $temp_val !== "1970-01-01"))) {
                                    $qr->whereDate($col, "=", $date->toDateString())->orWhereDay($col, "=", $temp_val)->orWhereMonth($col, "=", Carbon::parse($temp_val)->month)->orWhereYear($col, "=", $date->year == 1970 && (int) $temp_val !== 1970 ? $temp_val : $date->year);
                                } else {
                                    $qr->whereDate($col, "=", $date->toDateString())->whereDay($col, "=", $date->day)->whereMonth($col, "=", $date->month)->whereYear($col, "=", $date->year == 1970 && (int) $temp_val !== 1970 ? $temp_val : $date->year);
                                }
                            });
                            if (!($date->toDateString() == "1970-01-01" && ((int) $temp_val !== 1970 || (string) $temp_val !== "1970-01-01"))) {
                                $q = $q->orWhereRaw('WEEKDAY(' . $col . ') = ' . ($date->dayOfWeek - 1));
                            }
                        }
                    }
                }
            });
        }


        if (isset($params['filternames'])) {
            foreach ($params['filternames'] as $filter_index => $filter) {
                $first = true;
                if (strpos($filter, 'having_') !== false) {
                    continue;
                }

                if ($params['filtercond'] == 'and') {
                    $query = $query->where(function ($q1) use ($filter, $params, $filter_index) {
                        foreach ($params['filtervalues'][$filter_index] as $filter_value_index => $filter_value) {
                            $filter_params = explode('|', $filter_value);
                            if (count($filter_params) > 1) {
                                $temp_col_name = $filter_params[0] . '.' . $filter_params[1];
                                $filter_values = explode(',', $filter_params[2]);
                            } else {
                                $temp_col_name = '';
                                $filter_values = explode(',', $filter_params[0]);
                            }

                            if ($params['filterfieldtype'][$filter_index] == 'string') {
                                $searchType = $params['filtersearchmethod'][$filter_index];
                                $q1->where(function ($q2) use ($filter, $filter_values, $temp_col_name, $searchType) {
                                    foreach ($filter_values as $ifv => $fv) {
                                        if ($temp_col_name == '') {
                                            if ($fv == '') {
                                                $q2->whereNull($filter);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q2->where($filter, '=', $fv);
                                                } else {
                                                    $q2->where($filter, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q2->whereNull($temp_col_name);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q2->where($temp_col_name, '=', $fv);
                                                } else {
                                                    $q2->where($temp_col_name, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        }
                                    }
                                });
                            } else if ($params['filterfieldtype'][$filter_index] == 'numeric') {
                                $q1->where(function ($q2) use ($filter, $filter_values, $temp_col_name) {
                                    $temp_col_name = '';
                                    foreach ($filter_values as $ifv => $fv) {
                                        if ($temp_col_name == '') {
                                            if ($fv == '') {
                                                $q2->whereNull($filter);
                                            } else {
                                                $q2->where($filter, '=', $fv);
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q2->whereNull($temp_col_name);
                                            } else {
                                                $q2->where($temp_col_name, '=', $fv);
                                            }
                                        }
                                    }
                                });
                            }
                        }
                    });
                } else {
                    $query = $query->where(function ($q1) use ($filter, $params, $filter_index, $first) {
                        foreach ($params['filtervalues'][$filter_index] as $filter_value_index => $filter_value) {
                            $filter_params = explode('|', $filter_value);
                            if (count($filter_params) > 1) {
                                $temp_col_name = $filter_params[0] . '.' . $filter_params[1];
                                $filter_values = explode(',', $filter_params[2]);
                            } else {
                                $temp_col_name = '';
                                $filter_values = explode(',', $filter_params[0]);
                            }

                            if ($params['filterfieldtype'][$filter_index] == 'string') {
                                $searchType = $params['filtersearchmethod'][$filter_index];
                                foreach ($filter_values as $ifv => $fv) {
                                    if ($ifv == 0 && $first) {
                                        if ($temp_col_name != '') {
                                            if ($fv == '') {
                                                $q1->whereNull($temp_col_name);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q1->where($temp_col_name, '=', $fv);
                                                } else {
                                                    $q1->where($temp_col_name, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q1->whereNull($filter);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q1->where($filter, '=', $fv);
                                                } else {
                                                    $q1->where($filter, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        }
                                        $first = false;
                                    } else {
                                        if ($temp_col_name != '') {
                                            if ($fv == '') {
                                                $q1->orWhereNull($temp_col_name);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q1->orWhere($temp_col_name, '=', $fv);
                                                } else {
                                                    $q1->orWhere($temp_col_name, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q1->orWhereNull($filter);
                                            } else {
                                                if ($searchType == 'exact') {
                                                    $q1->orWhere($filter, '=', $fv);
                                                } else {
                                                    $q1->orWhere($filter, 'like', '%' . $fv . '%');
                                                }
                                            }
                                        }
                                    }
                                }
                            } else if ($params['filterfieldtype'][$filter_index] == 'numeric') {
                                foreach ($filter_values as $ifv => $fv) {
                                    if ($ifv == 0 && $first) {
                                        if ($temp_col_name != '') {
                                            if ($fv == '') {
                                                $q1->whereNull($temp_col_name);
                                            } else {
                                                $q1->where($temp_col_name, '=', $fv);
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q1->whereNull($filter);
                                            } else {
                                                $q1->where($filter, '=', $fv);
                                            }
                                        }
                                        $first = false;
                                    } else {
                                        if ($temp_col_name != '') {
                                            if ($fv == '') {
                                                $q1->orWhereNull($temp_col_name);
                                            } else {
                                                $q1->orWhere($temp_col_name, '=', $fv);
                                            }
                                        } else {
                                            if ($fv == '') {
                                                $q1->orWhereNull($filter);
                                            } else {
                                                $q1->orWhere($filter, '=', $fv);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            foreach ($params['filternames'] as $filter_index => $filter) {
                $first = true;
                if (strpos($filter, 'having_') === false) {
                    continue;
                }

                $field_name = str_replace("having_", "", $filter);

                $searchType = $params['filtersearchmethod'][$filter_index];

                if ($params['filtercond'] == 'and') {
                    foreach ($params['filtervalues'][$filter_index] as $filter_value_index => $filter_value) {
                        $filter_params = explode('|', $filter_value);
                        if (count($filter_params) > 1) {
                            $temp_col_name = $filter_params[0] . '.' . $filter_params[1];
                            $filter_values = explode(',', $filter_params[2]);
                        } else {
                            $temp_col_name = '';
                            $filter_values = explode(',', $filter_params[0]);
                        }

                        foreach ($filter_values as $ifv => $fv) {
                            if ($params['filterfieldtype'][$filter_index] == 'string') {
                                if ($temp_col_name != '') {
                                    if ($searchType == 'exact') {
                                        $query = $query->having($temp_col_name, '=', $fv);
                                    } else {
                                        $query = $query->having($temp_col_name, 'like', '%' . $fv . '%');
                                    }
                                } else {
                                    if ($searchType == 'exact') {
                                        $query = $query->having($field_name, '=', $fv);
                                    } else {
                                        $query = $query->having($field_name, 'like', '%' . $fv . '%');
                                    }
                                }
                            } else if ($params['filterfieldtype'][$filter_index] == 'numeric') {
                                if ($temp_col_name != '') {
                                    $query = $query->having($temp_col_name, '=', $fv);
                                } else {
                                    $query = $query->having($field_name, '=', $fv);
                                }
                            }
                        }
                    }
                } else {
                    foreach ($params['filtervalues'][$filter_index] as $filter_value_index => $filter_value) {
                        $filter_params = explode('|', $filter_value);
                        if (count($filter_params) > 1) {
                            $temp_col_name = $filter_params[0] . '.' . $filter_params[1];
                            $filter_values = explode(',', $filter_params[2]);
                        } else {
                            $temp_col_name = '';
                            $filter_values = explode(',', $filter_params[0]);
                        }

                        foreach ($filter_values as $ifv => $fv) {
                            if ($params['filterfieldtype'][$filter_index] == 'string') {
                                if ($ifv == 0 && $first) {
                                    if ($temp_col_name != '') {
                                        if ($searchType == 'exact') {
                                            $query = $query->having($temp_col_name, '=', $fv);
                                        } else {
                                            $query = $query->having($temp_col_name, 'like', '%' . $fv . '%');
                                        }
                                    } else {
                                        if ($searchType == 'exact') {
                                            $query = $query->having($field_name, '=', $fv);
                                        } else {
                                            $query = $query->having($field_name, 'like', '%' . $fv . '%');
                                        }
                                    }
                                    $first = false;
                                } else {
                                    if ($temp_col_name != '') {
                                        if ($searchType == 'exact') {
                                            $query = $query->orHaving($temp_col_name, '=', $fv);
                                        } else {
                                            $query = $query->orHaving($temp_col_name, 'like', '%' . $fv . '%');
                                        }
                                    } else {
                                        if ($searchType == 'exact') {
                                            $query = $query->orHaving($field_name, '=', $fv);
                                        } else {
                                            $query = $query->orHaving($field_name, 'like', '%' . $fv . '%');
                                        }
                                    }
                                }
                            } else if ($params['filterfieldtype'][$filter_index] == 'numeric') {
                                if ($ifv == 0 && $first) {
                                    if ($temp_col_name != '') {
                                        $query = $query->having($temp_col_name, '=', $fv);
                                    } else {
                                        $query = $query->having($field_name, '=', $fv);
                                    }
                                    $first = false;
                                } else {
                                    if ($temp_col_name != '') {
                                        $query = $query->orHaving($temp_col_name, '=', $fv);
                                    } else {
                                        $query = $query->orHaving($field_name, '=', $fv);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // logger($query->toSql());
        // logger($query->getBindings());

        return $query;
    }

    public static function telnyx_error_check(&$result, $route = null, $extra = null)
    {
        $result = json_encode($result);
        $result = json_decode($result, true);

        if (Functions::not_empty($result) && is_array($result) && array_key_exists('errors', $result) && Functions::not_empty($result['errors']) && is_array($result['errors']) && count($result['errors']) > 0) {
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

    public static function convertToTrueFalse($dataArray)
    {
        foreach ($dataArray as $key => $value) {
            if ($value == 0) {
                $dataArray[$key] = false;
            }
            if ($value == 1) {
                $dataArray[$key] = true;
            }
        }
        return $dataArray;
    }

    public static function createQueueConnection($connection, $queue)
    {
        $connection = 'database';

        $configFile = config_path('queue.php');
        $config = include $configFile;

        $config['connections'][$queue] = [
            'driver' => $connection,
            'connection' => $connection,
            'queue' => $queue,
        ];

        File::put($configFile, '<?php return ' . var_export($config, true) . ';');
    }

    public static function removeQueueConnection($queue)
    {
        $filePath = config_path('queue.php');
        $fileContent = File::get($filePath);

        $connectionName = $queue;

        $pattern = "/'$connectionName'\s*=>\s*array\s*\([\s\S]*?\),/";
        $fileContent = preg_replace($pattern, '', $fileContent);

        File::put($filePath, $fileContent);
    }

    public static function createSupervisor($queue, $workflow_id = null, $type = null, $multiWorker = true, $tries = 3)
    {
        $workflow = null;
        if (Functions::not_empty($workflow_id)) {
            $workflow = Workflow::where('id', $workflow_id)->first();
        }

        if (Functions::not_empty($workflow)) {
            $workers = Functions::calculateWorkers($workflow, $type, $multiWorker);

            $commandReturn = Artisan::call('supervisor:create-worker', [
                'queue' => $queue,
                'worker' => $workers,
                'tries' => $tries
            ]);
        } else {
            $commandReturn = Artisan::call('supervisor:create-worker', [
                'queue' => $queue,
                'worker' => 1,
                'tries' => $tries
            ]);
        }
    }

    public static function removeSupervisor($queue, $workflow_id = null, $resetFlag = false)
    {
        if (strpos($queue, '-add-waitlist-job-') !== false || strpos($queue, '-add-workflow-job-') !== false) {
            event(new ResetWorkflowAndDeleteJobsEvent($workflow_id, $resetFlag, $queue));
        } else if (strpos($queue, 'add-waitlist-job-chunk') !== false || strpos($queue, 'add-workflow-job-chunk') !== false) {
            event(new ResetWorkflowAndDeleteJobsEvent(null, false, $queue));
            // $jobsCount = DB::table('jobs')->where('queue', $queue)->count();
            // if ($jobsCount <= 1)
            // {
            //     $commandReturn = Artisan::call('supervisor:delete-worker', [
            //         'queue' => $queue
            //     ]);
            // }
            // Log::channel('custom')->debug('Functions.php - 2nd Condition - jobsCount: ' . $jobsCount . ' - queue: ' . $queue);
        } else {
            event(new ResetWorkflowAndDeleteJobsEvent(null, false, $queue));
            // $jobsCount = DB::table('jobs')->where('queue', $queue)->count();
            // if ($jobsCount == 0)
            // {
            //     $commandReturn = Artisan::call('supervisor:delete-worker', [
            //         'queue' => $queue
            //     ]);
            // }
            // Log::channel('custom')->debug('Functions.php - 3rd Condition - jobsCount: ' . $jobsCount . ' - queue: ' . $queue);
        }
    }

    public static function get_phone_price($package, $phone, $direction = 'outbound', $type = 'message')
    {
        $numberType = $phone->type;
        $isNumberRegistered = false;

        if (Functions::is_empty($numberType)) {
            $areaCode = Functions::get_area_code_from_number($phone->phone_number);
            $tollFreeCode = ['844', '855', '866', '877', '888'];
            if (in_array($areaCode, $tollFreeCode)) {
                $numberType = 'tollfree';
            } else {
                $numberType = 'longcode';
            }
        }

        if ($numberType == 'longcode') {
            $isNumberRegistered = $phone->is_number_registered();
        } else if ($numberType == 'tollfree') {
            $isNumberRegistered = $phone->is_toll_free_registered();
        }

        $sms_cost = 0;
        $mms_cost = 0.02;
        $call_cost = 0.01;
        $other_cost = 0.5;

        if (Functions::is_empty($package)) {
            if ($isNumberRegistered && $direction == 'outbound' && $type == 'message') {
                $sms_cost = 0.012;
                $mms_cost = 0.03;
            } else if (!$isNumberRegistered && $direction == 'outbound' && $type == 'message') {
                $sms_cost = 0.018;
                $mms_cost = 0.03;
            } else if ($isNumberRegistered && $direction == 'inbound' && $type == 'message') {
                $sms_cost = 0.0;
                $mms_cost = 0.02;
            } else if (!$isNumberRegistered && $direction == 'inbound' && $type == 'message') {
                $sms_cost = 0.0;
                $mms_cost = 0.02;
            }

            if ($isNumberRegistered && $direction == 'outbound' && $type == 'call') {
                $call_cost = 0.01;
            } else if (!$isNumberRegistered && $direction == 'outbound' && $type == 'call') {
                $call_cost = 0.01;
            } else if ($isNumberRegistered && $direction == 'inbound' && $type == 'call') {
                $call_cost = 0.01;
            } else if (!$isNumberRegistered && $direction == 'inbound' && $type == 'call') {
                $call_cost = 0.01;
            }

            return ['sms' => (float) $sms_cost, 'mms' => (float) $mms_cost, 'call' => (float) $call_cost];
        }

        if ($numberType == 'tollfree') {
            if ($type == 'message') {
                if ($direction == 'inbound') {
                    $sms_cost = $isNumberRegistered ? $package->registered_inbound_toll_message_price : $package->inbound_toll_message_price;
                    $mms_cost = $isNumberRegistered ? $package->registered_inbound_toll_mms_price : $package->inbound_toll_mms_price;
                } elseif ($direction == 'outbound') {
                    $sms_cost = $isNumberRegistered ? $package->registered_outbound_toll_message_price : $package->outbound_toll_message_price;
                    $mms_cost = $isNumberRegistered ? $package->registered_outbound_toll_mms_price : $package->outbound_toll_mms_price;
                } else {
                    return ['other' => (float) $other_cost];
                }
            } elseif ($type == 'call') {
                if ($direction == 'inbound') {
                    $call_cost = $isNumberRegistered ? $package->registered_inbound_toll_call_price : $package->inbound_toll_call_price;
                } elseif ($direction == 'outbound') {
                    $call_cost = $isNumberRegistered ? $package->registered_outbound_toll_call_price : $package->outbound_toll_call_price;
                } else {
                    return ['other' => (float) $other_cost];
                }
            } else {
                return ['other' => (float) $other_cost];
            }
        } else {
            if ($type == 'message') {
                if ($direction == 'inbound') {
                    $sms_cost = $isNumberRegistered ? $package->registered_inbound_text_message_price : $package->inbound_text_message_price;
                    $mms_cost = $isNumberRegistered ? $package->registered_inbound_mms_price : $package->inbound_mms_price;
                } elseif ($direction == 'outbound') {
                    $sms_cost = $isNumberRegistered ? $package->registered_outbound_text_message_price : $package->outbound_text_message_price;
                    $mms_cost = $isNumberRegistered ? $package->registered_outbound_mms_price : $package->outbound_mms_price;
                } else {
                    return ['other' => (float) $other_cost];
                }
            } elseif ($type == 'call') {
                if ($direction == 'inbound') {
                    $call_cost = $isNumberRegistered ? $package->registered_inbound_call_price : $package->inbound_call_price;
                } elseif ($direction == 'outbound') {
                    $call_cost = $isNumberRegistered ? $package->registered_outbound_call_price : $package->outbound_call_price;
                } else {
                    return ['other' => (float) $other_cost];
                }
            } else {
                return ['other' => (float) $other_cost];
            }
        }

        return ['sms' => (float) $sms_cost, 'mms' => (float) $mms_cost, 'call' => (float) $call_cost];
    }

    public static  function workflowInTimeWindow($workflow, $today)
    {
        if ($workflow->mon == 1 && $today == 'mon') {
            return true;
        } else if ($workflow->tue == 1 && $today == 'tue') {
            return true;
        } else if ($workflow->wed == 1 && $today == 'wed') {
            return true;
        } else if ($workflow->thu == 1 && $today == 'thu') {
            return true;
        } else if ($workflow->fri == 1 && $today == 'fri') {
            return true;
        } else if ($workflow->sat == 1 && $today == 'sat') {
            return true;
        } else if ($workflow->sun == 1 && $today == 'sun') {
            return true;
        }
        return false;
    }

    public static function workflowDayList($workflow)
    {
        $days = [];
        if ($workflow->mon == 1) {
            $days[] = 'monday';
        }
        if ($workflow->tue == 1) {
            $days[] = 'tuesday';
        }
        if ($workflow->wed == 1) {
            $days[] = 'wednesday';
        }
        if ($workflow->thu == 1) {
            $days[] = 'thursday';
        }
        if ($workflow->fri == 1) {
            $days[] = 'friday';
        }
        if ($workflow->sat == 1) {
            $days[] = 'saturday';
        }
        if ($workflow->sun == 1) {
            $days[] = 'sunday';
        }
        return $days;
    }

    public static function getDelayInSeconds($value, $unit)
    {
        $unit = strtolower($unit);
        $value = (float) $value;

        $seconds = 0;
        switch ($unit) {
            case 'minutes':
                $seconds = (int) (60 * $value);
                break;
            case 'hours':
                $seconds = (int) (3600 * $value);
                break;
            case 'days':
                $seconds = (int) (86400 * $value);
                break;
            default:
                break;
        }
        return $seconds;
    }

    public static function calculateNumberOfJobs($processingTimePerJob, $startTime, $endTime, $totalJobs = 0)
    {
        if (Functions::is_empty($startTime) || Functions::is_empty($endTime)) {
            return $totalJobs;
        }
        // Convert start and end time to seconds
        $startTimeInSeconds = strtotime($startTime) - strtotime('00:00:00');
        $endTimeInSeconds = strtotime($endTime) - strtotime('00:00:00');

        // Calculate the duration of the time interval in seconds
        $timeIntervalInSeconds = $endTimeInSeconds - $startTimeInSeconds;
        $timeIntervalInMilliSeconds = $timeIntervalInSeconds * 1000;

        // Calculate the number of jobs that can be processed
        $numberOfJobs = floor($timeIntervalInMilliSeconds / $processingTimePerJob);

        if ($numberOfJobs <= 0) {
            return 0;
        }

        return $numberOfJobs;
    }

    // $startTime and $endTime format "hh:mm:ss"
    // $days is the list of days name in this format ['monday', 'tuesday', ...]
    public static function filterTimezones($days, $startTime, $endTime, $unrestricted = false)
    {
        $filteredTimezones = [];

        $timezones = [
            "America/New_York",
            "America/Chicago",
            "America/Los_Angeles",
            "America/Denver",
            "America/Puerto_Rico",
            "America/Indianapolis",
            "America/Phoenix",
            "Pacific/Guam",
            "Pacific/Pago_Pago",
            "Pacific/Honolulu",
            "America/Anchorage"
        ];

        if ($unrestricted) {
            return $timezones;
        }


        foreach ($timezones as $timezone) {
            $currentDay = strtolower(Carbon::now($timezone)->format('l'));

            if (in_array($currentDay, $days)) {
                $currentTime = Carbon::now($timezone);
                $currentDayString = Carbon::now($timezone)->format('Y-m-d');
                $startTimeStr = $currentDayString . ' ' . $startTime;
                $startTimeObj = Carbon::parse($startTimeStr, $timezone);
                $endTimeStr = $currentDayString . ' ' . $endTime;
                $endTimeObj = Carbon::parse($endTimeStr, $timezone);
                if ($currentTime->gte($startTimeObj) && $currentTime->lte($endTimeObj)) {
                    $filteredTimezones[] = $timezone;
                }
            }
        }

        return $filteredTimezones;
    }

    public static function calculateWorkers($workflow, $type = 'waitlist', $multiWorker = true)
    {
        $workers = 1;
        if ($multiWorker) {
            if ($type == 'waitlist') {
                if (Functions::not_empty($workflow) && $workflow->waiting_list == 1) {
                    $waitingItems = (int) $workflow->waiting_items_to_process;
                    $waiting_interval = (int) $workflow->waiting_interval;
                    $average_time = 15;
                    if ($waiting_interval > 0) {
                        // $new_rate = $waitingItems / $waiting_interval;
                        // $workers = ceil($new_rate / 20);

                        $workers = ($waitingItems * $average_time) / (60 * $waiting_interval);
                        $workers = ceil($workers);
                    }
                    if ($workers < 1) {
                        $workers = 1;
                    }
                }
            }
            if ($type == 'workflow') {
                $workers = 5;
            }
        }

        return $workers;
    }

    public static function updateUserBalance(User $user, $cost)
    {
        try {
            // $queue = 'update_balance_user_' . $user->id;
            // $workerPath = "/etc/supervisor/conf.d/{$queue}_worker.conf";
            // if (!File::exists($workerPath)) {
            //     Functions::createSupervisor($queue, null, null, false, 1);
            // }

            // UpdateDripBalanceJob::dispatch($user, $cost)->onQueue($queue);
            UpdateDripBalanceJob::dispatch($user, $cost)->onConnection('redis')->onQueue('balance_update');

            return true;
        } catch (Exception $e) {
            logger($e->getMessage() . ' - ' . $e->getCode() . ' - ' . $e->getLine() . ' - ' . $e->getTraceAsString());
            return false;
        }
    }

    public static function processUpdateBalance($user, $cost)
    {
        $balance = $user->balance;
        $newBalance = $balance - $cost;

        DB::beginTransaction();
        try {
            $user->update(['balance' => $newBalance]);
            DB::commit();
            Cache::put("user_balance_{$user->id}", (float) $newBalance, now()->addMinutes(5));
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }

        $settings = $user->fetchSettings();
        if ($settings->threshold_amount >= $newBalance && $settings->threshold_amount > 0 && $user->attempting_auto_reload == 0) {
            event(new LowBalance($user));
        }

        return true;
    }

    public static function deleteRecordsByTag($tag)
    {
        $keys = Redis::smembers($tag);
        foreach ($keys as $key) {
            Redis::del('salesgod_crm_horizon:' . $key);
        }
        Redis::del($tag);
    }

    public static function deleteAllRecordsByTag($tag)
    {
        $keys = Redis::keys($tag . '*');
        foreach ($keys as $key) {
            if (Redis::type($key) == 'set') {
                $members = Redis::smembers($key);
                foreach ($members as $member) {
                    Redis::del('salesgod_crm_horizon:' . $member);
                }
            }
            Redis::del($key);
        }
    }

    public static function cleanString($string)
    {
        if (Functions::is_empty($string)) {
            return '';
        }
        $lineBreaks = array("\r\n", "\r", "\n");

        $cleanString = str_replace($lineBreaks, '', $string);

        return trim($cleanString);
    }

    public static function ip_in_range($ip, $range)
    {
        list($subnet, $mask) = explode('/', $range);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - $mask);
        $subnet &= $mask;

        return ($ip & $mask) == $subnet;
    }

    public static function getCountryCode($country_name)
    {
        $country = Country::where('name', $country_name)->get()->first();
        return $country->iso2;
    }

    public static function getStateCode($state_name, $country_code)
    {
        $state = State::where('name', $state_name)->where('country_code', $country_code)->get()->first();
        return $state->iso2;
    }

    public static function sendWorkflowMessage($workflow, $user, $contact, $from, $component, $timer, $cw_id, $queue = null)
    {
        if ($contact->status == 'blocked' || $contact->status == 'user optout' || $contact->status == 'stop word' || $user->status == 'blocked')
        {
            return 5;
        }

        if ($contact->user_id !== $user->id) {
            return 13;
        }

        $contact_workflow = DB::table('contact_workflow')->where('id', $cw_id)->get()->first();

        if (Functions::is_empty($contact_workflow) || $contact_workflow->status == 'completed') {
            return 8;
        }

        if ($contact_workflow->message_received == 1) {
            DB::table('contact_workflow')->where('workflow_id', $workflow->id)->where('contact_id', $contact->id)->where('id', $cw_id)->update(['under_process' => 0, 'status' => 'completed', 'expected_at' => null, 'added_at' => null]);
            return 12;
        }

        $messagesCount = Message::where('workflow_id', $workflow->id)->where('workflow_component_id', $component->id)->where('contact_id', $contact->id)->count();
        if ($messagesCount > 0) {
            return 9;
        }

        if (Functions::not_empty($contact->last_used_number)) {
            $newFrom = PhoneNumber::where('phone_number', $contact->last_used_number)->where('user_id', $user->id)->get()->first();
            if (Functions::not_empty($newFrom)) {
                $from = $newFrom;
            }
        }
        if (Functions::is_empty($from) || $from->user_id !== $user->id) {

            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'Unable to find the phone Number to send a message. System will retry on next go.');
            return 1;
        }

        if (Functions::not_empty($from) && $from->status != 'active') {
            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'The number used to send message is not active. System will retry on next go.');
            return 10;
        }

        $to = Functions::format_phone_number($contact->phone());
        if (Functions::is_empty($to)) {
            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'Unable to find the phone Number for the contact added in the workflow.');
            return 2;
        }

        $attachments = $component->attachments;
        $messageText = $component->value;

        $code = Functions::get_area_code_from_number($to);
        $timeOfDay = Functions::getTimeByLocation($code);

        $media_parts = 0;

        $media_parts = count($attachments);

        $messageText = Functions::processMessageText($messageText, $contact, $timeOfDay);

        $message_length = strlen($messageText);
        $message_parts = 0;
        if ($message_length > 0) {
            $message_parts = 1;
        }
        if ($message_length > 160) {
            $message_parts = ceil($message_length / 153);
        }

        $msg_type = 'sms';
        $media_text = '';
        if ($media_parts > 0) {
            $media_text = 'Media File';
            if ($media_parts > 1) {
                $media_text = 'Media Files';
            }
            $msg_type = 'mms';
        }

        $package = $user->subscription_package();
        if (Functions::is_empty($package)) {
            return 6;
        }

        if ($msg_type == 'sms' && $message_length <= 0) {
            return 11;
        }

        if ($msg_type == 'sms' && $message_length > 1500) {
            return 11;
        }

        $msgCost = Functions::get_phone_price($package, $from);
        $sms_cost = $msgCost['sms'];
        $mms_cost = $msgCost['mms'];

        $cost = ($message_parts * $sms_cost) + ($media_parts * $mms_cost);

        // $settings = $user->fetchSettings();
        // if ($settings->threshold_amount >= $newBalance && $settings->threshold_amount > 0 && $user->attempting_auto_reload == 0)
        // {
        //     event(new LowBalance($user));
        // }

        $user = User::where('id', $user->id)->get()->first();

        if (Functions::is_empty($user) || (float) $user->balance < (float) $cost) {
            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'Available Balance is not enough to send a message.');
            return 3;
        }

        $message = new Message([
            'text' => $messageText,
            'user_id' => $user->id,
            'contact_id' => $contact->id,
            'phone_number_id' => $from->id,
            'workflow_id' => $workflow->id,
            'cw_id' => $cw_id,
            'workflow_component_id' => $component->id,

            'sms_parts' => $message_parts,
            'mms_parts' => $media_parts,

            'status' => 'sending',
            'direction' => 'outbound',

            'queue_name' => $queue,

            'cost' => $cost,
            'type' => $msg_type,

            'read' => 1
        ]);

        $message->save();

        foreach ($attachments as $attachment) {
            $sourceLink = $attachment->link;
            $sourcePath = str_replace('/storage', 'public', $sourceLink);
            $absSourcePath = storage_path("app/" . $sourcePath);
            $filename = pathinfo($sourceLink, PATHINFO_BASENAME);

            $destinationPath = "public/messages/{$message->id}/{$filename}";
            $absDestinationPath = storage_path("app/" . $destinationPath);

            $destinationDirectoryPath = "public/messages/{$message->id}";
            $absDestinationDirectoryPath = storage_path("app/" . $destinationDirectoryPath);

            if (!FileModel::exists($absDestinationDirectoryPath)) {
                FileModel::makeDirectory($absDestinationDirectoryPath, 0755, true);
            }

            FileModel::copy($absSourcePath, $absDestinationPath);

            $destinationLink = str_replace('public', '/storage', $destinationPath);

            $newAttachment = new Attachment([
                'name' => $attachment->name,
                'disk_name' => $attachment->disk_name,
                'link' => $destinationLink,
                'type' => $attachment->type,
                'extension' => $attachment->extension,
                'mime' => $attachment->mime,
                'size' => $attachment->size,
            ]);

            if ($attachment->type == 'audio') {
                $media_text = 'Voice Message';
            }

            $message->attachments()->save($newAttachment);
            $newAttachment->save();
        }

        try {
            $messageResponse = app(TelnyxController::class)->sendMessage($from->phone_number, $to, $user, $message);
            // $messageResponse = 4;

            $workflow->addLog('Functions (sendWorkflowMessage) - Message Sent', [
                'workflow_id' => $workflow->id,
                'message_id' => $message->id,
                'contact_id' => $contact->id,
                'phone_number_id' => $from->id,
                'user_id' => $user->id,
                'response' => $messageResponse
            ]);

            if (Functions::is_empty($messageResponse)) {
                $message->forceDelete();
                return 15;
            }

            Functions::updateUserBalance($user, $cost);

            //$contact->phone = $to;
            
            Redis::publish("message_update", json_encode([
                "event" => 'user_' . $user->id . '_contact',
                "data" => [
                    "type" => 'MessageUpdated',
                    'content' => $contact
                ],
                "user" => $user->id,
                'type' => 'user'
            ]));

            Redis::publish("message_update", json_encode([
                "event" => 'user_' . $user->id . '_message',
                "data" => [
                    "type" => 'MessageUpdated',
                    'content' => $message->load(['attachments', 'phone_number', 'contact']),
                    'cid' => $contact->id
                ],
                "user" => $user->id,
                'type' => 'user'
            ]));
        } catch (Exception $e) {
            logger($e->getMessage() . ' - ' . $e->getCode() . ' - ' . $e->getLine() . ' - ' . $e->getTraceAsString());
            return 7;
        }

        $contact->recent_message_id = $message->id;
        $contact->last_message = $message->type == 'mms' ? $media_parts . ' ' . $media_text : (Functions::not_empty($message->text) ? Str::limit($message->text, 14, '...') : '');
        $contact->last_message_time = Carbon::now()->toDateTimeString();

        $contact->check_drip_message = 1;

        $contact->last_used_number = $from->phone_number;
        $contact->recent_msg_time = Carbon::now()->toDateTimeString();
        $contact->save();

        $contact_meta = ContactMeta::where('contact_id',$contact->id)->first();
        $contact_meta->message_sent_via_workflow = 1;
        $contact_meta->save();


        if ($timer > 1) {
            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'Contact (Lead) from Text Drip (Waitlist) is processed.', $contact->id, $from->id);
        } else {
            app(WorkflowHistoryController::class)->systemAddWorkflowHistory($workflow, 'Contact (Lead) from Text Drip is processed.', $contact->id, $from->id);
        }


        return 4;
    }

    public static function splitMessage($inputString, $chunkLength = 459)
    {
        if (strlen($inputString) > $chunkLength) {
            $chunks = str_split($inputString, $chunkLength);
            return $chunks;
        } else {
            return [$inputString];
        }
    }

    public static function subscriptionPaymentAdjustment($user, $package_id, $package_duration)
    {
        $settings = $user->fetchSettings();
        $current_duration = $settings->sub_duration;
        $current_sub_start_date = Carbon::parse($settings->sub_start_date);
        $current_date = Carbon::now();
        $delta_time = $current_date->diffInSeconds($current_sub_start_date);
        $current_subscription = $user->subscription_package();

        if (Functions::is_empty($current_subscription)) {
            return;
        }
        if (Functions::is_empty($package_id) || Functions::is_empty($package_duration)) {
            return;
        }
        if (((int) $current_subscription->id == (int) $package_id) && ($current_duration == $package_duration)) {
            return;
        }

        $current_subscription_price = 0;
        $total_seconds = 1;
        if ($current_subscription) {
            if ($current_duration == 'monthly') {
                $total_seconds = 2592000;
                $current_subscription_price = $current_subscription->monthly_price;
            } else {
                $total_seconds = 31536000;
                $current_subscription_price = $current_subscription->yearly_price;
            }
        }

        $per_second_price = $current_subscription_price / $total_seconds;
        $amount_used = $delta_time * $per_second_price;
        $amount_left = $current_subscription_price - $amount_used;

        if ($amount_left > 0) {
            app(ReceiptController::class)->createTransaction($user, $amount_left, 'credit', 'Subscription Refund', 'Refund during the Subscription Purchase', 0, '(Return) Amount left from the Previous Subscribed Package (' . time() . ')');
        }
    }

    public static function phoneNumberStats()
    {
        $phoneNumbers = PhoneNumber::where('status', 'active')->get();
        foreach ($phoneNumbers as $phoneNumber) {
            $totalMessages = Message::where('phone_number_id', $phoneNumber->id)->whereNotNull('telnyx_id')->where('direction', 'outbound')->where(function ($q) {
                $q->where('status', '!=', 'sending')->where('status', '!=', 'queued');
            })->count();

            $deliveredMessages = Message::where('phone_number_id', $phoneNumber->id)->whereNotNull('telnyx_id')->where('direction', 'outbound')->whereNull('telnyx_status')->where(function ($q) {
                $q->where('status', 'delivered')->orWhere('status', 'sent')->orWhere('status', 'delivery_unconfirmed');
            })->count();

            $spamMessage = Message::where('phone_number_id', $phoneNumber->id)->whereNotNull('telnyx_id')->where('direction', 'outbound')->whereNotNull('telnyx_status')->where(function ($q) {
                $q->where('status', '!=', 'delivered');
            })->count();

            $incomingMessage = Message::where('phone_number_id', $phoneNumber->id)->whereNotNull('telnyx_id')->where('direction', 'inbound')->count();
            $outgoingMessage = Message::where('phone_number_id', $phoneNumber->id)->whereNotNull('telnyx_id')->where('direction', 'outbound')->count();

            $io_ratio = $incomingMessage;
            if ($outgoingMessage > 0) {
                $io_ratio = $incomingMessage / $outgoingMessage;
            }

            $percent_delivered = 0;
            $percent_spam = 0;
            if ($totalMessages > 0) {
                $percent_delivered = ($deliveredMessages / $totalMessages);
                $percent_spam = ($spamMessage / $totalMessages);
            }

            $phoneNumber->inbound_outbound_ratio = $io_ratio;
            $phoneNumber->message_count = $totalMessages;
            $phoneNumber->spam_ratio = $percent_spam;
            $phoneNumber->success_ratio = $percent_delivered;
            $phoneNumber->save();
        }
    }

    public static function chunkByOuterKeys($array, $chunkSize)
    {
        $result = [];
        $currentChunk = [];
        foreach ($array as $key => $value) {
            $currentChunk[$key] = $value;
            if (count($currentChunk) === $chunkSize) {
                $result[] = $currentChunk;
                $currentChunk = [];
            }
        }
        if (!empty($currentChunk)) {
            $result[] = $currentChunk;
        }
        return $result;
    }

    public static function timeInBetween($start_time, $end_time, $timezone = 'America/New_York')
    {
        if (Functions::is_empty($start_time) || Functions::is_empty($end_time)) {
            return true;
        }
        $start_time = Carbon::createFromFormat('H:i:s', $start_time, $timezone);
        $end_time = Carbon::createFromFormat('H:i:s', $end_time, $timezone);

        // Get the current time in the specified timezone
        $current_time = Carbon::now($timezone);

        // Check if the current time is between start and end time
        $is_between = $current_time->between($start_time, $end_time);

        return $is_between;
    }

    public static function distributeItemsEqually($arrays, $itemsToTake)
    {
        $result = [];
        $deficit = 0;
        $log = [];

        // First pass: Take up to $itemsToTake items from each array
        foreach ($arrays as $index => $array) {
            // skip if array empty, add full quota to deficit
            if (empty($array)) {
                $deficit += $itemsToTake;
                continue;
            }
            // take items
            $take = min(count($array), $itemsToTake);
            for ($i = 0; $i < $take; $i++) {
                $item = $array[$i];
                $result[] = $item;
                $log[] = "Taken item $item from array $index";
            }
            $deficit += ($itemsToTake - $take);
        }

        // Second pass: Distribute the remaining quota equally
        while ($deficit > 0) {
            $totalDeficitFilled = 0;
            foreach ($arrays as $index => &$array) {
                // skip if deficit filled
                if ($deficit <= 0) {
                    break;
                }
                // skip if array empty or all items already taken in first pass
                if (empty($array) || count($array) <= $itemsToTake) {
                    continue;
                }
                // take items
                $remainingItems = array_slice($array, $itemsToTake);
                if (count($remainingItems) > 0) {
                    $item = array_shift($remainingItems);
                    $result[] = $item;
                    $log[] = "Taken item $item from array $index";
                    $deficit--;
                    $totalDeficitFilled++;
                }
                $array = array_merge(array_slice($array, 0, $itemsToTake), $remainingItems);
            }
            // If no more items can be taken to fill the deficit, break the loop
            if ($totalDeficitFilled == 0) {
                break;
            }
        }

        /* foreach ($log as $entry) {
            echo $entry . PHP_EOL;
        } */

        return $result;

    }
}
