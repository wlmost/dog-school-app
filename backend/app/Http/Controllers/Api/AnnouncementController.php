<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Announcement Controller
 *
 * Handles CRUD operations for announcements. The public endpoint exposes
 * only currently active announcements, while all admin endpoints require
 * an admin user and expose the full history (active and expired).
 */
class AnnouncementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of currently active announcements.
     *
     * Unauthenticated/public endpoint, used by the landing page banner.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        $announcements = Announcement::query()
            ->active()
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }

    /**
     * Display a listing of all announcements, including expired ones.
     *
     * Admin-only endpoint used by the announcement management area.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Announcement::class);

        return AnnouncementResource::collection(
            Announcement::query()->orderByDesc('created_at')->get()
        );
    }

    /**
     * Store a newly created announcement.
     *
     * Authorization is enforced by StoreAnnouncementRequest::authorize().
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validatedSnakeCase();

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request);
        }

        $announcement = Announcement::create($data);

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    /**
     * Update the specified announcement.
     *
     * Replaces the stored image (deleting the previous one from disk) when
     * a new image is uploaded.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $this->authorize('update', $announcement);

        $data = $request->validatedSnakeCase();

        if ($request->hasFile('image')) {
            $this->deleteImageIfExists($announcement);
            $data['image_path'] = $this->storeImage($request);
        }

        $announcement->update($data);

        return new AnnouncementResource($announcement->fresh());
    }

    /**
     * Remove the specified announcement and its associated image from disk.
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $this->deleteImageIfExists($announcement);

        $announcement->delete();

        return response()->json(null, 204);
    }

    /**
     * Store the uploaded image on the public disk and return its path.
     */
    private function storeImage(Request $request): string
    {
        $file = $request->file('image');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = 'announcement_'.Str::uuid().'.'.$extension;

        return $file->storeAs('announcement-images', $filename, 'public');
    }

    /**
     * Delete the announcement's current image from the public disk, if any.
     */
    private function deleteImageIfExists(Announcement $announcement): void
    {
        if ($announcement->image_path && Storage::disk('public')->exists($announcement->image_path)) {
            Storage::disk('public')->delete($announcement->image_path);
        }
    }
}
