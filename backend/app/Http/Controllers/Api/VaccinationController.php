<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVaccinationRequest;
use App\Http\Requests\UpdateVaccinationRequest;
use App\Http\Resources\VaccinationResource;
use App\Models\Vaccination;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vaccination Controller
 *
 * Handles CRUD operations for dog vaccinations.
 */
class VaccinationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of vaccinations with optional filtering.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Vaccination::query()->with(['dog.customer.user']);

        // Filter by dog
        if ($request->has('dogId')) {
            $query->where('dog_id', $request->input('dogId'));
        }

        // Filter by vaccination type
        if ($request->has('vaccinationType')) {
            $query->where('vaccination_type', 'ilike', '%' . $request->input('vaccinationType') . '%');
        }

        // Filter vaccinations due soon (within 30 days)
        if ($request->boolean('dueSoon')) {
            $query->dueSoon();
        }

        // Filter overdue vaccinations
        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        // Filter by date range
        if ($request->has('startDate')) {
            $query->where('vaccination_date', '>=', $request->input('startDate'));
        }

        if ($request->has('endDate')) {
            $query->where('vaccination_date', '<=', $request->input('endDate'));
        }

        return VaccinationResource::collection(
            $query->orderBy('vaccination_date', 'desc')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created vaccination.
     *
     * @param StoreVaccinationRequest $request
     * @return VaccinationResource
     */
    public function store(StoreVaccinationRequest $request): VaccinationResource
    {
        $vaccination = Vaccination::create($request->validatedSnakeCase());

        return new VaccinationResource($vaccination->load('dog.customer.user'));
    }

    /**
     * Display the specified vaccination.
     *
     * @param Vaccination $vaccination
     * @return VaccinationResource
     */
    public function show(Vaccination $vaccination): VaccinationResource
    {
        // Load dog for authorization check
        $vaccination->load('dog.customer.user');
        
        $this->authorize('view', $vaccination);
        
        return new VaccinationResource($vaccination);
    }

    /**
     * Update the specified vaccination.
     *
     * @param UpdateVaccinationRequest $request
     * @param Vaccination $vaccination
     * @return VaccinationResource
     */
    public function update(UpdateVaccinationRequest $request, Vaccination $vaccination): VaccinationResource
    {
        $vaccination->update($request->validatedSnakeCase());

        return new VaccinationResource($vaccination->fresh(['dog.customer.user']));
    }

    /**
     * Remove the specified vaccination.
     *
     * @param Vaccination $vaccination
     * @return JsonResponse
     */
    public function destroy(Vaccination $vaccination): JsonResponse
    {
        $this->authorize('delete', $vaccination);
        
        $vaccination->delete();

        return response()->json(null, 204);
    }

    /**
     * Get upcoming vaccinations that are due soon.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $query = Vaccination::query()
            ->with(['dog.customer.user'])
            ->dueSoon();

        return VaccinationResource::collection(
            $query->orderBy('next_due_date')
                ->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Get overdue vaccinations.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $query = Vaccination::query()
            ->with(['dog.customer.user'])
            ->overdue();

        return VaccinationResource::collection(
            $query->orderBy('next_due_date')
                ->paginate($request->input('perPage', 15))
        );
    }
}
