<?php

namespace App\Http\Controllers;

use App\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeTrackerController extends Controller
{
    public function getTimes()
    {
        return Time::where('user_id', Auth::id())->orderBy('start_time')
        ->selectRaw("
            id,
            start_time,
            stop_time,
            work_report,
            date(start_time) as date,
            stop_time - start_time as duration,
            EXTRACT(EPOCH FROM (stop_time - start_time)) as duration_in_seconds
        ")
        ->get()
        ->groupBy('date');
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
