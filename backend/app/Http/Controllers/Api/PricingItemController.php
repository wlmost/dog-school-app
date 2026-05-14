<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePricingItemRequest;
use App\Http\Requests\UpdatePricingItemRequest;
use App\Http\Resources\PricingItemResource;
use App\Models\PricingItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * PricingItem Controller
 *
 * Handles public and admin CRUD operations for pricing items.
 */
class PricingItemController extends Controller
{
    /**
     * Public endpoint: return all pricing items grouped by category.
     *
     * @return JsonResponse
     */
    public function publicIndex(): JsonResponse
    {
        $items = PricingItem::query()
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        $grouped = $items->groupBy('category')->map(function ($group, $category) {
            return [
                'category' => $category,
                'items'    => PricingItemResource::collection($group)->resolve(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * Admin endpoint: return a flat list of all pricing items.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return PricingItemResource::collection(
            PricingItem::query()->orderBy('category')->orderBy('id')->get()
        );
    }

    /**
     * Store a newly created pricing item.
     *
     * @param StorePricingItemRequest $request
     * @return JsonResponse
     */
    public function store(StorePricingItemRequest $request): JsonResponse
    {
        $item = PricingItem::create($request->validatedSnakeCase());

        return (new PricingItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified pricing item.
     *
     * @param UpdatePricingItemRequest $request
     * @param PricingItem $pricingItem
     * @return PricingItemResource
     */
    public function update(UpdatePricingItemRequest $request, PricingItem $pricingItem): PricingItemResource
    {
        $pricingItem->update($request->validatedSnakeCase());

        return new PricingItemResource($pricingItem->fresh());
    }

    /**
     * Delete the specified pricing item.
     *
     * @param PricingItem $pricingItem
     * @return JsonResponse
     */
    public function destroy(PricingItem $pricingItem): JsonResponse
    {
        $pricingItem->delete();

        return response()->json(null, 204);
    }
}
