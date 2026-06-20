<?php

namespace App\Http\Controllers;

use App\Models\AttendancePolicy;
use Illuminate\Http\Request;

class AttendanceSettingsController extends Controller
{
    public function edit()
    {
        $policy = AttendancePolicy::current();

        return view('attendance.settings', compact('policy'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'late_threshold_minutes' => 'required|integer|min:0|max:240',
            'late_grade_percentage' => 'required|numeric|min:0|max:100',
            'default_session_start_time' => 'required|date_format:H:i',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $policy = AttendancePolicy::current();
        $policy->update([
            'late_threshold_minutes' => (int) $validated['late_threshold_minutes'],
            'late_grade_percentage' => (float) $validated['late_grade_percentage'],
            'default_session_start_time' => $validated['default_session_start_time'].':00',
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        return redirect()
            ->route('admin.attendance-settings.edit')
            ->with('success', __('pages.attendance_settings_saved'));
    }
}
