import { Routes } from '@angular/router';
import { adminGuard } from './core/guards/admin.guard';
import { authGuard } from './core/guards/auth.guard';
import { instructorGuard } from './core/guards/instructor.guard';
import { LayoutComponent } from './layout/layout.component';
import { HomeComponent } from './pages/home.component';

/**
 * Le pagine dell'app. Login e registrazione non hanno una pagina qui: le gestisce Keycloak (le guardie mandano alla sua
 * pagina di accesso). Ogni pagina ha un titolo, letto dai lettori di schermo e mostrato nella scheda del browser.
 *
 * Layout e home si caricano subito (servono al primo schermo); ogni altra pagina è un "pezzo" a parte (`loadComponent`,
 * lazy loading) che il browser scarica solo la prima volta che la si apre. Le guardie girano prima del download, quindi chi
 * non è admin non scarica mai il codice dell'area admin, e il bundle iniziale resta piccolo.
 */
export const routes: Routes = [
  {
    path: '',
    component: LayoutComponent, // il layout con barra e menu avvolge tutte le pagine qui sotto
    children: [
      // Pagina pubblica: nessuna guardia
      { path: '', component: HomeComponent, pathMatch: 'full', title: 'Palestra' },

      // Il proprio profilo: per tutti gli utenti collegati
      { path: 'profile', loadComponent: () => import('./pages/profile/profile.component').then((m) => m.ProfileComponent), canActivate: [authGuard], title: 'Il mio profilo - Palestra' },

      // Corsi: per tutti gli utenti collegati
      { path: 'courses', loadComponent: () => import('./pages/courses/courses-list/courses-list.component').then((m) => m.CoursesListComponent), canActivate: [authGuard], title: 'Corsi - Palestra' },
      { path: 'courses/:id', loadComponent: () => import('./pages/courses/course-detail/course-detail.component').then((m) => m.CourseDetailComponent), canActivate: [authGuard], title: 'Dettaglio del corso - Palestra' },
      { path: 'my-courses', loadComponent: () => import('./pages/courses/my-courses/my-courses.component').then((m) => m.MyCoursesComponent), canActivate: [authGuard], title: 'I miei corsi - Palestra' },
      // Solo per chi insegna in almeno un corso
      { path: 'teaching', loadComponent: () => import('./pages/courses/teaching/teaching.component').then((m) => m.TeachingComponent), canActivate: [instructorGuard], title: 'Corsi che insegno - Palestra' },

      // Prenotazione di sale e slot
      { path: 'bookables', loadComponent: () => import('./pages/booking/bookables-list.component/bookables-list.component').then((m) => m.BookablesListComponent), canActivate: [authGuard], title: 'Sale - Palestra' },
      { path: 'bookables/:id', loadComponent: () => import('./pages/booking/bookable-detail.component/bookable-detail.component').then((m) => m.BookableDetailComponent), canActivate: [authGuard], title: 'Prenota una sala - Palestra' },
      { path: 'my-bookings', loadComponent: () => import('./pages/booking/my-bookings.component/my-bookings.component').then((m) => m.MyBookingsComponent), canActivate: [authGuard], title: 'Le mie prenotazioni - Palestra' },

      // Area admin: accessibile solo con il ruolo "admin" (adminGuard)
      { path: 'admin', pathMatch: 'full', redirectTo: 'admin/courses' },
      { path: 'admin/courses', loadComponent: () => import('./pages/admin/admin-courses/admin-courses.component').then((m) => m.AdminCoursesComponent), canActivate: [adminGuard], title: 'Gestione corsi - Palestra' },
      { path: 'admin/courses/new', loadComponent: () => import('./pages/admin/admin-course-form/admin-course-form.component').then((m) => m.AdminCourseFormComponent), canActivate: [adminGuard], title: 'Nuovo corso - Palestra' },
      { path: 'admin/courses/:id/edit', loadComponent: () => import('./pages/admin/admin-course-form/admin-course-form.component').then((m) => m.AdminCourseFormComponent), canActivate: [adminGuard], title: 'Modifica corso - Palestra' },
      { path: 'admin/courses/:id/instructors', loadComponent: () => import('./pages/admin/admin-course-instructors/admin-course-instructors.component').then((m) => m.AdminCourseInstructorsComponent), canActivate: [adminGuard], title: 'Istruttori del corso - Palestra' },
      { path: 'admin/courses/:id/materials', loadComponent: () => import('./pages/admin/admin-course-materials/admin-course-materials.component').then((m) => m.AdminCourseMaterialsComponent), canActivate: [adminGuard], title: 'Materiali del corso - Palestra' },
      { path: 'admin/plans', loadComponent: () => import('./pages/admin/admin-plans/admin-plans.component').then((m) => m.AdminPlansComponent), canActivate: [adminGuard], title: 'Piani di abbonamento - Palestra' },
      { path: 'admin/plans/new', loadComponent: () => import('./pages/admin/admin-plan-form/admin-plan-form.component').then((m) => m.AdminPlanFormComponent), canActivate: [adminGuard], title: 'Nuovo piano - Palestra' },
      { path: 'admin/plans/:id/edit', loadComponent: () => import('./pages/admin/admin-plan-form/admin-plan-form.component').then((m) => m.AdminPlanFormComponent), canActivate: [adminGuard], title: 'Modifica piano - Palestra' },
      { path: 'admin/subscriptions', loadComponent: () => import('./pages/admin/admin-subscriptions/admin-subscriptions.component').then((m) => m.AdminSubscriptionsComponent), canActivate: [adminGuard], title: 'Utenti e abbonamenti - Palestra' },
      { path: 'admin/bookables', loadComponent: () => import('./pages/admin/admin-bookables.component/admin-bookables.component').then((m) => m.AdminBookablesComponent), canActivate: [adminGuard], title: 'Gestione sale - Palestra' },
      { path: 'admin/bookables/new', loadComponent: () => import('./pages/admin/admin-bookable-form.component/admin-bookable-form.component').then((m) => m.AdminBookableFormComponent), canActivate: [adminGuard], title: 'Nuova sala - Palestra' },
      { path: 'admin/bookables/:id/edit', loadComponent: () => import('./pages/admin/admin-bookable-form.component/admin-bookable-form.component').then((m) => m.AdminBookableFormComponent), canActivate: [adminGuard], title: 'Modifica sala - Palestra' },
      { path: 'admin/bookables/:id/availabilities', loadComponent: () => import('./pages/admin/admin-availabilities.component/admin-availabilities.component').then((m) => m.AdminAvailabilitiesComponent), canActivate: [adminGuard], title: 'Fasce orarie - Palestra' },
    ],
  },

  // Indirizzo sconosciuto: torna alla pagina pubblica
  { path: '**', redirectTo: '' },
];
