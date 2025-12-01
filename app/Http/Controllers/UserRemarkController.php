<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserRemarkController extends Controller
{
    private const FILE_PATH = 'remarks/user_remarks.json';

    /** GET: /api/user-remarks/{email} */
    public function show($email)
    {
        $remarks = $this->loadRemarks();

        foreach ($remarks as $item) {
            if ($item['email'] === $email) {
                return response()->json($item);
            }
        }

        return response()->json(['remarks' => ""], 200);
    }

    /** POST: /api/user-remarks/{email} */
    public function save(Request $request, $email)
    {
        $request->validate([
            'remarks' => 'nullable|string'
        ]);

        $remarks = $this->loadRemarks();
        $found = false;

        // Identify the user writing the remark
        $writerName = $request->input('writer_name') ?? 'Unknown';
        $writerEmail = $request->input('writer_email') ?? 'unknown@example.com';

        foreach ($remarks as &$item) {
            if ($item['email'] === $email) {

                $item['remarks'] = $request->input('remarks') ?? "";
                $item['timestamp'] = now()->toDateTimeString();
                $item['by'] = [
                    'name' => $writerName,
                    'email' => $writerEmail
                ];

                $found = true;
                break;
            }
        }

        if (!$found) {
            $remarks[] = [
                'email' => $email,
                'remarks' => $request->input('remarks') ?? "",
                'timestamp' => now()->toDateTimeString(),
                'by' => [
                    'name' => $writerName,
                    'email' => $writerEmail
                ]
            ];
        }

        $this->saveRemarks($remarks);

        return response()->json(['status' => 'ok']);
    }


    private function loadRemarks(): array
    {
        if (!Storage::exists('remarks')) {
            Storage::makeDirectory('remarks');
        }

        if (!Storage::exists(self::FILE_PATH)) {
            return [];
        }

        return json_decode(Storage::get(self::FILE_PATH), true) ?: [];
    }

    public function all()
    {
        if (!Storage::exists('remarks/user_remarks.json')) {
            return response()->json([]);
        }

        $data = json_decode(Storage::get('remarks/user_remarks.json'), true);

        // MUST return normalized data for frontend
        return response()->json($data);
    }


    private function saveRemarks(array $data): void
    {
        Storage::put(self::FILE_PATH, json_encode($data, JSON_PRETTY_PRINT));
    }
}
