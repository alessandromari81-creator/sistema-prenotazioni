import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';
import { FileSaverService } from './file-saver.service';

describe('FileSaverService', () => {
  it('fa scaricare il file con il nome indicato e libera l\'indirizzo temporaneo', () => {
    URL.createObjectURL = vi.fn().mockReturnValue('blob:file');
    URL.revokeObjectURL = vi.fn();
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    let name = '';
    click.mockImplementation(function (this: HTMLAnchorElement) { name = this.download; });

    TestBed.inject(FileSaverService).save(new Blob(['x']), 'dispensa.pdf');

    expect(click).toHaveBeenCalled();
    expect(name).toBe('dispensa.pdf');
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:file');
    expect(document.querySelector('a[download]')).toBeNull(); // il link temporaneo non resta nella pagina
    click.mockRestore();
  });

  it('show apre il PDF nella scheda già aperta, come PDF', () => {
    URL.createObjectURL = vi.fn().mockReturnValue('blob:pdf');
    URL.revokeObjectURL = vi.fn();
    vi.useFakeTimers();
    const tab = { location: { href: '' } } as unknown as Window;

    TestBed.inject(FileSaverService).show(new Blob(['x']), tab, 'dispensa.pdf');

    expect((URL.createObjectURL as ReturnType<typeof vi.fn>).mock.calls[0][0].type).toBe('application/pdf');
    expect(tab.location.href).toBe('blob:pdf');
    expect(URL.revokeObjectURL).not.toHaveBeenCalled(); // la scheda lo sta ancora usando
    vi.advanceTimersByTime(60_000);
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:pdf');
    vi.useRealTimers();
  });

  it('show, se la scheda è stata bloccata, scarica il file', () => {
    URL.createObjectURL = vi.fn().mockReturnValue('blob:pdf');
    URL.revokeObjectURL = vi.fn();
    let name = '';
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) { name = this.download; });

    TestBed.inject(FileSaverService).show(new Blob(['x']), null, 'dispensa.pdf');

    expect(name).toBe('dispensa.pdf');
    click.mockRestore();
  });
});
