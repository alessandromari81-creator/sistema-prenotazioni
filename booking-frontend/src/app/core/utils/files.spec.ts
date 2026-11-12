import { formatFileSize } from './files';

describe('formatFileSize', () => {
  it('scrive i byte, i KB e i MB', () => {
    expect(formatFileSize(850)).toBe('850 byte');
    expect(formatFileSize(1024)).toBe('1 KB');
    expect(formatFileSize(120 * 1024)).toBe('120 KB');
    expect(formatFileSize(1.5 * 1024 * 1024)).toBe('1,5 MB');
  });
});
