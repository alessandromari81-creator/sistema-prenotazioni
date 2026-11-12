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
}
