<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GoogleController extends Controller
{
    private $jwtSecret;

    public function __construct()
    {
        $this->jwtSecret = env('JWT_SECRET');
    }

    /**
     * Extract user from JWT token in Authorization header.
     */
    private function getUserFromJWT(Request $req)
    {
        $auth = $req->header('Authorization') ?? $req->query('auth');
        if (!$auth) return null;
        $token = str_replace('Bearer ', '', $auth);
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return User::find($decoded->sub);
        } catch (\Exception $e) {
            Log::error("JWT decode failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize Google Client
     */
    private function getGoogleClient()
    {
        $client = new GoogleClient();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(Gmail::GMAIL_READONLY);
        $client->addScope(Gmail::GMAIL_SEND);
        $client->addScope('email');
        $client->addScope('profile');
        return $client;
    }

public function redirectToGoogle(Request $req)
{
    $authHeader = $req->header('Authorization') ?? $req->query('auth');
    if (!$authHeader) {
        return response()->json(['error' => 'Missing JWT'], 401);
    }

    try {
        $decoded = JWT::decode(str_replace('Bearer ', '', $authHeader), new Key($this->jwtSecret, 'HS256'));
        $user = User::find($decoded->sub);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Invalid JWT'], 401);
    }

    if (!$user) return response()->json(['error' => 'User not found'], 404);

    // Build URL manually to avoid duplicate "state"
    $params = [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'scope' => implode(' ', [
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'email',
            'profile',
        ]),
        'state' => base64_encode($authHeader),
    ];

    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    Log::info('Redirecting to Google OAuth (manual):', ['url' => $authUrl]);

    return redirect()->away($authUrl);
}




    public function handleCallback(Request $req)
    {
        $client = $this->getGoogleClient();
        $code = $req->get('code');
        $state = $req->get('state');
        $authHeader = base64_decode($state ?? '');

        if (!$authHeader) return response('Missing state or JWT', 400);

        $jwt = str_replace('Bearer ', '', $authHeader);
        try {
            $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
            $user = User::find($decoded->sub);
        } catch (\Exception $e) {
            return response('Invalid token', 401);
        }

        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            return response()->json(['error' => $token['error_description'] ?? 'OAuth failed'], 400);
        }

        $user->google_access_token = $token['access_token'];
        $user->google_refresh_token = $token['refresh_token'] ?? $user->google_refresh_token;
        $user->google_token_expires_at = now()->addSeconds($token['expires_in']);
        $user->save();

        return redirect('/?google=connected');
    }

    /**
     * Ensure valid access token (refresh if expired)
     */
    private function getValidAccessToken(User $user)
    {
        $client = $this->getGoogleClient();

        if (!$user->google_access_token) return null;

        $client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => Carbon::parse($user->google_token_expires_at)->diffInSeconds(now(), false),
        ]);

        // Refresh token if expired
        if ($client->isAccessTokenExpired()) {
            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                if (isset($newToken['access_token'])) {
                    $user->google_access_token = $newToken['access_token'];
                    $user->google_token_expires_at = now()->addSeconds($newToken['expires_in'] ?? 3600);
                    $user->save();
                    return $newToken['access_token'];
                }
            } catch (\Exception $e) {
                Log::error("Token refresh failed: " . $e->getMessage());
                return null;
            }
        }

        return $user->google_access_token;
    }


    public function getEmails(Request $req)
{
    $user = $this->getUserFromJWT($req);
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $leadEmail = $req->query('email');
    if (!$leadEmail) {
        return response()->json(['error' => 'Missing lead email'], 400);
    }

    $accessToken = $this->getValidAccessToken($user);
    if (!$accessToken) {
        return response()->json(['error' => 'Gmail not connected'], 401);
    }

    // Initialize Google client
    $client = $this->getGoogleClient();
    $client->setAccessToken($accessToken);

    $service = new Gmail($client);


    // This finds messages where the leadEmail appears in either to/from/cc/bcc
    $query = "from:{$leadEmail} OR to:{$leadEmail}";

    $response = $service->users_messages->listUsersMessages('me', [
        'maxResults' => 10,
        'q' => $query,
    ]);

    $messages = [];

    if ($response->getMessages()) {
        foreach ($response->getMessages() as $msg) {
            $fullMessage = $service->users_messages->get('me', $msg->getId(), ['format' => 'metadata', 'metadataHeaders' => ['Subject', 'From', 'Date']]);

            $headers = collect($fullMessage->getPayload()->getHeaders())
                ->mapWithKeys(fn($h) => [$h->getName() => $h->getValue()]);

            $messages[] = [
                'id' => $msg->getId(),
                'subject' => $headers['Subject'] ?? '(No Subject)',
                'from' => $headers['From'] ?? '',
                'date' => $headers['Date'] ?? '',
                'snippet' => $fullMessage->getSnippet(),
            ];
        }
    }

    return response()->json($messages);
}


    public function sendEmail(Request $req)
{
    $user = $this->getUserFromJWT($req);
    if (!$user) return response()->json(['error' => 'Unauthenticated'], 401);

    $accessToken = $this->getValidAccessToken($user);
    if (!$accessToken) return response()->json(['error' => 'Gmail not connected'], 401);

    $req->validate([
        'to' => 'required|email',
        'subject' => 'required|string',
        'body' => 'required|string',
        'attachments.*' => 'file|max:5120', // 5MB max per file
    ]);

    $client = $this->getGoogleClient();
    $client->setAccessToken($accessToken);
    $service = new Gmail($client);

    $boundary = uniqid('np');

    // HTML + plain text version
    $plainTextBody = strip_tags($req->body);
    $htmlBody = $req->body; 

    $rawMessage = "To: {$req->to}\r\n";
    $rawMessage .= "Subject: {$req->subject}\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
    $rawMessage .= "--$boundary\r\n";
    $rawMessage .= "Content-Type: multipart/alternative; boundary=\"alt-$boundary\"\r\n\r\n";
    $rawMessage .= "--alt-$boundary\r\n";
    $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $rawMessage .= "$plainTextBody\r\n\r\n";
    $rawMessage .= "--alt-$boundary\r\n";
    $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $rawMessage .= "$htmlBody\r\n\r\n";
    $rawMessage .= "--alt-$boundary--\r\n";

    // Attach files (if any)
    if ($req->hasFile('attachments')) {
        foreach ($req->file('attachments') as $file) {
            $fileData = base64_encode(file_get_contents($file->getRealPath()));
            $fileName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();

            $rawMessage .= "--$boundary\r\n";
            $rawMessage .= "Content-Type: $mimeType; name=\"$fileName\"\r\n";
            $rawMessage .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
            $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $rawMessage .= chunk_split($fileData, 76, "\r\n") . "\r\n";
        }
    }

    $rawMessage .= "--$boundary--";

    $encodedMessage = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

    try {
        $message = new \Google\Service\Gmail\Message();
        $message->setRaw($encodedMessage);
        $service->users_messages->send('me', $message);
        return response()->json(['message' => 'Email sent successfully']);
    } catch (\Exception $e) {
        \Log::error("Gmail send failed: " . $e->getMessage());
        return response()->json(['error' => 'Failed to send email'], 500);
    }
}


    public function disconnect(Request $req)
    {
        $user = $this->getUserFromJWT($req);
        if (!$user) return response()->json(['error' => 'Unauthenticated'], 401);

        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        return response()->json(['message' => 'Disconnected from Gmail']);
    }
}
