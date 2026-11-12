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
});
