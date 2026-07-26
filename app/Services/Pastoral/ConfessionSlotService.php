<?php

namespace App\Services\Pastoral;

use App\Models\Church;
use App\Models\ConfessionSlot;
use App\Models\Priest;
use App\Services\AuditLogService;
use App\Support\Pastoral\BookingRules;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ConfessionSlotService
{
    public function create(Priest $priest, array $data): ConfessionSlot
    {
        $slot = new ConfessionSlot([
            'priest_id' => $priest->priest_id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'capacity' => $data['capacity'] ?? 1,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? ConfessionSlot::STATUS_OPEN,
            'recurrence' => $data['recurrence'] ?? null,
        ]);
        $slot->church_id = $priest->church_id;
        $slot->save();

        AuditLogService::recordEvent('confession_slot.created', [
            'confession_slot_id' => $slot->confession_slot_id,
            'priest_id' => $priest->priest_id,
        ]);

        return $slot;
    }

    public function update(ConfessionSlot $slot, array $data): ConfessionSlot
    {
        $slot->update($data);

        AuditLogService::recordEvent('confession_slot.updated', [
            'confession_slot_id' => $slot->confession_slot_id,
            'status' => $slot->status,
        ]);

        return $slot->fresh();
    }

    public function setStatus(ConfessionSlot $slot, string $status): ConfessionSlot
    {
        return $this->update($slot, ['status' => $status]);
    }

    /**
     * Generate weekly open slots for a priest over N weeks for selected weekdays.
     *
     * @param  list<int>  $weekdays  ISO weekdays 1=Mon … 7=Sun
     * @return Collection<int, ConfessionSlot>
     */
    public function generateWeekly(
        Priest $priest,
        array $weekdays,
        string $timeStart,
        string $timeEnd,
        int $weeks,
        int $capacity = 1,
        ?string $location = null,
        ?Carbon $fromDate = null,
    ): Collection {
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        if ($weekdays === [] || $weeks < 1 || $weeks > 26) {
            throw ValidationException::withMessages([
                'weekdays' => __('church_mgmt.generate_invalid'),
            ]);
        }

        $church = Church::find($priest->church_id) ?? Church::main();
        $tz = BookingRules::for($church)->timezone;
        $cursor = ($fromDate?->copy() ?? now($tz))->startOfDay();
        $created = collect();

        for ($w = 0; $w < $weeks; $w++) {
            foreach ($weekdays as $isoDay) {
                $day = $cursor->copy()->startOfWeek(Carbon::MONDAY)->addDays($isoDay - 1)->addWeeks($w);
                if ($day->lt(now($tz)->startOfDay())) {
                    continue;
                }

                $starts = Carbon::parse($day->format('Y-m-d').' '.$timeStart, $tz)->utc();
                $ends = Carbon::parse($day->format('Y-m-d').' '.$timeEnd, $tz)->utc();
                if ($ends->lte($starts)) {
                    throw ValidationException::withMessages([
                        'time_end' => __('church_mgmt.generate_invalid'),
                    ]);
                }

                $overlap = ConfessionSlot::query()
                    ->where('priest_id', $priest->priest_id)
                    ->where('status', '!=', ConfessionSlot::STATUS_CANCELLED)
                    ->where('starts_at', '<', $ends)
                    ->where('ends_at', '>', $starts)
                    ->exists();

                if ($overlap) {
                    continue;
                }

                $created->push($this->create($priest, [
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'capacity' => $capacity,
                    'location' => $location,
                    'status' => ConfessionSlot::STATUS_OPEN,
                    'recurrence' => 'weekly',
                ]));
            }
        }

        return $created;
    }

    /**
     * @return Collection<string, Collection<int, ConfessionSlot>> keyed by Y-m-d in church TZ
     */
    public function weekGrid(Carbon $weekStart, ?int $priestId = null): Collection
    {
        $start = $weekStart->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $query = ConfessionSlot::query()
            ->with(['priest.user', 'confirmedBookings.user'])
            ->where('starts_at', '>=', $start->copy()->utc())
            ->where('starts_at', '<=', $end->copy()->utc())
            ->orderBy('starts_at');

        if ($priestId) {
            $query->where('priest_id', $priestId);
        }

        return $query->get()->groupBy(function (ConfessionSlot $slot) use ($weekStart) {
            return $slot->starts_at?->timezone($weekStart->timezoneName)->format('Y-m-d') ?? '';
        });
    }
}
