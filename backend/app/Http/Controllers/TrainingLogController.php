<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrainingLogRequest;
use App\Http\Requests\UpdateTrainingLogRequest;
use App\Http\Resources\TrainingLogResource;
use App\Models\TrainingLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TrainingLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TrainingLog::class);

        $query = TrainingLog::query()
            ->with(['dog', 'session', 'trainer', 'attachments']);

        // Filter by dog
        if ($request->has('dogId')) {
            $query->where('dog_id', $request->input('dogId'));
        }

        // Filter by trainer
        if ($request->has('trainerId')) {
            $query->where('trainer_id', $request->input('trainerId'));
        }

        // Filter by session
        if ($request->has('trainingSessionId')) {
            $query->where('training_session_id', $request->input('trainingSessionId'));
        }

        // Filter by date range
        if ($request->has('startDate')) {
            $query->where('created_at', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('created_at', '<=', $request->input('endDate'));
        }

        // Apply customer filter: customers can only see logs for their own dogs
        if ($request->user()->role === 'customer') {
            $query->whereHas('dog', function ($q) use ($request) {
                $q->whereHas('customer', function ($customerQuery) use ($request) {
                    $customerQuery->where('user_id', $request->user()->id);
                });
            });
        }

        $logs = $query->latest()->paginate(15);

        return TrainingLogResource::collection($logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTrainingLogRequest $request): TrainingLogResource
    {
        $this->authorize('create', TrainingLog::class);

        $log = TrainingLog::create($request->validatedSnakeCase());

        $log->load(['dog', 'session', 'trainer', 'attachments']);

        return new TrainingLogResource($log);
    }

    /**
     * Display the specified resource.
     */
    public function show(TrainingLog $trainingLog): TrainingLogResource
    {
        // Load dog relation for policy check
        $trainingLog->load('dog');
        
        $this->authorize('view', $trainingLog);

        $trainingLog->load(['session', 'trainer', 'attachments']);

        return new TrainingLogResource($trainingLog);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTrainingLogRequest $request, TrainingLog $trainingLog): TrainingLogResource
    {
        $this->authorize('update', $trainingLog);

        $trainingLog->update($request->validatedSnakeCase());

        $trainingLog->load(['dog', 'session', 'trainer', 'attachments']);

        return new TrainingLogResource($trainingLog);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TrainingLog $trainingLog): Response
    {
        $this->authorize('delete', $trainingLog);

        $trainingLog->delete();

        return response()->noContent();
    }
}
