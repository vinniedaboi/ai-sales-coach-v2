<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class TemporaryInsightController extends Controller
{
    private const FILE_PATH = 'insights/temp_insights.json';
    public function destroy($id)
    {
        // 1. Load existing data
        $insights = $this->loadInsights();
        
        // 2. Filter the array to remove the item with the matching ID
        $initialCount = count($insights);
        $insights = array_filter($insights, function ($item) use ($id) {
            // Check the 'id' field in the item structure
            return $item['id'] !== $id;
        });
        
        // 3. Check if an item was actually removed
        if (count($insights) === $initialCount) {
            return response()->json(['message' => 'Insight not found.'], 404);
        }

        // 4. Save the filtered data back to the file
        $this->saveInsights($insights);

        return response()->json(['message' => 'Insight deleted successfully'], 200);
    }

    // --- SAVE LOGIC (POST /api/store-insight-temp) ---
    public function store(Request $request)
    {
        // 1. Validate incoming data (essential for any Laravel route)
        $request->validate([
            'user_email' => 'required|email',
            'insight' => 'nullable|string',
            'action' => 'nullable|string',
            // ... include validation for other fields ...
        ]);
        
        // 2. Load existing data
        $insights = $this->loadInsights();

        // 3. Add new insight
        $insights[] = $request->all();

        // 4. Save back to file
        $this->saveInsights($insights);

        return response()->json(['message' => 'Insight saved to file', 'data' => $request->all()], 201);
    }
    public function update(Request $request, $id)
{
    // 1. Validation 
    // Always validate data coming from the frontend
    $request->validate([
        'id' => 'required|string', // Ensure the ID in the payload matches the route ID
        'user_email' => 'required|email',
        'insight' => 'nullable|string',
        'action' => 'nullable|string',
        'assignee' => 'nullable|string',
        'priority' => 'required|string',
        'due' => 'nullable|date', // Use 'due' to match the frontend 'item' structure
        'revenue' => 'nullable|numeric',
        'status' => 'required|string',
        'tab_context' => 'required|string',
    ]);
    
    // Safety check: Ensure the ID in the URL matches the ID in the payload
    if ($request->input('id') !== $id) {
        return response()->json(['message' => 'Mismatched item ID in request.'], 400);
    }
    
    // 2. Load existing data from file
    $insights = $this->loadInsights();
    
    $updated = false;
    $requestData = $request->all(); // Get all validated request data
    
    // 3. Find and replace the item in the array
    foreach ($insights as $key => $item) {
        if ($item['id'] === $id) {
            // Overwrite the old item with the new request data
            // Since the frontend sends the complete updated item, we replace the entry.
            $insights[$key] = $requestData; 
            $updated = true;
            break;
        }
    }
    
    if (!$updated) {
        return response()->json(['message' => 'Insight not found for update.'], 404);
    }

    // 4. Save the updated data back to the file
    $this->saveInsights($insights);

    // 5. Success Response
    return response()->json(['message' => 'Insight updated successfully', 'data' => $requestData], 200);
}

    // --- RETRIEVE LOGIC (GET /api/get-insights-temp) ---
    public function index()
    {
        // Simply return all insights from the file
        return response()->json($this->loadInsights());
    }

    // --- HELPER FUNCTIONS ---
    private function loadInsights(): array
    {
        // Ensure the directory exists
        if (!Storage::exists('insights')) {
            Storage::makeDirectory('insights');
        }

        // Check if the file exists and return content, or return an empty array
        if (Storage::exists(self::FILE_PATH)) {
            $content = Storage::get(self::FILE_PATH);
            return json_decode($content, true) ?: [];
        }

        return [];
    }

    private function saveInsights(array $data): void
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        Storage::put(self::FILE_PATH, $jsonContent);
    }
}