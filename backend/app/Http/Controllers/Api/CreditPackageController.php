<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\DatabaseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreditPackageRequest;
use App\Http\Requests\UpdateCreditPackageRequest;
use App\Http\Resources\CreditPackageResource;
use App\Models\CreditPackage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Credit Package Controller
 *
 * Handles CRUD operations for credit packages (Mehrfachkarten).
 */
class CreditPackageController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of credit packages.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CreditPackage::query();

        // Search by name
        if ($request->has('search')) {
            $query->where('name', DatabaseHelper::caseInsensitiveLike(), '%' . $request->input('search') . '%');
        }

        // Filter by minimum credits
        if ($request->has('minCredits')) {
            $query->where('total_credits', '>=', $request->input('minCredits'));
        }

        // Sort by price or credits
        $sortBy = $request->input('sortBy', 'created_at');
        $sortOrder = $request->input('sortOrder', 'asc');

        if (in_array($sortBy, ['price', 'total_credits', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        return CreditPackageResource::collection(
            $query->paginate($request->input('perPage', 15))
        );
    }

    /**
     * Store a newly created credit package.
     *
     * @param StoreCreditPackageRequest $request
     * @return CreditPackageResource
     */
    public function store(StoreCreditPackageRequest $request): CreditPackageResource
    {
        $this->authorize('create', CreditPackage::class);

        $package = CreditPackage::create($request->validatedSnakeCase());

        return new CreditPackageResource($package);
    }

    /**
     * Display the specified credit package.
     *
     * @param CreditPackage $creditPackage
     * @return CreditPackageResource
     */
    public function show(CreditPackage $creditPackage): CreditPackageResource
    {
        return new CreditPackageResource($creditPackage);
    }

    /**
     * Update the specified credit package.
     *
     * @param UpdateCreditPackageRequest $request
     * @param CreditPackage $creditPackage
     * @return CreditPackageResource
     */
    public function update(UpdateCreditPackageRequest $request, CreditPackage $creditPackage): CreditPackageResource
    {
        $this->authorize('update', $creditPackage);

        $creditPackage->update($request->validatedSnakeCase());

        return new CreditPackageResource($creditPackage->fresh());
    }

    /**
     * Remove the specified credit package.
     *
     * @param CreditPackage $creditPackage
     * @return JsonResponse
     */
    public function destroy(CreditPackage $creditPackage): JsonResponse
    {
        $this->authorize('delete', $creditPackage);

        // Check if package has been purchased
        if ($creditPackage->customerCredits()->exists()) {
            return response()->json([
                'message' => 'Paket kann nicht gelöscht werden, da es bereits von Kunden erworben wurde.',
            ], 422);
        }

        $creditPackage->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all credit packages available for purchase.
     *
     * @return AnonymousResourceCollection
     */
    public function available(): AnonymousResourceCollection
    {
        $packages = CreditPackage::orderBy('total_credits', 'asc')->get();

        return CreditPackageResource::collection($packages);
    }
}
