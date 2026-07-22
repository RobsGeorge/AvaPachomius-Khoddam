<?php

namespace App\Http\Controllers\Church;

use App\Http\Controllers\Church\Concerns\ResolvesTenantChurch;
use App\Http\Controllers\Controller;
use App\Models\ConfessionBooking;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConfessionController extends Controller
{
    use ResolvesTenantChurch;

    public function index()
    {
        $slots = ConfessionSlot::query()
            ->with(['priest.user', 'confirmedBookings'])
            ->where('starts_at', '>=', now()->subDay())
            ->orderBy('starts_at')
            ->paginate(30);

        $myPriest = Priest::query()
            ->where('user_id', Auth::id())
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        return view('church.confession.index', [
            'slots' => $slots,
            'myPriest' => $myPriest,
        ]);
    }

    public function create()
    {
        $priest = $this->requireOwnActivePriest();

        return view('church.confession.create', compact('priest'));
    }

    public function store(Request $request)
    {
        $priest = $this->requireOwnActivePriest();

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $slot = new ConfessionSlot([
            'priest_id' => $priest->priest_id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'capacity' => $validated['capacity'],
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => ConfessionSlot::STATUS_OPEN,
            'recurrence' => null,
        ]);
        $slot->church_id = $priest->church_id;
        $slot->save();

        AuditLogService::recordEvent('confession_slot.created', [
            'confession_slot_id' => $slot->confession_slot_id,
            'priest_id' => $priest->priest_id,
        ]);

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.slot_created'));
    }

    public function edit(ConfessionSlot $slot)
    {
        $this->assertOwnsSlot($slot);

        return view('church.confession.edit', ['slot' => $slot]);
    }

    public function update(Request $request, ConfessionSlot $slot)
    {
        $this->assertOwnsSlot($slot);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'location' => ['nullable', 'string', 'max:191'],
            'status' => ['required', Rule::in([
                ConfessionSlot::STATUS_OPEN,
                ConfessionSlot::STATUS_CLOSED,
                ConfessionSlot::STATUS_CANCELLED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $slot->update($validated);

        AuditLogService::recordEvent('confession_slot.updated', [
            'confession_slot_id' => $slot->confession_slot_id,
        ]);

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.slot_updated'));
    }

    public function book(Request $request, ConfessionSlot $slot)
    {
        abort_unless($slot->isOpen(), 404);
        abort_unless($slot->remainingCapacity() > 0, 422, __('church_mgmt.slot_full'));

        $user = Auth::user();
        abort_unless($user, 403);

        $existing = ConfessionBooking::query()
            ->where('confession_slot_id', $slot->confession_slot_id)
            ->where('user_id', $user->user_id)
            ->first();

        if ($existing && $existing->status === ConfessionBooking::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'slot' => __('church_mgmt.already_booked'),
            ]);
        }

        if ($existing) {
            $existing->update([
                'status' => ConfessionBooking::STATUS_CONFIRMED,
                'notes' => $request->input('notes'),
            ]);
            $booking = $existing;
        } else {
            $booking = new ConfessionBooking([
                'confession_slot_id' => $slot->confession_slot_id,
                'user_id' => $user->user_id,
                'status' => ConfessionBooking::STATUS_CONFIRMED,
                'notes' => $request->input('notes'),
            ]);
            $booking->church_id = $slot->church_id;
            $booking->save();
        }

        AuditLogService::recordEvent('confession_booking.created', [
            'confession_booking_id' => $booking->confession_booking_id,
            'confession_slot_id' => $slot->confession_slot_id,
        ]);

        return redirect()
            ->route('church.confession.index')
            ->with('success', __('church_mgmt.booking_confirmed'));
    }

    public function cancelBooking(ConfessionBooking $booking)
    {
        $user = Auth::user();
        $church = $booking->church ?? $this->resolveChurch();

        abort_unless($user && (
            (int) $booking->user_id === (int) $user->user_id
            || ($user->is_superadmin ?? false)
            || $user->canInChurch('confession.manage', $church)
        ), 403);

        $booking->update(['status' => ConfessionBooking::STATUS_CANCELLED]);

        AuditLogService::recordEvent('confession_booking.cancelled', [
            'confession_booking_id' => $booking->confession_booking_id,
        ]);

        return back()->with('success', __('church_mgmt.booking_cancelled'));
    }

    private function requireOwnActivePriest(): Priest
    {
        $priest = Priest::query()
            ->where('user_id', Auth::id())
            ->where('status', Priest::STATUS_ACTIVE)
            ->first();

        abort_unless($priest, 403, __('church_mgmt.not_a_priest'));

        return $priest;
    }

    private function assertOwnsSlot(ConfessionSlot $slot): void
    {
        $priest = $this->requireOwnActivePriest();
        abort_unless((int) $slot->priest_id === (int) $priest->priest_id, 403);
    }
}
