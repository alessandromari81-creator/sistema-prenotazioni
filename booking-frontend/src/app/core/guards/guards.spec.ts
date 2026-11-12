import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { AuthService } from '../services/http/auth.service';
import { adminGuard } from './admin.guard';
import { authGuard } from './auth.guard';
import { instructorGuard } from './instructor.guard';

describe('guardie', () => {
  let auth: {
    isLoggedIn: ReturnType<typeof vi.fn>;
    isAdmin: ReturnType<typeof vi.fn>;
    teachesCourses: ReturnType<typeof vi.fn>;
    login: ReturnType<typeof vi.fn>;
  };

  const route = {} as ActivatedRouteSnapshot;
  const state = { url: '/admin/courses' } as RouterStateSnapshot;

  beforeEach(() => {
    auth = {
      isLoggedIn: vi.fn().mockReturnValue(false),
      isAdmin: vi.fn().mockReturnValue(false),
      teachesCourses: vi.fn().mockReturnValue(false),
      login: vi.fn().mockResolvedValue(undefined),
    };

    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: AuthService, useValue: auth }],
    });
  });

  const run = (guard: typeof authGuard) => TestBed.runInInjectionContext(() => guard(route, state));

  describe('authGuard', () => {
    it('lascia passare un utente collegato', () => {
      auth.isLoggedIn.mockReturnValue(true);

      expect(run(authGuard)).toBe(true);
      expect(auth.login).not.toHaveBeenCalled();
    });

    it('manda un anonimo al login di Keycloak, ricordando la pagina richiesta', () => {
      expect(run(authGuard)).toBe(false);
      expect(auth.login).toHaveBeenCalledWith('/admin/courses');
    });
  });

  describe('adminGuard', () => {
    it('lascia passare un amministratore', () => {
      auth.isLoggedIn.mockReturnValue(true);
      auth.isAdmin.mockReturnValue(true);

      expect(run(adminGuard)).toBe(true);
    });

    it('rimanda un utente normale all\'elenco dei corsi', () => {
      auth.isLoggedIn.mockReturnValue(true);

      const result = run(adminGuard);

      expect(result).toBeInstanceOf(UrlTree);
      expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/courses');
    });

    it('manda un anonimo al login', () => {
      expect(run(adminGuard)).toBe(false);
      expect(auth.login).toHaveBeenCalledWith('/admin/courses');
    });
  });

  describe('instructorGuard', () => {
    it('lascia passare chi insegna', () => {
      auth.isLoggedIn.mockReturnValue(true);
      auth.teachesCourses.mockReturnValue(true);

      expect(run(instructorGuard)).toBe(true);
    });

    it('rimanda all\'elenco dei corsi chi non insegna', () => {
      auth.isLoggedIn.mockReturnValue(true);

      const result = run(instructorGuard);

      expect(TestBed.inject(Router).serializeUrl(result as UrlTree)).toBe('/courses');
    });

    it('manda un anonimo al login', () => {
      expect(run(instructorGuard)).toBe(false);
      expect(auth.login).toHaveBeenCalled();
    });

    it('un amministratore che non insegna non passa: la pagina è solo per gli istruttori', () => {
      auth.isLoggedIn.mockReturnValue(true);
      auth.isAdmin.mockReturnValue(true);

      expect(run(instructorGuard)).toBeInstanceOf(UrlTree);
    });
  });
});
