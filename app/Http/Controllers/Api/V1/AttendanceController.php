<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $records = Attendance::with(['session'])
            ->where('user_id', $user->user_id)
            ->orderByDesc('attendance_time')
            ->limit(200)
            ->get()
            ->map(fn (Attendance $row) => [
                'attendance_id' => $row->getKey(),
                'session_id' => $row->session_id,
                'session_title' => $row->session?->session_title,
                'session_date' => $row->session?->session_date,
                'status' => $row->status,
                'attendance_time' => $this->isoOrString($row->attendance_time),
                'permission_reason' => $row->permission_reason,
            ]);

        $overall = DB::table('attendance')
            ->where('user_id', $user->user_id)
            ->selectRaw('
                COUNT(*) as total_records,
                SUM(CASE WHEN status IN ("Present", "Permission") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late_count
            ')
            ->first();

        $monthExpr = $this->sessionMonthSql();
        $monthly = DB::table('attendance')
            ->join('session', 'attendance.session_id', '=', 'session.session_id')
            ->where('attendance.user_id', $user->user_id)
            ->selectRaw("
                {$monthExpr} as month,
                COUNT(*) as total_records,
                SUM(CASE WHEN attendance.status IN (\"Present\", \"Permission\") THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendance.status = \"Absent\" THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN attendance.status = \"Late\" THEN 1 ELSE 0 END) as late_count
            ")
            ->groupByRaw($monthExpr)
            ->orderByRaw($monthExpr.' DESC')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => $records,
            'meta' => [
                'overall' => $overall,
                'monthly' => $monthly,
            ],
        ]);
    }

    private function sessionMonthSql(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', session.session_date)"
            : "DATE_FORMAT(session.session_date, '%Y-%m')";
    }

    private function isoOrString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'toIso8601String')) {
            return $value->toIso8601String();
        }

        return is_string($value) ? $value : (string) $value;
    }
}
