<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnamnesisResponseRequest;
use App\Http\Requests\UpdateAnamnesisResponseRequest;
use App\Http\Resources\AnamnesisResponseResource;
use App\Models\AnamnesisResponse;
use App\Models\Dog;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AnamnesisResponseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of anamnesis responses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AnamnesisResponse::query()
            ->with(['dog.customer', 'template', 'completedBy']);

        // Filter by dog
        if ($request->has('dogId')) {
            $query->where('dog_id', $request->input('dogId'));
        }

        // Filter by template
        if ($request->has('templateId')) {
            $query->where('template_id', $request->input('templateId'));
        }

        // Filter by completion status
        if ($request->has('completed')) {
            if ($request->boolean('completed')) {
                $query->completed();
            } else {
                $query->incomplete();
            }
        }

        // Filter by customer (through dog)
        if ($request->has('customerId')) {
            $query->whereHas('dog', function ($q) use ($request) {
                $q->where('customer_id', $request->input('customerId'));
            });
        }

        // Authorization: customers only see their dogs' responses
        if ($request->user()->role === 'customer') {
            $query->whereHas('dog', function ($q) use ($request) {
                $q->where('customer_id', $request->user()->customer->id);
            });
        }

        // Order by completion date descending (newest first)
        $query->orderBy('completed_at', 'desc')
            ->orderBy('created_at', 'desc');

        return AnamnesisResponseResource::collection(
            $query->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created anamnesis response.
     */
    public function store(StoreAnamnesisResponseRequest $request): JsonResponse
    {
        $data = $request->validatedSnakeCase();
        $answers = $data['answers'] ?? [];
        unset($data['answers']);

        $response = DB::transaction(function () use ($data, $answers) {
            $response = AnamnesisResponse::create($data);

            // Create answers if provided
            if (!empty($answers)) {
                foreach ($answers as $answerData) {
                    $answerData['response_id'] = $response->id;
                    $response->answers()->create($answerData);
                }
            }

            return $response;
        });

        $response->load(['dog', 'template', 'completedBy', 'answers.question']);

        return (new AnamnesisResponseResource($response))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified anamnesis response.
     */
    public function show(AnamnesisResponse $anamnesisResponse): AnamnesisResponseResource
    {
        $this->authorize('view', $anamnesisResponse);

        $anamnesisResponse->load([
            'dog.customer',
            'template.questions',
            'completedBy',
            'answers.question'
        ]);

        return new AnamnesisResponseResource($anamnesisResponse);
    }

    /**
     * Update the specified anamnesis response.
     */
    public function update(
        UpdateAnamnesisResponseRequest $request,
        AnamnesisResponse $anamnesisResponse
    ): AnamnesisResponseResource {
        $this->authorize('update', $anamnesisResponse);

        $data = $request->validatedSnakeCase();

        DB::transaction(function () use ($anamnesisResponse, $data) {
            // Update answers if provided
            if (!empty($data['answers'])) {
                foreach ($data['answers'] as $answerData) {
                    $anamnesisResponse->answers()->updateOrCreate(
                        ['question_id' => $answerData['question_id']],
                        ['answer_value' => $answerData['answer_value']]
                    );
                }
            }
        });

        $anamnesisResponse->load(['dog', 'template', 'completedBy', 'answers.question']);

        return new AnamnesisResponseResource($anamnesisResponse);
    }

    /**
     * Remove the specified anamnesis response.
     */
    public function destroy(AnamnesisResponse $anamnesisResponse): JsonResponse
    {
        $this->authorize('delete', $anamnesisResponse);

        $anamnesisResponse->delete();

        return response()->json(null, 204);
    }

    /**
     * Complete an anamnesis response.
     */
    public function complete(AnamnesisResponse $anamnesisResponse): AnamnesisResponseResource
    {
        $this->authorize('update', $anamnesisResponse);

        $anamnesisResponse->update([
            'completed_at' => now(),
        ]);

        $anamnesisResponse->load(['dog', 'template', 'completedBy', 'answers.question']);

        return new AnamnesisResponseResource($anamnesisResponse);
    }
}
