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

        $insights = $this->loadInsights();

        $initialCount = count($insights);
        $insights = array_filter($insights, function ($item) use ($id) {
            // Check the 'id' field in the item structure
            return $item['id'] !== $id;
        });


        if (count($insights) === $initialCount) {
            return response()->json(['message' => 'Insight not found.'], 404);
        }

        $this->saveInsights($insights);

        return response()->json(['message' => 'Insight deleted successfully'], 200);
    }

    // --- SAVE LOGIC (POST /api/store-insight-temp) ---
    public function store(Request $request)
    {
        $request->validate([
            'user_email' => 'required|email',
            'insight' => 'nullable|string',
            'action' => 'nullable|string',
        ]);

        $insights = $this->loadInsights();

        $insights[] = $request->all();

        $this->saveInsights($insights);

        return response()->json(['message' => 'Insight saved to file', 'data' => $request->all()], 201);
    }
    public function update(Request $request, $id)
    {
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

        if ($request->input('id') !== $id) {
            return response()->json(['message' => 'Mismatched item ID in request.'], 400);
        }

        $insights = $this->loadInsights();

        $updated = false;
        $requestData = $request->all(); // Get all validated request data


        foreach ($insights as $key => $item) {
            if ($item['id'] === $id) {
                $insights[$key] = $requestData;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            return response()->json(['message' => 'Insight not found for update.'], 404);
        }


        $this->saveInsights($insights);

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
