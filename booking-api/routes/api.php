<?php

use App\Http\Controllers\Api\AuthController;   // controller con register, login e logout
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookableController;   // controller delle risorse prenotabili
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RegistrationController;
use Illuminate\Http\Request;                   // serve alla rotta /user per leggere la richiesta
use Illuminate\Support\Facades\Route;          // per definire le rotte




// Rotte pubbliche: non serve essere autenticati.
// Laravel aggiunge in automatico il prefisso /api, quindi gli indirizzi
// Rotte pubbliche (solo per chi NON è loggato)
Route::post('/register', [RegistrationController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Conferma: la pagina del frontend raggiunta dal link nella mail invia email e token
Route::post('/register/confirm', [RegistrationController::class, 'confirm'])
    ->middleware('throttle:register-confirm');

// Rotte protette: la richiesta deve contenere un token Sanctum valido,
// altrimenti Laravel risponde con un errore 401 (non autenticato)
Route::middleware('auth:sanctum')->group(function () {

    // Revoca il token usato per la richiesta
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);


    // Consultazione: tutti gli utenti autenticati possono vedere le risorse.
    // Il nome {bookable} deve coincidere con il parametro $bookable del controller,
    // così Laravel trova da solo la risorsa (route model binding)
    Route::get('/bookables', [BookableController::class, 'index']);
    Route::get('/bookables/{bookable}', [BookableController::class, 'show']);

    // Gestione: solo gli admin. Il middleware 'can:admin' esegue la regola
    // definita con Gate::define e risponde 403 se l'utente non è admin
    Route::middleware('can:admin')->group(function () {
        Route::post('/bookables', [BookableController::class, 'store']);
        Route::put('/bookables/{bookable}', [BookableController::class, 'update']);
        Route::delete('/bookables/{bookable}', [BookableController::class, 'destroy']);

        // Fasce orarie di una risorsa: aggiunta ed eliminazione
        Route::post('/bookables/{bookable}/availabilities', [AvailabilityController::class, 'store']);
        Route::delete('/availabilities/{availability}', [AvailabilityController::class, 'destroy']);

    });

      

      


    // Slot liberi di una risorsa per un giorno: ?date=AAAA-MM-GG
    Route::get('/bookables/{bookable}/slots', [BookableController::class, 'slots']);

    // Prenotazioni: elenco, creazione e annullamento
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);


});