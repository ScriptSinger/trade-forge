<?php

use App\Jobs\TestQueueJob;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    TestQueueJob::dispatch();
    return view('welcome');
});
