<?php

namespace App\Http\Controllers;

use App\Time;
use Illuminate\Http\Request;
use Auth;

class TimeTrackerController extends Controller
{
    public function getTimes(Request $request)
    {
        $isAdmin = Auth::user()->can('access-admin-panel');

        $times = Time::selectRaw("
            times.id,
            times.start_time,
            times.stop_time,
            times.work_report,
            date(times.start_time) as date,
            times.stop_time - times.start_time as duration,
            EXTRACT(EPOCH FROM (times.stop_time - times.start_time)) as duration_in_seconds,
            users.name as employee_name
        ")->join('users', 'users.id', 'times.user_id');

        if($isAdmin && $request->user_id) {
            if($request->user_id !== 'All') {
                $times = $times->where('times.user_id', $request->user_id);
            }
        } else {
            $times = $times->where('times.user_id', Auth::id());
        }

        $times = $times->where(\DB::raw('date(times.start_time)'), '>=', $request->from)
        ->where(\DB::raw('date(times.start_time)'), '<=', $request->to);

        if(!isset($request->is_weekly)) {
            $times = $times->orderBy(\DB::raw('date(times.start_time)'))->orderBy('users.name');
        } else {
            $times = $times->orderBy('users.name')->orderBy(\DB::raw('date(times.start_time)'));
        }

        $times = $times->orderBy('times.start_time')->get();

        $times = $times->groupBy(!isset($request->is_weekly) ? 'date' : 'employee_name');

        foreach($times as $date => $timesDateWise) {
            $times[$date] = $timesDateWise->groupBy(!isset($request->is_weekly) ? 'employee_name' : 'date');
        }

        return $times;
    }

    public function getActiveTrack()
    {
        return Time::where('user_id', Auth::id())->whereNull('stop_time')->orderBy('start_time', 'desc')->select('id', 'start_time')->first();
    }

    public function startTracking(Request $request)
    {
        $timeOverlaps = Time::where('user_id', Auth::id())
        ->where('start_time', '<=', $request->start_time)
        ->where('stop_time', '>=', $request->start_time)
        ->count() > 0;

        if(!$timeOverlaps) {
            return Time::create([
                'user_id' => Auth::id(),
                'start_time' => $request->start_time
            ])->id;
        } else {
            return response('Given time overlaps with an existing entry', 400);
        }
    }

    public function stopTracking(Request $request, $timeId)
    {
        Time::where('id', $timeId)->where('user_id', Auth::id())->update([
            'stop_time' => $request->stop_time,
            'work_report' => $request->work_report
        ]);
    }
}
