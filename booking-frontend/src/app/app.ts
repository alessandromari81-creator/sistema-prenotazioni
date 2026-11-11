import { Component, inject } from '@angular/core';;
import { AuthService } from './core/services/http/auth.service';
import { RouterOutlet } from '@angular/router';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    RouterOutlet, 
    MatToolbarModule,
    MatButtonModule,
    MatIconModule
  ],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App { // se la tua classe si chiama AppComponent, lascia quel nome
  auth = inject(AuthService);
}