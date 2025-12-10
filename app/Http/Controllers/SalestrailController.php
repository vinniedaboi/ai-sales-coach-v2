<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class SalestrailController extends Controller
{
    public function getCalls(Request $request)
    {
        // Default FROM date
        $defaultFrom = '2025-06-01T00:00:00.000Z';

        // Normalize "from"
        try {
            $from = Carbon::parse(
                $request->query('from', $defaultFrom)
            )->toJSON(); // <-- FIXED
        } catch (\Exception $e) {
            $from = Carbon::parse($defaultFrom)->toJSON(); // fallback
        }

        // "to" = NOW in correct Z format
        $to = Carbon::now('UTC')->toJSON(); // <-- FIXED

        $username = "c7a5829c-3b1f-4226-914c-eaea93bd6b1e";
        $password = "MowRouieNvmJPCkHi3ldZvlcNH2JF1k6Rr19iHm9SgEMRrE6QF9IJAzne5h5eYJp";

        $url = "https://standalone-api.salestrail.io/export/calls/json";

        $response = Http::withBasicAuth($username, $password)
            ->timeout(20)
            ->acceptJson()
            ->get($url, [
                "from" => $from,
                "to"   => $to
            ]);

        if ($response->failed()) {
            return response()->json([
                "error" => "Salestrail request failed",
                "status" => $response->status(),
                "body" => $response->body()
            ], 500);
        }

        return $response->json();
    }
}
