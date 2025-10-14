<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    /**
     * Map model name to API credentials.
     */
    private function resolveModelConfig(string $model): array
    {
        switch ($model) {
            case 'claude':
                return [
                    'key' => env('CLAUDE_API_KEY'),
                    'url' => env('CLAUDE_API_URL', 'https://api.anthropic.com/v1/messages'),
                    'type' => 'claude'
                ];
            case 'deepseek':
                return [
                    'key' => env('DEEPSEEK_API_KEY'),
                    'url' => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/chat/completions'),
                    'type' => 'deepseek'
                ];
            case 'chatgpt':
                return [
                    'key' => env('OPENAI_API_KEY'),
                    'url' => env('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions'),
                    'type' => 'chatgpt'
                ];
            case 'alibaba':
                return [
                    'key' => env('ALIBABA_API_KEY'),
                    'url' => env('ALIBABA_API_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions'),
                    'type' => 'alibaba'
                ];
            default:
                // Default Gemini
                return [
                    'key' => env('GEMINI_API_KEY'),
                    'url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent'),
                    'type' => 'gemini'
                ];
        }
    }

    /**
     * Generic model request handler
     */
    private function callModel(string $model, string $prompt)
    {
        $config = $this->resolveModelConfig($model);

        switch ($config['type']) {
            case 'claude':
                $response = Http::withHeaders([
                    'x-api-key' => $config['key'],
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout(30)->post($config['url'], [
                            'model' => 'claude-3-opus-20240229',
                            'max_tokens' => 500,
                            'messages' => [['role' => 'user', 'content' => $prompt]],
                        ]);
                return data_get($response->json(), 'content.0.text', 'Hello?');

            case 'deepseek':
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $config['key'],
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($config['url'], [
                            'model' => 'deepseek-chat',
                            'messages' => [['role' => 'user', 'content' => $prompt]],
                            'max_tokens' => 500,
                        ]);
                return data_get($response->json(), 'choices.0.message.content', 'Hello?');

            case 'chatgpt':
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $config['key'],
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($config['url'], [
                            'model' => 'gpt-4o-mini',
                            'messages' => [['role' => 'user', 'content' => $prompt]],
                            'max_tokens' => 500,
                        ]);
                return data_get($response->json(), 'choices.0.message.content', 'Hello?');

            case 'alibaba':
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $config['key'],
                    'Content-Type' => 'application/json'
                ])->timeout(30)->post($config['url'], [
                            'model' => 'qwen-plus',
                            'messages' => [
                                ['role' => 'system', 'content' => 'You are an assistant.'],
                                ['role' => 'user', 'content' => $prompt]
                            ],
                            'max_tokens' => 500,
                        ]);

                Log::info('[Alibaba Qwen Raw Response]', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);

                return data_get($response->json(), 'choices.0.message.content', 'Hello?');


            case 'gemini':
                $response = Http::timeout(30)->post($config['url'] . '?key=' . $config['key'], [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                ]);
                return data_get($response->json(), 'candidates.0.content.parts.0.text', 'Hello?');
        }

        return 'Hello?';
    }

    /**
     * Start a new AI sales roleplay session
     */
    public function startSession(Request $request)
    {
        try {
            $role = $request->input('role', 'prospect');
            $product = $request->input('product', 'a generic software product');
            $customPrompt = $request->input('custom_prompt', '');
            $model = $request->input('model', 'gemini');

            $prompt = trim($customPrompt) ?: <<<PROMPT
You are role-playing a **potential client** in a realistic B2B cold-call sales simulation.

🎭 **Your Character**
- Role: $role, you are a client and the caller is trying to sell you a product
- Company: a mid-sized business that could reasonably need $product
- Personality: realistic, polite but busy, a little skeptical of sales calls
- Goal: respond naturally as if this were a real phone call — not as an AI, and not breaking character

📞 **Context**
A salesperson will call you to offer $product.
Start the call the way a real prospect would — short, natural, and a bit guarded.
You might ask who they are, what the company does, or why they called you.
Keep your tone conversational, human, and emotionally believable.

🚫 **Avoid**
- Mentioning that you are an AI or in a simulation.
- Using phrases like “As an AI…” or “In this simulation...”
- Over-explaining; prefer brief, human-sounding replies (1–3 sentences max).

🎯 **Your first message**
Start the conversation like a real person answering a call — e.g. “Hello?” or “Yes, who's this?”, and do NOT use context brackets such as [Your Name].
PROMPT;

            $message = $this->callModel($model, $prompt);

            Log::info("[$model] startSession response", ['message' => $message]);

            return response()->json(['message' => $message]);
        } catch (\Throwable $e) {
            Log::error('Start session failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to start session'], 500);
        }
    }

    /**
     * Continue chat interaction
     */
    public function chat(Request $request)
    {
        try {
            $history = $request->input('history', []);
            $userInput = $request->input('user_input', '');
            $role = $request->input('role', 'prospect');
            $product = $request->input('product', 'a generic software product');
            $customPrompt = $request->input('custom_prompt', '');
            $model = $request->input('model', 'gemini');

            $formattedHistory = collect($history)
                ->map(fn($m) => "{$m['role']}: {$m['content']}")
                ->join("\n");

            $prompt = trim($customPrompt)
                ? $customPrompt . "\n\nConversation so far:\n$formattedHistory\n\nUser: $userInput"
                : <<<PROMPT
You are continuing a realistic sales call roleplay as a human prospect.

- Your role: $role
- The product being offered: $product

Conversation so far:
$formattedHistory

Sales: $userInput

Now reply naturally and concisely as a real human $role, continuing the conversation.
Avoid sounding like an AI or giving meta comments.
PROMPT;

            $reply = $this->callModel($model, $prompt);

            Log::info("[$model] chat response", ['reply' => $reply]);

            return response()->json(['reply' => $reply]);
        } catch (\Throwable $e) {
            Log::error('Chat generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Chat generation failed'], 500);
        }
    }

    /**
     * Generate AI scorecard evaluation
     */
    public function scorecard(Request $request)
    {
        try {
            $transcript = $request->input('transcript', '');
            $model = $request->input('model', 'gemini');

            $prompt = <<<PROMPT
Analyze the following sales call transcript and provide a score from 1–10 for each category.
Return *valid JSON only*.

Transcript:
$transcript

Return JSON with:
{
  "overall_score": number,
  "communication": {"score": number, "remarks": string},
  "objection_handling": {"score": number, "remarks": string},
  "closing": {"score": number, "remarks": string},
  "summary": string
}
PROMPT;

            $rawText = $this->callModel($model, $prompt);
            preg_match('/\{[\s\S]*\}/', $rawText, $matches);
            $jsonOutput = isset($matches[0]) ? json_decode($matches[0], true) : ['error' => 'Invalid JSON'];
            Log::info('[Scorecard] Generated', [
                'model' => $model,
                'raw_text' => $rawText,
                'parsed_json' => $jsonOutput
            ]);
            return response()->json($jsonOutput);
        } catch (\Throwable $e) {
            Log::error('Scorecard generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Scorecard generation failed'], 500);
        }
    }

    // ✅ STT and TTS remain unchanged
    public function speechToText(Request $request)
    {
        try {
            $audioFile = $request->file('audio');
            if (!$audioFile) {
                return response()->json(['error' => 'No audio file uploaded'], 400);
            }

            $apiKey = env('GOOGLE_CLOUD_API_KEY');
            $audioData = base64_encode(file_get_contents($audioFile->getRealPath()));

            $payload = [
                "config" => [
                    "encoding" => "WEBM_OPUS",
                    "languageCode" => "en-US",
                    "alternativeLanguageCodes" => ["zh-CN", "en-US"],
                ],
                "audio" => ["content" => $audioData]
            ];

            $response = Http::timeout(30)
                ->post("https://speech.googleapis.com/v1/speech:recognize?key={$apiKey}", $payload);

            $transcript = data_get($response->json(), 'results.0.alternatives.0.transcript', '');
            Log::info('[STT] Speech-to-text result', [
                'file_name' => $audioFile->getClientOriginalName(),
                'transcript' => $transcript
            ]);
            return response()->json(['text' => $transcript]);
        } catch (\Throwable $e) {
            Log::error('🎤 [STT] Speech-to-text failed: ' . $e->getMessage());
            return response()->json(['error' => 'Speech-to-text failed'], 500);
        }
    }

    public function textToSpeech(Request $request)
{
    try {
        $text = $request->input('text', '');
        if (!$text) {
            return response()->json(['error' => 'No text provided'], 400);
        }

        $apiKey = env('GOOGLE_CLOUD_API_KEY');

        // 🧠 Auto-detect language based on text content
        // If the text contains Chinese characters, use Mandarin voice
        if (preg_match('/\p{Han}/u', $text)) {
            $language = 'cmn-CN';
            $voiceName = 'cmn-CN-Wavenet-A';
        } else {
            $language = 'en-US';
            $voiceName = 'en-US-Wavenet-D';
        }

        $payload = [
            "input" => ["text" => $text],
            "voice" => [
                "languageCode" => $language,
                "name" => $voiceName
            ],
            "audioConfig" => ["audioEncoding" => "MP3"]
        ];

        $response = Http::timeout(30)
            ->post("https://texttospeech.googleapis.com/v1/text:synthesize?key={$apiKey}", $payload);

        $audioContent = data_get($response->json(), 'audioContent');
        if (!$audioContent) {
            Log::error('TTS failed', ['response' => $response->json()]);
            return response()->json(['error' => 'Failed to generate speech'], 500);
        }

        return response()->json([
            'audio' => 'data:audio/mp3;base64,' . $audioContent
        ]);
    } catch (\Throwable $e) {
        Log::error('🔊 [TTS] Text-to-speech failed: ' . $e->getMessage());
        return response()->json(['error' => 'Text-to-speech failed'], 500);
    }
}

}
