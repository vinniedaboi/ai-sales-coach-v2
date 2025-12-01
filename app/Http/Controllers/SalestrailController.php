<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SalestrailController extends Controller
{
    public function getCalls(Request $request)
    {
        $from = $request->query('from', '2025-06-01T00:00:00.000Z');
        $to   = $request->query('to',   '2025-12-01T00:00:00.000Z');

        $username = "adb6b54d-3e23-47dc-8b85-f8ea2332c36f";
        $password = "dwi8Os4TySSY51YHJourNEarRkNqWp7AJGtxeSoWnCKIdLrkwrDFKv45DQvkuuox";

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
