import { Routes } from '@angular/router';
import { adminGuard } from './core/guards/admin.guard';
import { authGuard } from './core/guards/auth.guard';
import { instructorGuard } from './core/guards/instructor.guard';
import { LayoutComponent } from './layout/layout.component';
import { HomeComponent } from './pages/home.component';
import { AdminAvailabilitiesComponent } from './pages/admin/admin-availabilities.component/admin-availabilities.component';
import { AdminBookableFormComponent } from './pages/admin/admin-bookable-form.component/admin-bookable-form.component';
import { AdminBookablesComponent } from './pages/admin/admin-bookables.component/admin-bookables.component';
import { AdminCourseFormComponent } from './pages/admin/admin-course-form/admin-course-form.component';
import { AdminCourseMaterialsComponent } from './pages/admin/admin-course-materials/admin-course-materials.component';
import { AdminCourseInstructorsComponent } from './pages/admin/admin-course-instructors/admin-course-instructors.component';
import { AdminCoursesComponent } from './pages/admin/admin-courses/admin-courses.component';
import { AdminPlanFormComponent } from './pages/admin/admin-plan-form/admin-plan-form.component';
import { AdminPlansComponent } from './pages/admin/admin-plans/admin-plans.component';
import { AdminSubscriptionsComponent } from './pages/admin/admin-subscriptions/admin-subscriptions.component';
import { BookableDetailComponent } from './pages/booking/bookable-detail.component/bookable-detail.component';
import { BookablesListComponent } from './pages/booking/bookables-list.component/bookables-list.component';
import { ProfileComponent } from './pages/profile/profile.component';
import { MyBookingsComponent } from './pages/booking/my-bookings.component/my-bookings.component';
import { CourseDetailComponent } from './pages/courses/course-detail/course-detail.component';
import { CoursesListComponent } from './pages/courses/courses-list/courses-list.component';
import { MyCoursesComponent } from './pages/courses/my-courses/my-courses.component';
import { TeachingComponent } from './pages/courses/teaching/teaching.component';

/**
 * Le pagine dell'app. Login e registrazione non hanno una pagina qui: le gestisce Keycloak
 * (le guardie mandano alla sua pagina di accesso). Ogni pagina ha un titolo, letto dai lettori di schermo
 * e mostrato nella scheda del browser.
 */
export const routes: Routes = [
  {
    path: '',
    component: LayoutComponent, // il layout con barra e menu avvolge tutte le pagine qui sotto
    children: [
      // Pagina pubblica: nessuna guardia
      { path: '', component: HomeComponent, pathMatch: 'full', title: 'Palestra' },

      // Il proprio profilo: per tutti gli utenti collegati
      { path: 'profile', component: ProfileComponent, canActivate: [authGuard], title: 'Il mio profilo - Palestra' },

      // Corsi: per tutti gli utenti collegati
      { path: 'courses', component: CoursesListComponent, canActivate: [authGuard], title: 'Corsi - Palestra' },
      { path: 'courses/:id', component: CourseDetailComponent, canActivate: [authGuard], title: 'Dettaglio del corso - Palestra' },
      { path: 'my-courses', component: MyCoursesComponent, canActivate: [authGuard], title: 'I miei corsi - Palestra' },
      // Solo per chi insegna in almeno un corso
      { path: 'teaching', component: TeachingComponent, canActivate: [instructorGuard], title: 'Corsi che insegno - Palestra' },

      // Prenotazione di sale e slot
      { path: 'bookables', component: BookablesListComponent, canActivate: [authGuard], title: 'Sale - Palestra' },
      { path: 'bookables/:id', component: BookableDetailComponent, canActivate: [authGuard], title: 'Prenota una sala - Palestra' },
      { path: 'my-bookings', component: MyBookingsComponent, canActivate: [authGuard], title: 'Le mie prenotazioni - Palestra' },

      // Area admin: accessibile solo con il ruolo "admin" (adminGuard)
      { path: 'admin', pathMatch: 'full', redirectTo: 'admin/courses' },
      { path: 'admin/courses', component: AdminCoursesComponent, canActivate: [adminGuard], title: 'Gestione corsi - Palestra' },
      { path: 'admin/courses/new', component: AdminCourseFormComponent, canActivate: [adminGuard], title: 'Nuovo corso - Palestra' },
      { path: 'admin/courses/:id/edit', component: AdminCourseFormComponent, canActivate: [adminGuard], title: 'Modifica corso - Palestra' },
      { path: 'admin/courses/:id/instructors', component: AdminCourseInstructorsComponent, canActivate: [adminGuard], title: 'Istruttori del corso - Palestra' },
      { path: 'admin/courses/:id/materials', component: AdminCourseMaterialsComponent, canActivate: [adminGuard], title: 'Materiali del corso - Palestra' },
      { path: 'admin/plans', component: AdminPlansComponent, canActivate: [adminGuard], title: 'Piani di abbonamento - Palestra' },
      { path: 'admin/plans/new', component: AdminPlanFormComponent, canActivate: [adminGuard], title: 'Nuovo piano - Palestra' },
      { path: 'admin/plans/:id/edit', component: AdminPlanFormComponent, canActivate: [adminGuard], title: 'Modifica piano - Palestra' },
      { path: 'admin/subscriptions', component: AdminSubscriptionsComponent, canActivate: [adminGuard], title: 'Utenti e abbonamenti - Palestra' },
      { path: 'admin/bookables', component: AdminBookablesComponent, canActivate: [adminGuard], title: 'Gestione sale - Palestra' },
      { path: 'admin/bookables/new', component: AdminBookableFormComponent, canActivate: [adminGuard], title: 'Nuova sala - Palestra' },
      { path: 'admin/bookables/:id/edit', component: AdminBookableFormComponent, canActivate: [adminGuard], title: 'Modifica sala - Palestra' },
      { path: 'admin/bookables/:id/availabilities', component: AdminAvailabilitiesComponent, canActivate: [adminGuard], title: 'Fasce orarie - Palestra' },
    ],
  },

  // Indirizzo sconosciuto: torna alla pagina pubblica
  { path: '**', redirectTo: '' },
];
