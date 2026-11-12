<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * I permessi su un singolo corso. Laravel la collega al modello Course in automatico, dal nome.
 */
class CoursePolicy
{
    /**
     * Chi può vedere l'elenco degli iscritti: gli amministratori e gli istruttori di quel corso.
     * Gli altri utenti (compresi gli istruttori di altri corsi) no.
     */
    public function viewStudents(User $user, Course $course): bool
    {
        return $user->isAdmin() || $course->isTaughtBy($user);
    }

    /**
     * Chi può vedere i materiali PDF: gli amministratori, gli istruttori del corso e chi ha un'iscrizione valida.
     * Un'iscrizione annullata non basta: il permesso si perde subito, perché il file passa sempre dall'API.
     */
    public function viewMaterials(User $user, Course $course): bool
    {
        return $user->isAdmin()
            || $course->isTaughtBy($user)
            || $course->activeEnrollments()->where('user_id', $user->id)->exists();
    }
}
