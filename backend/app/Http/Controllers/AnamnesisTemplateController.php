<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnamnesisTemplateRequest;
use App\Http\Requests\UpdateAnamnesisTemplateRequest;
use App\Http\Resources\AnamnesisTemplateResource;
use App\Models\AnamnesisTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AnamnesisTemplateController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of anamnesis templates.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AnamnesisTemplate::query()->with(['trainer']);

        // Role-based filtering
        $user = $request->user();
        if ($user->isTrainer()) {
            // Trainer sees default templates and their own templates
            $query->where(function ($q) use ($user) {
                $q->where('is_default', true)
                  ->orWhere('trainer_id', $user->id);
            });
        } elseif ($user->isCustomer()) {
            // Customers only see default templates (for information)
            $query->where('is_default', true);
        }
        // Admin sees all

        // Filter by trainer (Admin only)
        if ($request->has('trainerId') && $user->isAdmin()) {
            $query->where('trainer_id', $request->input('trainerId'));
        }

        // Filter by default templates
        if ($request->boolean('isDefault')) {
            $query->where('is_default', true);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->input('search') . '%');
        }

        // Order by name by default
        $query->orderBy('name');

        return AnamnesisTemplateResource::collection(
            $query->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created anamnesis template.
     */
    public function store(StoreAnamnesisTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', AnamnesisTemplate::class);

        $data = $request->validatedSnakeCase();
        $questions = $data['questions'] ?? [];
        unset($data['questions']);

        $template = DB::transaction(function () use ($data, $questions) {
            $template = AnamnesisTemplate::create($data);

            // Create questions if provided
            if (!empty($questions)) {
                foreach ($questions as $questionData) {
                    $template->questions()->create($questionData);
                }
            }

            return $template;
        });

        $template->load(['trainer', 'questions']);

        return (new AnamnesisTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified anamnesis template.
     */
    public function show(AnamnesisTemplate $anamnesisTemplate): AnamnesisTemplateResource
    {
        $this->authorize('view', $anamnesisTemplate);

        $anamnesisTemplate->load(['trainer', 'questions', 'responses']);

        return new AnamnesisTemplateResource($anamnesisTemplate);
    }

    /**
     * Update the specified anamnesis template.
     */
    public function update(
        UpdateAnamnesisTemplateRequest $request,
        AnamnesisTemplate $anamnesisTemplate
    ): AnamnesisTemplateResource {
        $this->authorize('update', $anamnesisTemplate);

        $anamnesisTemplate->update($request->validatedSnakeCase());
        $anamnesisTemplate->load(['trainer', 'questions']);

        return new AnamnesisTemplateResource($anamnesisTemplate);
    }

    /**
     * Remove the specified anamnesis template.
     */
    public function destroy(AnamnesisTemplate $anamnesisTemplate): JsonResponse
    {
        $this->authorize('delete', $anamnesisTemplate);

        // Prevent deletion if template has responses
        if ($anamnesisTemplate->responses()->exists()) {
            return response()->json([
                'message' => 'Cannot delete template with existing responses.'
            ], 422);
        }

        $anamnesisTemplate->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all questions for a template.
     */
    public function questions(AnamnesisTemplate $anamnesisTemplate): AnonymousResourceCollection
    {
        $this->authorize('view', $anamnesisTemplate);

        $questions = $anamnesisTemplate->questions()
            ->orderBy('order')
            ->get();

        return \App\Http\Resources\AnamnesisQuestionResource::collection($questions);
    }
}
