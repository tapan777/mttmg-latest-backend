<?php

use App\Http\Controllers\AdmsController;
use App\Http\Controllers\UserLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// ZKTeco ADMS — device connects here (no /api prefix, default device path)
Route::match(['GET', 'POST'], '/iclock/cdata', [AdmsController::class, 'handle']);