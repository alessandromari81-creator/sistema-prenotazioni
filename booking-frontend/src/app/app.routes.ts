import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { adminGuard } from './core/guards/admin.guard';
import { LoginComponent } from './pages/auth/login/login.component';
import { BookablesListComponent } from './pages/booking/bookables-list.component/bookables-list.component';
import { BookableDetailComponent } from './pages/booking/bookable-detail.component/bookable-detail.component';
import { MyBookingsComponent } from './pages/booking/my-bookings.component/my-bookings.component';
import { AdminBookablesComponent } from './pages/admin/admin-bookables.component/admin-bookables.component';
import { AdminBookableFormComponent } from './pages/admin/admin-bookable-form.component/admin-bookable-form.component';
import { AdminAvailabilitiesComponent } from './pages/admin/admin-availabilities.component/admin-availabilities.component';
import { RegisterComponent } from './pages/auth/register/register.component';
import { ConfirmRegistrationComponent } from './pages/auth/confirm-registration/confirm-registration.component';
import { HomeComponent } from './pages/home.component';
import { LayoutComponent } from './layout/layout.component';

export const routes: Routes = [
  {
    path: '',
    component: LayoutComponent,   // il layout con barra e menu avvolge tutte le pagine qui sotto
    children: [
      // Pagina pubblica: nessun guard
      { path: '', component: HomeComponent, pathMatch: 'full' },

      { path: 'login', component: LoginComponent },
      { path: 'register', component: RegisterComponent },
      // Pagina raggiunta dal link nella mail di conferma
      { path: 'confirm-registration', component: ConfirmRegistrationComponent },
      { path: 'bookables', component: BookablesListComponent, canActivate: [authGuard] },
      { path: 'bookables/:id', component: BookableDetailComponent, canActivate: [authGuard] },
      { path: 'my-bookings', component: MyBookingsComponent, canActivate: [authGuard] },

      // Area admin: accessibile solo con role = 'admin' (adminGuard)
      { path: 'admin', pathMatch: 'full', redirectTo: 'admin/bookables' },
      { path: 'admin/bookables', component: AdminBookablesComponent, canActivate: [adminGuard] },
      { path: 'admin/bookables/new', component: AdminBookableFormComponent, canActivate: [adminGuard] },
      { path: 'admin/bookables/:id/edit', component: AdminBookableFormComponent, canActivate: [adminGuard] },
      { path: 'admin/bookables/:id/availabilities', component: AdminAvailabilitiesComponent, canActivate: [adminGuard] },

    ],
  },

  // Indirizzo sconosciuto: torna alla pagina pubblica
  { path: '**', redirectTo: '' },
];