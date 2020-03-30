<?php

Route::get('/', function() {
    if(Auth::check()) {
        return view('home');
    } else {
        return view('auth.login');
    }
});

Auth::routes();

Route::middleware('auth')->group(function() {

    Route::prefix('/time-tracker')->group(function() {
        Route::get('/times', 'TimeTrackerController@getTimes');
        Route::get('/active-track', 'TimeTrackerController@getActiveTrack');
        Route::post('/start', 'TimeTrackerController@startTracking');
        Route::put('/stop/{timeId}', 'TimeTrackerController@stopTracking');
    });

});
