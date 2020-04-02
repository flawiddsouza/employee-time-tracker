<?php

namespace App\Http\Controllers;

use App\Time;
use Illuminate\Http\Request;
use Auth;

class TimeTrackerController extends Controller
{
    public function getTimes(Request $request)
    {
        $times = Time::selectRaw("
            id,
            start_time,
            stop_time,
            work_report,
            date(start_time) as date,
            stop_time - start_time as duration,
            EXTRACT(EPOCH FROM (stop_time - start_time)) as duration_in_seconds
        ");

        if(Auth::user()->can('access-admin-panel') && $request->user_id) {
            $times = $times->where('user_id', $request->user_id);
        } else {
            $times = $times->where('user_id', Auth::id());
        }

        $times = $times->where(\DB::raw('date(start_time)'), '>=', $request->from)
        ->where(\DB::raw('date(start_time)'), '<=', $request->to)
        ->orderBy('start_time')
        ->get()
        ->groupBy('date');

        return $times;
    }

    public function getActiveTrack()
    {
        return Time::where('user_id', Auth::id())->whereNull('stop_time')->orderBy('start_time', 'desc')->select('id', 'start_time')->first();
    }

    public function startTracking(Request $request)
    {
        return Time::create([
            'user_id' => Auth::id(),
            'start_time' => $request->start_time
        ])->id;
    }

    public function stopTracking(Request $request, $timeId)
    {
        Time::where('id', $timeId)->where('user_id', Auth::id())->update([
            'stop_time' => $request->stop_time,
            'work_report' => $request->work_report
        ]);
    }
}
