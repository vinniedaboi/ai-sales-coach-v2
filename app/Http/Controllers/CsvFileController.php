<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CsvFile; // Import the model

class CsvFileController extends Controller
{
    public function upload(Request $request)
    {
        // 1. Validation: Ensures it's a file and a valid type
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        // 2. Storage
        try {
            $path = $request->file('csv_file')->store('csv_uploads');

            // 3. Database Record
            CsvFile::create([
                'original_name' => $request->file('csv_file')->getClientOriginalName(),
                'stored_path' => $path, // e.g., 'csv_uploads/randomfilename.csv'
            ]);

            return response()->json([
                'message' => 'File uploaded and stored successfully.',
                'path' => $path
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'File upload failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listFiles()
    {
        $files = CsvFile::orderBy('created_at', 'desc')->get();
        return response()->json($files);
    }


public function processSelectedFile(Request $request)
{
    $request->validate(['selected_csv_path' => 'required|string']);
    $storedPath = $request->input('selected_csv_path');

    // 1. Path validation
    if (!Storage::exists($storedPath)) {
        return response()->json(['message' => 'Error: File not found on storage disk.'], 404);
    }

    try {
        $fileContent = Storage::get($storedPath);
        
        return response()->json([
            'message' => 'File content retrieved successfully for frontend processing.',

            'csv_content' => $fileContent, 
            'path' => $storedPath
        ], 200);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'FATAL ERROR: Could not read file content from storage.',
            'error_detail' => $e->getMessage(),
        ], 500);
    }
}
}