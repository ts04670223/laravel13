<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = $request->user()
            ->documents()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'required|in:file,url,database',
            'title' => 'required|string|max:255',
            'file' => 'required_if:source_type,file|file|max:10240|mimes:pdf,txt,md,docx',
            'url' => 'required_if:source_type,url|url|max:2048',
            'content' => 'required_if:source_type,database|string',
        ]);

        $document = new Document([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'source_type' => $validated['source_type'],
            'status' => 'pending',
        ]);

        match ($validated['source_type']) {
            'file' => $this->handleFileUpload($request, $document),
            'url' => $document->source_path = $validated['url'],
            'database' => $document->metadata = ['content' => $validated['content']],
        };

        $document->save();
        ProcessDocument::dispatch($document);

        return response()->json($document, 201);
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        if ($document->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($document->source_type === 'file' && $document->source_path) {
            Storage::disk('private')->delete($document->source_path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    private function handleFileUpload(Request $request, Document $document): void
    {
        $file = $request->file('file');
        $path = $file->store('documents');

        $document->source_path = $path;
        $document->mime_type = $file->getMimeType();
    }
}
