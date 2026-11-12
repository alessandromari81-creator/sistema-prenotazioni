import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { SessionSyncService } from './session-sync.service';

/** Un canale finto: tiene i messaggi mandati e permette di simularne l'arrivo da un'altra scheda. */
class FakeChannel {
  static last: FakeChannel;
  onmessage: ((event: MessageEvent) => void) | null = null;
  postMessage = vi.fn();
  close = vi.fn();
  constructor(public name: string) {
    FakeChannel.last = this;
  }
}

describe('SessionSyncService', () => {
  beforeEach(() => {
    vi.stubGlobal('BroadcastChannel', FakeChannel);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('manda i messaggi sul canale dell\'app', () => {
    const service = TestBed.inject(SessionSyncService);

    service.announce({ type: 'logout' });

    expect(FakeChannel.last.name).toBe('palestra-auth');
    expect(FakeChannel.last.postMessage).toHaveBeenCalledWith({ type: 'logout' });
  });

  it('gira al gestore i messaggi validi', () => {
    const service = TestBed.inject(SessionSyncService);
    const handler = vi.fn();
    service.listen(handler);

    FakeChannel.last.onmessage!({ data: { type: 'logout' } } as MessageEvent);
    FakeChannel.last.onmessage!({ data: { type: 'login', userId: 3 } } as MessageEvent);

    expect(handler).toHaveBeenCalledTimes(2);
    expect(handler).toHaveBeenCalledWith({ type: 'login', userId: 3 });
  });

  it('ignora i messaggi che non conosce o malformati', () => {
    const service = TestBed.inject(SessionSyncService);
    const handler = vi.fn();
    service.listen(handler);

    FakeChannel.last.onmessage!({ data: { type: 'altro' } } as MessageEvent);
    FakeChannel.last.onmessage!({ data: { type: 'login', userId: 'x' } } as MessageEvent);
    FakeChannel.last.onmessage!({ data: null } as MessageEvent);

    expect(handler).not.toHaveBeenCalled();
  });

  it('senza BroadcastChannel nel browser non si rompe', () => {
    vi.stubGlobal('BroadcastChannel', undefined);
    const service = TestBed.inject(SessionSyncService);

    expect(() => {
      service.announce({ type: 'logout' });
      service.listen(vi.fn());
    }).not.toThrow();
  });

  it('chiude il canale quando il servizio viene distrutto', () => {
    TestBed.inject(SessionSyncService);
    const channel = FakeChannel.last;

    TestBed.resetTestingModule();

    expect(channel.close).toHaveBeenCalled();
  });
});
