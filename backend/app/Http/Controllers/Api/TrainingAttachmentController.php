<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingAttachmentRequest;
use App\Http\Resources\TrainingAttachmentResource;
use App\Models\TrainingAttachment;
use App\Models\TrainingLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Training Attachment Controller
 *
 * Handles file uploads for training logs (photos, videos, documents).
 */
class TrainingAttachmentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of attachments for a training log.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TrainingAttachment::query()->with('trainingLog');

        // Filter by training log
        if ($request->has('trainingLogId')) {
            $query->where('training_log_id', $request->input('trainingLogId'));
        }

        // Filter by file type
        if ($request->has('fileType')) {
            $query->where('file_type', $request->input('fileType'));
        }

        // Authorization: customers only see their own dog's attachments
        if ($request->user()->role === 'customer') {
            $query->whereHas('trainingLog', function ($q) use ($request) {
                $q->whereHas('dog', function ($dogQuery) use ($request) {
                    $dogQuery->where('customer_id', $request->user()->customer->id);
                });
            });
        }

        return TrainingAttachmentResource::collection(
            $query->orderBy('uploaded_at', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly uploaded attachment.
     *
     * @param StoreTrainingAttachmentRequest $request
     * @return JsonResponse
     */
    public function store(StoreTrainingAttachmentRequest $request): JsonResponse
    {
        $this->authorize('create', TrainingAttachment::class);

        $validated = $request->validated();
        $file = $request->file('file');

        // Determine file type based on MIME type
        $mimeType = $file->getMimeType();
        $fileType = $this->getFileTypeFromMime($mimeType);

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $filename);
        $uniqueFilename = $filename . '_' . time() . '.' . $extension;

        // Store file
        $path = $file->storeAs(
            'training-attachments/' . $validated['training_log_id'],
            $uniqueFilename,
            'public'
        );

        // Create attachment record
        $attachment = TrainingAttachment::create([
            'training_log_id' => $validated['training_log_id'],
            'file_type' => $fileType,
            'file_path' => $path,
            'file_name' => $originalName,
            'uploaded_at' => now(),
        ]);

        $attachment->load('trainingLog');

        return (new TrainingAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified attachment.
     *
     * @param TrainingAttachment $trainingAttachment
     * @return TrainingAttachmentResource
     */
    public function show(TrainingAttachment $trainingAttachment): TrainingAttachmentResource
    {
        $this->authorize('view', $trainingAttachment);

        $trainingAttachment->load('trainingLog');

        return new TrainingAttachmentResource($trainingAttachment);
    }

    /**
     * Download the specified attachment.
     *
     * @param TrainingAttachment $trainingAttachment
     * @return StreamedResponse
     */
    public function download(TrainingAttachment $trainingAttachment): StreamedResponse
    {
        $this->authorize('view', $trainingAttachment);

        if (!Storage::disk('public')->exists($trainingAttachment->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download(
            $trainingAttachment->file_path,
            $trainingAttachment->file_name
        );
    }

    /**
     * Remove the specified attachment.
     *
     * @param TrainingAttachment $trainingAttachment
     * @return JsonResponse
     */
    public function destroy(TrainingAttachment $trainingAttachment): JsonResponse
    {
        $this->authorize('delete', $trainingAttachment);

        // Delete file from storage
        if (Storage::disk('public')->exists($trainingAttachment->file_path)) {
            Storage::disk('public')->delete($trainingAttachment->file_path);
        }

        $trainingAttachment->delete();

        return response()->json(null, 204);
    }

    /**
     * Get file type from MIME type.
     *
     * @param string $mimeType
     * @return string
     */
    private function getFileTypeFromMime(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        return 'document';
    }
}
