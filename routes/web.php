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

    // only give access if user has the role of admin
    Route::prefix('/admin-panel')->group(function() {

        Route::get('/', function() {
            return view('admin-panel');
        });

        Route::get('/users', 'AdminPanelController@getUsers');
    });

});
