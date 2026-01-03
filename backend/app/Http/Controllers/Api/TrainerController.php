<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TrainerController extends Controller
{
    /**
     * Display a listing of trainers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->where('role', 'trainer');

        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                  ->orWhere('last_name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        $trainers = $query->orderBy('last_name')->orderBy('first_name')->get();

        return UserResource::collection($trainers);
    }

    /**
     * Store a newly created trainer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'qualifications' => ['nullable', 'string'],
            'specializations' => ['nullable', 'string'],
        ]);

        $trainer = User::create([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'street' => $validated['street'] ?? null,
            'postal_code' => $validated['postalCode'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'qualifications' => $validated['qualifications'] ?? null,
            'specializations' => $validated['specializations'] ?? null,
            'role' => 'trainer',
        ]);

        return response()->json([
            'message' => 'Trainer erfolgreich erstellt',
            'data' => new UserResource($trainer),
        ], 201);
    }

    /**
     * Display the specified trainer.
     */
    public function show(User $trainer): UserResource
    {
        // Ensure the user is actually a trainer
        abort_if($trainer->role !== 'trainer', 404, 'Trainer not found');

        return new UserResource($trainer);
    }

    /**
     * Update the specified trainer.
     */
    public function update(Request $request, User $trainer): JsonResponse
    {
        // Ensure the user is actually a trainer
        abort_if($trainer->role !== 'trainer', 404, 'Trainer not found');

        $validated = $request->validate([
            'firstName' => ['sometimes', 'required', 'string', 'max:255'],
            'lastName' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:users,email,' . $trainer->id],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'street' => ['nullable', 'string', 'max:255'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'qualifications' => ['nullable', 'string'],
            'specializations' => ['nullable', 'string'],
        ]);

        $updateData = [];
        if (isset($validated['firstName'])) {
            $updateData['first_name'] = $validated['firstName'];
        }
        if (isset($validated['lastName'])) {
            $updateData['last_name'] = $validated['lastName'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (isset($validated['password']) && $validated['password']) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        if (array_key_exists('phone', $validated)) {
            $updateData['phone'] = $validated['phone'];
        }
        if (array_key_exists('street', $validated)) {
            $updateData['street'] = $validated['street'];
        }
        if (array_key_exists('postalCode', $validated)) {
            $updateData['postal_code'] = $validated['postalCode'];
        }
        if (array_key_exists('city', $validated)) {
            $updateData['city'] = $validated['city'];
        }
        if (array_key_exists('country', $validated)) {
            $updateData['country'] = $validated['country'];
        }
        if (array_key_exists('qualifications', $validated)) {
            $updateData['qualifications'] = $validated['qualifications'];
        }
        if (array_key_exists('specializations', $validated)) {
            $updateData['specializations'] = $validated['specializations'];
        }

        $trainer->update($updateData);

        return response()->json([
            'message' => 'Trainer erfolgreich aktualisiert',
            'data' => new UserResource($trainer),
        ]);
    }

    /**
     * Remove the specified trainer.
     */
    public function destroy(User $trainer): JsonResponse
    {
        // Ensure the user is actually a trainer
        abort_if($trainer->role !== 'trainer', 404, 'Trainer not found');

        // Check if trainer has active courses
        if ($trainer->courses()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Trainer kann nicht gelöscht werden, da noch aktive Kurse zugeordnet sind',
            ], 422);
        }

        $trainer->delete();

        return response()->json([
            'message' => 'Trainer erfolgreich gelöscht',
        ]);
    }
}
