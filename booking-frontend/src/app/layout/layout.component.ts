import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatListModule } from '@angular/material/list';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { AuthService } from '../core/services/http/auth.service';

// Layout comune a tutte le pagine: barra in alto + menu laterale (hamburger).
// Le pagine vere vengono mostrate dentro <router-outlet />.
@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatListModule,
    MatIconModule,
    MatButtonModule,
    MatDividerModule,
  ],
templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent {
  protected auth = inject(AuthService);
  private router = inject(Router);

  // Titolo mostrato al centro della barra e in cima al menu
  appTitle = 'Sistema di prenotazioni';

  /** Esce dall'account e porta alla pagina di login. */
  logout(): void {
    this.auth.logout();
  }
}