<?php

namespace App\Http\Controllers;

use App\Enums\ReportBookingStatus;
use App\Http\Resources\BookingDashboardResource;
use App\Models\ReportBooking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $showPast = $request->boolean('past');

        $baseQuery = ReportBooking::query()
            ->with(['prospect.search', 'report'])
            ->whereHas('prospect.search', fn (Builder $q) => $q->where('user_id', $user->id));

        $upcomingCount = (clone $baseQuery)
            ->where('starts_at', '>=', now())
            ->count();

        $unsentCount = (clone $baseQuery)
            ->whereNull('confirmation_sent_at')
            ->where('status', ReportBookingStatus::Confirmed)
            ->count();

        $query = clone $baseQuery;

        if ($showPast) {
            $query->where('starts_at', '<', now());
        } else {
            $query->where('starts_at', '>=', now());
        }

        $paginator = $query
            ->orderBy('starts_at')
            ->paginate(20)
            ->withQueryString();

        $bookings = collect($paginator->items())
            ->map(fn (ReportBooking $booking) => BookingDashboardResource::format($booking))
            ->values();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'filters' => ['past' => $showPast],
            'stats' => [
                'upcoming' => $upcomingCount,
                'unsent_confirmations' => $unsentCount,
            ],
            'pagination' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
