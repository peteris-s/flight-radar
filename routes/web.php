<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FlightController;

Route::get('/', [FlightController::class, 'index'])->name('flights.index');
Route::get('/api/flights', [FlightController::class, 'getFlights'])->name('flights.api');
