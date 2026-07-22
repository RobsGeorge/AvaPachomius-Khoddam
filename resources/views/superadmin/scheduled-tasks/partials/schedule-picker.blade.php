@php
    $prefix = $prefix ?? 'schedule';
    $idPrefix = $idPrefix ?? $prefix;
    $scheduleUi = $scheduleUi ?? ['frequency' => 'daily_at', 'time' => '00:00', 'day' => 1];
    $frequency = old($prefix.'_frequency', $scheduleUi['frequency'] ?? 'daily_at');
    $time = old($prefix.'_time', $scheduleUi['time'] ?? '00:00');
    $day = old($prefix.'_day', $scheduleUi['day'] ?? 1);
    $frequencies = [
        'every_five_minutes',
        'hourly',
        'daily_at',
        'weekly_on',
        'monthly_on',
    ];
@endphp

<div class="schedule-picker" data-schedule-picker="{{ $idPrefix }}">
    <fieldset class="mb-2">
        <legend class="form-label small mb-2">{{ __('scheduled_tasks.field_schedule_frequency') }}</legend>
        <div class="d-flex flex-wrap gap-2">
            @foreach($frequencies as $freq)
                <div class="form-check form-check-inline">
                    <input class="form-check-input schedule-frequency-radio"
                           type="radio"
                           name="{{ $prefix }}_frequency"
                           id="{{ $idPrefix }}-freq-{{ $freq }}"
                           value="{{ $freq }}"
                           data-schedule-frequency
                           @checked($frequency === $freq)
                           required>
                    <label class="form-check-label small" for="{{ $idPrefix }}-freq-{{ $freq }}">
                        {{ __('scheduled_tasks.freq_'.$freq) }}
                    </label>
                </div>
            @endforeach
        </div>
    </fieldset>

    <div class="row g-2">
        <div class="col-md-4 schedule-time-field" data-schedule-time-wrap>
            <label class="form-label small" for="{{ $idPrefix }}-time">{{ __('scheduled_tasks.field_schedule_time') }}</label>
            <input type="time"
                   class="form-control form-control-sm"
                   id="{{ $idPrefix }}-time"
                   name="{{ $prefix }}_time"
                   value="{{ $time }}"
                   data-schedule-time>
        </div>

        <div class="col-md-4 schedule-weekday-field" data-schedule-weekday-wrap>
            <label class="form-label small" for="{{ $idPrefix }}-weekday">{{ __('scheduled_tasks.field_schedule_weekday') }}</label>
            <select class="form-select form-select-sm"
                    id="{{ $idPrefix }}-weekday"
                    name="{{ $prefix }}_day"
                    data-schedule-weekday>
                @foreach(__('scheduled_tasks.weekdays') as $weekdayValue => $weekdayLabel)
                    <option value="{{ $weekdayValue }}" @selected((int) $day === (int) $weekdayValue)>{{ $weekdayLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4 schedule-monthday-field" data-schedule-monthday-wrap>
            <label class="form-label small" for="{{ $idPrefix }}-monthday">{{ __('scheduled_tasks.field_schedule_month_day') }}</label>
            <select class="form-select form-select-sm"
                    id="{{ $idPrefix }}-monthday"
                    name="{{ $prefix }}_day"
                    data-schedule-monthday
                    disabled>
                @for($d = 1; $d <= 31; $d++)
                    <option value="{{ $d }}" @selected((int) $day === $d)>{{ $d }}</option>
                @endfor
            </select>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        const FREQ = {
            EVERY_FIVE: 'every_five_minutes',
            HOURLY: 'hourly',
            DAILY: 'daily_at',
            WEEKLY: 'weekly_on',
            MONTHLY: 'monthly_on',
        };

        function syncSchedulePicker(root) {
            const selected = root.querySelector('[data-schedule-frequency]:checked');
            const frequency = selected ? selected.value : FREQ.DAILY;
            const timeWrap = root.querySelector('[data-schedule-time-wrap]');
            const timeInput = root.querySelector('[data-schedule-time]');
            const weekdayWrap = root.querySelector('[data-schedule-weekday-wrap]');
            const weekdaySelect = root.querySelector('[data-schedule-weekday]');
            const monthdayWrap = root.querySelector('[data-schedule-monthday-wrap]');
            const monthdaySelect = root.querySelector('[data-schedule-monthday]');

            const needsTime = [FREQ.DAILY, FREQ.WEEKLY, FREQ.MONTHLY].includes(frequency);
            const isWeekly = frequency === FREQ.WEEKLY;
            const isMonthly = frequency === FREQ.MONTHLY;

            if (timeWrap) {
                timeWrap.classList.toggle('d-none', !needsTime);
            }
            if (timeInput) {
                timeInput.disabled = !needsTime;
                timeInput.required = needsTime;
            }

            if (weekdayWrap) {
                weekdayWrap.classList.toggle('d-none', !isWeekly);
            }
            if (weekdaySelect) {
                weekdaySelect.disabled = !isWeekly;
                weekdaySelect.required = isWeekly;
            }

            if (monthdayWrap) {
                monthdayWrap.classList.toggle('d-none', !isMonthly);
            }
            if (monthdaySelect) {
                monthdaySelect.disabled = !isMonthly;
                monthdaySelect.required = isMonthly;
            }
        }

        document.querySelectorAll('[data-schedule-picker]').forEach(function (root) {
            root.addEventListener('change', function (event) {
                if (event.target.matches('[data-schedule-frequency]')) {
                    syncSchedulePicker(root);
                }
            });
            syncSchedulePicker(root);
        });
    })();
    </script>
    @endpush
@endonce
