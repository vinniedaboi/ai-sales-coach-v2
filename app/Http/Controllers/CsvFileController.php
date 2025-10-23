<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\CsvFile; // Import the model

class CsvFileController extends Controller
{
    // =========================================================
    // 1. UPLOAD AND STORE (Called by your frontend JS)
    // =========================================================
    public function upload(Request $request)
    {
        // 1. Validation: Ensures it's a file and a valid type
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ]);

        // 2. Storage
        try {
            // Store the file in storage/app/csv_uploads (or storage/app/public/csv_uploads if you uncomment the disk)
            // Storing on the default 'local' disk is most secure for private data.
            $path = $request->file('csv_file')->store('csv_uploads');

            // 3. Database Record
            CsvFile::create([
                'original_name' => $request->file('csv_file')->getClientOriginalName(),
                'stored_path' => $path, // e.g., 'csv_uploads/randomfilename.csv'
                // 'user_id' => auth()->id(), // Uncomment if using authentication
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

    // =========================================================
    // 2. RETRIEVE FILE LIST (For the dropdown menu)
    // =========================================================
    public function listFiles()
    {
        // Retrieve all files stored in the database
        $files = CsvFile::orderBy('created_at', 'desc')->get();

        // Pass the list to the view (or return as JSON if you are using an API for the selection page)
        return response()->json($files);
        
        // For a JSON API endpoint to populate the dropdown via AJAX:
        // return response()->json($files);
    }

    // app/Http/Controllers/CsvFileController.php

// app/Http/Controllers/CsvFileController.php

public function processSelectedFile(Request $request)
{
    $request->validate(['selected_csv_path' => 'required|string']);
    $storedPath = $request->input('selected_csv_path');

    // 1. Path validation
    if (!Storage::exists($storedPath)) {
        return response()->json(['message' => 'Error: File not found on storage disk.'], 404);
    }

    try {
        // 2. Retrieve the ENTIRE file content as a string
        $fileContent = Storage::get($storedPath);
        
        // 3. Return the raw content for frontend processing
        return response()->json([
            'message' => 'File content retrieved successfully for frontend processing.',
            // CRUCIAL: Send the raw text content back to the client
            'csv_content' => $fileContent, 
            'path' => $storedPath
        ], 200);

    } catch (\Exception $e) {
        // Server failed to read the file, but not due to bad logic
        return response()->json([
            'message' => 'FATAL ERROR: Could not read file content from storage.',
            'error_detail' => $e->getMessage(),
        ], 500);
    }
}
}