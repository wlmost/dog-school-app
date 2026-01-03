<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Course;
use App\Models\Customer;
use App\Models\Dog;
use App\Models\Invoice;
use App\Models\TrainingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics and data based on user role.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->getAdminDashboard();
        }

        if ($user->isTrainer()) {
            return $this->getTrainerDashboard($user->id);
        }

        // Customer dashboard (future implementation)
        return response()->json([
            'stats' => [
                'customers' => 0,
                'dogs' => 0,
                'courses' => 0,
                'invoices' => 0,
            ],
            'upcomingSessions' => [],
            'recentBookings' => [],
        ]);
    }

    /**
     * Get admin dashboard with all data.
     */
    private function getAdminDashboard(): JsonResponse
    {
        $stats = [
            'customers' => Customer::count(),
            'dogs' => Dog::count(),
            'courses' => Course::where('status', 'active')->count(),
            'invoices' => Invoice::whereIn('status', ['pending', 'overdue'])->count(),
            'bookings' => Booking::whereIn('status', ['pending', 'confirmed'])->count(),
        ];

        $upcomingSessions = TrainingSession::with(['course', 'bookings'])
            ->where('session_date', '>=', now())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'course' => $session->course->name ?? 'Unbekannt',
                    'date' => $session->session_date->format('d.m.Y'),
                    'time' => substr($session->start_time, 0, 5),
                    'participants' => $session->bookings()->count(),
                ];
            });

        $recentBookings = Booking::with(['customer.user', 'dog', 'trainingSession.course'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'customer' => $booking->customer->user->full_name ?? 'Unbekannt',
                    'dog' => $booking->dog->name ?? 'Unbekannt',
                    'course' => $booking->trainingSession->course->name ?? 'Unbekannt',
                    'status' => $booking->status,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'upcomingSessions' => $upcomingSessions,
            'recentBookings' => $recentBookings,
        ]);
    }

    /**
     * Get trainer dashboard with assigned data only.
     */
    private function getTrainerDashboard(int $trainerId): JsonResponse
    {
        // Get customers directly assigned to this trainer
        $assignedCustomers = Customer::where('trainer_id', $trainerId)->pluck('id');

        // Get trainer's courses
        $trainerCourses = Course::where('trainer_id', $trainerId)
            ->where('status', 'active')
            ->pluck('id');

        $stats = [
            'customers' => $assignedCustomers->count(),
            'dogs' => Dog::whereIn('customer_id', $assignedCustomers)->count(),
            'courses' => $trainerCourses->count(),
            'invoices' => Invoice::whereHas('items', function ($query) use ($trainerCourses) {
                $query->whereIn('course_id', $trainerCourses);
            })->whereIn('status', ['pending', 'overdue'])->count(),
            'bookings' => Booking::whereHas('trainingSession', function ($query) use ($trainerCourses) {
                $query->whereIn('course_id', $trainerCourses);
            })->whereIn('status', ['pending', 'confirmed'])->count(),
        ];

        $upcomingSessions = TrainingSession::with(['course', 'bookings'])
            ->whereIn('course_id', $trainerCourses)
            ->where('session_date', '>=', now())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'course' => $session->course->name ?? 'Unbekannt',
                    'date' => $session->session_date->format('d.m.Y'),
                    'time' => substr($session->start_time, 0, 5),
                    'participants' => $session->bookings()->count(),
                ];
            });

        $recentBookings = Booking::with(['customer.user', 'dog', 'trainingSession.course'])
            ->whereHas('trainingSession', function ($query) use ($trainerCourses) {
                $query->whereIn('course_id', $trainerCourses);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'customer' => $booking->customer->user->full_name ?? 'Unbekannt',
                    'dog' => $booking->dog->name ?? 'Unbekannt',
                    'course' => $booking->trainingSession->course->name ?? 'Unbekannt',
                    'status' => $booking->status,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'upcomingSessions' => $upcomingSessions,
            'recentBookings' => $recentBookings,
        ]);
    }
}
