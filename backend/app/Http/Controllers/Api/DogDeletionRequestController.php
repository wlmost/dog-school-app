<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\DogDeletedMail;
use App\Models\DogDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Dog Deletion Request Controller
 *
 * Allows admins to approve or reject customer-submitted dog deletion requests.
 * Approval deletes the dog and sends a confirmation email to the customer.
 */
class DogDeletionRequestController extends Controller
{
    /**
     * Approve a deletion request: delete the dog and notify the customer.
     */
    public function approve(DogDeletionRequest $dogDeletionRequest): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        if ($dogDeletionRequest->status !== 'pending') {
            return response()->json(['message' => 'Anfrage wurde bereits bearbeitet.'], 422);
        }

        $dog      = $dogDeletionRequest->dog;
        $customer = $dogDeletionRequest->customer()->with('user')->first();
        $dogName  = $dogDeletionRequest->dog_name;

        // Delete dog if it still exists
        $dog?->delete();

        $dogDeletionRequest->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Send email to customer
        if ($customer?->user?->email) {
            Mail::to($customer->user->email)
                ->send(new DogDeletedMail($customer->user->first_name, $dogName));
        }

        return response()->json(['message' => 'Hund wurde gelöscht und Kunde informiert.']);
    }

    /**
     * Reject a deletion request (dog is kept).
     */
    public function reject(DogDeletionRequest $dogDeletionRequest): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403);
        }

        if ($dogDeletionRequest->status !== 'pending') {
            return response()->json(['message' => 'Anfrage wurde bereits bearbeitet.'], 422);
        }

        $dogDeletionRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Anfrage wurde abgelehnt.']);
    }
}
