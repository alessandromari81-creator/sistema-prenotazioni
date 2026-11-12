<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * L'iscrizione dell'utente autenticato a un corso: "la mia iscrizione" a quel corso.
 *
 * Sono comandi: rispondono 204 senza corpo, perché l'esito è già nel codice di stato. Chi ha bisogno dei dati aggiornati
 * (iscritti, posti liberi, "sono iscritto?") li rilegge con GET /api/courses/{id}, che li calcola già per chi guarda.
 */
class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments)
    {
    }

    /**
     * Iscrive l'utente al corso (POST /api/courses/{course}/enrollment). Le regole (abbonamento, posti, istruttore...)
     * stanno nel servizio e, se non sono rispettate, la risposta è 422. Se va bene risponde 204 senza corpo.
     */
    public function store(Request $request, Course $course): Response
    {
        $this->enrollments->enroll($request->user(), $course);

        return response()->noContent();
    }

    /**
     * Annulla l'iscrizione dell'utente al corso (DELETE /api/courses/{course}/enrollment). Risponde 204 senza corpo;
     * 422 se l'utente non era iscritto.
     */
    public function destroy(Request $request, Course $course): Response
    {
        $this->enrollments->cancel($request->user(), $course);

        return response()->noContent();
    }
}
