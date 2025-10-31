<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OpenAIController extends Controller
{
    public function generate(Request $request)
    {
        $prompt = $request->input('prompt');

        if (!$prompt) {
            return response()->json(['error' => 'Missing prompt'], 400);
        }

        $apiKey = env('OPENAI_API_KEY');

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model'       => 'gpt-4o-mini',
            'messages'    => [
                ['role' => 'system', 'content' => 'You are a property sales assistant generating personalized outreach messages.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'temperature' => 0.7,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'OpenAI request failed'], $response->status());
        }

        return response()->json($response->json());
    }
}
