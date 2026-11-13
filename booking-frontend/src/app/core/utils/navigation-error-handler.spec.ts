import { TestBed } from '@angular/core/testing';
import { NavigationError } from '@angular/router';
import { vi } from 'vitest';
import { AppVersionService } from '../services/features/app-version.service';
import { handleNavigationError } from './navigation-error-handler';

describe('handleNavigationError', () => {
  const appVersion = { notifyOutdated: vi.fn() };

  beforeEach(() => {
    appVersion.notifyOutdated.mockReset();
    TestBed.configureTestingModule({ providers: [{ provide: AppVersionService, useValue: appVersion }] });
  });

  const run = (error: unknown) => TestBed.runInInjectionContext(() => handleNavigationError({ error } as NavigationError));

  it('se un pezzo dell\'app non si scarica invita ad aggiornare', () => {
    run(new TypeError("'text/html' is not a valid JavaScript MIME type for module script 'http://localhost:8080/chunk-Cc_xnoE9.js'."));

    expect(appVersion.notifyOutdated).toHaveBeenCalledTimes(1);
  });

  it('ogni altro errore viene rilanciato e non mostra nessun avviso', () => {
    const error = new Error('Qualcosa di diverso');

    expect(() => run(error)).toThrow(error);
    expect(appVersion.notifyOutdated).not.toHaveBeenCalled();
  });
});
