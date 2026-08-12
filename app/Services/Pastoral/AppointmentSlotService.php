<?php

namespace App\Services\Pastoral;

use App\Models\AppointmentSlot;
use App\Models\AppointmentType;
use App\Models\Church;
use App\Models\Priest;
use App\Services\AuditLogService;
use App\Support\Pastoral\BookingRules;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AppointmentSlotService
{
    public function create(Priest $priest, AppointmentType $type, array $data): AppointmentSlot
    {
        $slot = new AppointmentSlot([
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'capacity' => $data['capacity'] ?? $type->default_capacity ?? 1,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? AppointmentSlot::STATUS_OPEN,
            'recurrence' => $data['recurrence'] ?? null,
        ]);
        $slot->church_id = $priest->church_id;
        $slot->save();

        AuditLogService::recordEvent('appointment_slot.created', [
            'appointment_slot_id' => $slot->appointment_slot_id,
            'priest_id' => $priest->priest_id,
            'appointment_type_id' => $type->appointment_type_id,
        ]);

        return $slot;
    }

    public function update(AppointmentSlot $slot, array $data): AppointmentSlot
    {
        $slot->update($data);

        AuditLogService::recordEvent('appointment_slot.updated', [
            'appointment_slot_id' => $slot->appointment_slot_id,
            'status' => $slot->status,
        ]);

        return $slot->fresh();
    }

    public function setStatus(AppointmentSlot $slot, string $status): AppointmentSlot
    {
        return $this->update($slot, ['status' => $status]);
    }

    /**
     * @param  list<int>  $weekdays
     * @return Collection<int, AppointmentSlot>
     */
    public function generateWeekly(
        Priest $priest,
        AppointmentType $type,
        array $weekdays,
        string $timeStart,
        string $timeEnd,
        int $weeks,
        ?int $capacity = null,
        ?string $location = null,
        ?Carbon $fromDate = null,
    ): Collection {
        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));
        if ($weekdays === [] || $weeks < 1 || $weeks > 26) {
            throw ValidationException::withMessages([
                'weekdays' => __('church_mgmt.generate_invalid'),
            ]);
        }

        $capacity ??= (int) ($type->default_capacity ?: 1);
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

                $overlap = AppointmentSlot::query()
                    ->where('priest_id', $priest->priest_id)
                    ->where('status', '!=', AppointmentSlot::STATUS_CANCELLED)
                    ->where('starts_at', '<', $ends)
                    ->where('ends_at', '>', $starts)
                    ->exists();

                if ($overlap) {
                    continue;
                }

                $created->push($this->create($priest, $type, [
                    'starts_at' => $starts,
                    'ends_at' => $ends,
                    'capacity' => $capacity,
                    'location' => $location,
                    'status' => AppointmentSlot::STATUS_OPEN,
                    'recurrence' => 'weekly',
                ]));
            }
        }

        return $created;
    }

    /** @return Collection<string, Collection<int, AppointmentSlot>> */
    public function weekGrid(Carbon $weekStart, ?int $priestId = null, ?int $typeId = null): Collection
    {
        $start = $weekStart->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $query = AppointmentSlot::query()
            ->with(['priest.user', 'type', 'confirmedBookings.user'])
            ->where('starts_at', '>=', $start->copy()->utc())
            ->where('starts_at', '<=', $end->copy()->utc())
            ->orderBy('starts_at');

        if ($priestId) {
            $query->where('priest_id', $priestId);
        }
        if ($typeId) {
            $query->where('appointment_type_id', $typeId);
        }

        return $query->get()->groupBy(function (AppointmentSlot $slot) use ($weekStart) {
            return $slot->starts_at?->timezone($weekStart->timezoneName)->format('Y-m-d') ?? '';
        });
    }
}
