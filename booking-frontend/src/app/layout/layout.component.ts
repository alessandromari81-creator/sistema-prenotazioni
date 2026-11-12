import { Component, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
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

  // Titolo mostrato al centro della barra e in cima al menu
  appTitle = 'Palestra';

  constructor() {
    // Accessibilità (WCAG 2.4.3): in una pagina a schermata unica il browser non "ricarica" la pagina quando si cambia
    // rotta, quindi chi usa tastiera o lettore di schermo resterebbe con il focus sul link appena premuto.
    // A ogni cambio di pagina (tranne il primo caricamento) il focus passa al contenuto principale.
    let firstNavigation = true;

    inject(Router)
      .events.pipe(
        filter((event) => event instanceof NavigationEnd),
        takeUntilDestroyed()
      )
      .subscribe(() => {
        if (firstNavigation) {
          firstNavigation = false;
          return;
        }
        document.getElementById('main-content')?.focus();
      });
  }

  /** Porta alla pagina di accesso di Keycloak. */
  login(): void {
    void this.auth.login();
  }

  /** Porta alla pagina di registrazione di Keycloak. */
  register(): void {
    void this.auth.register();
  }

  /** Esce dall'account (chiude la sessione su Keycloak) e torna alla pagina iniziale. */
  logout(): void {
    void this.auth.logout();
  }
}
