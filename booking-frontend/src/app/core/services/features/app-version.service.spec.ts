import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { AppVersionService } from './app-version.service';
import { NotificationService } from './notification.service';
import { APP_VERSION } from '../../../../environments/version';

describe('AppVersionService', () => {
  let notifications: { action: ReturnType<typeof vi.fn> };
  let service: AppVersionService;

  beforeEach(() => {
    notifications = { action: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: NotificationService, useValue: notifications }] });
    service = TestBed.inject(AppVersionService);
  });

  it('se il server dichiara una versione diversa avvisa che c\'è una versione nuova', () => {
    service.check('versione-nuova');

    expect(notifications.action).toHaveBeenCalledWith(expect.stringContaining('nuova versione'), 'Aggiorna', expect.any(Function));
  });

  it('se la versione è la stessa dell\'app non avvisa', () => {
    service.check(APP_VERSION);

    expect(notifications.action).not.toHaveBeenCalled();
  });

  it('se il server non dichiara nessuna versione non avvisa', () => {
    service.check(null);
    service.check('');

    expect(notifications.action).not.toHaveBeenCalled();
  });

  it('avvisa una volta sola, anche se le risposte successive portano la stessa versione diversa', () => {
    service.check('versione-nuova');
    service.check('versione-nuova');
    service.check('un-altra');

    expect(notifications.action).toHaveBeenCalledTimes(1);
  });

  it('notifyOutdated avvisa una volta sola e condivide l\'avviso con check', () => {
    service.notifyOutdated();
    service.notifyOutdated();
    service.check('versione-nuova');

    expect(notifications.action).toHaveBeenCalledTimes(1);
  });

  it('il pulsante "Aggiorna" ricarica la pagina', () => {
    const reload = vi.fn();
    vi.stubGlobal('location', { ...window.location, reload });
    service.check('versione-nuova');

    notifications.action.mock.calls[0][2]();

    expect(reload).toHaveBeenCalled();
    vi.unstubAllGlobals();
  });
});
