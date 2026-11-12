<?php

use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookableController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseInstructorController;
use App\Http\Controllers\Api\CourseStudentController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotte dell'API (prefisso /api, aggiunto da Laravel)
|--------------------------------------------------------------------------
| Non ci sono rotte pubbliche: login, logout e registrazione le gestisce Keycloak.
| Ogni richiesta deve portare il JWT di Keycloak nell'header "Authorization: Bearer <token>".
| Il middleware "auth:keycloak" lo verifica e risponde 401 se manca o non è valido.
*/
Route::middleware(['auth:keycloak', 'throttle:api'])->group(function () {

    // L'utente a cui appartiene il token (il frontend lo chiama dopo il login)
    Route::get('/user', [UserController::class, 'me']);

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

        // Corsi: creazione, modifica, eliminazione e assegnazione degli istruttori
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::put('/courses/{course}/instructors/{user}', [CourseInstructorController::class, 'store']);
        Route::delete('/courses/{course}/instructors/{user}', [CourseInstructorController::class, 'destroy']);

        // Piani di abbonamento: gestione
        Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
        Route::put('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update']);
        Route::delete('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy']);

        // Utenti e abbonamenti: elenco utenti, abbonamenti di un utente, assegnazione e annullamento
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}/subscriptions', [UserSubscriptionController::class, 'index']);
        Route::post('/users/{user}/subscriptions', [UserSubscriptionController::class, 'store']);
        Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
    });

    // Corsi: li consultano tutti gli utenti autenticati
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);

    // Iscritti a un corso: solo admin e istruttori di quel corso (lo decide CoursePolicy nel controller)
    Route::get('/courses/{course}/students', [CourseStudentController::class, 'index']);

    // La mia iscrizione a un corso: iscriversi e annullare
    Route::post('/courses/{course}/enrollment', [EnrollmentController::class, 'store']);
    Route::delete('/courses/{course}/enrollment', [EnrollmentController::class, 'destroy']);

    // Piani di abbonamento: li consultano tutti (per sapere cosa esiste)
    Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);

    // Le mie cose: corsi a cui sono iscritto, corsi che insegno, il mio abbonamento
    Route::get('/me/courses', [MeController::class, 'courses']);
    Route::get('/me/teaching', [MeController::class, 'teaching']);
    Route::get('/me/subscription', [MeController::class, 'subscription']);

    // Slot liberi di una risorsa per un giorno: ?date=AAAA-MM-GG
    Route::get('/bookables/{bookable}/slots', [BookableController::class, 'slots']);

    // Prenotazioni: elenco, creazione e annullamento
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
});
