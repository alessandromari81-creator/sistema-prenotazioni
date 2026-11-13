import { isChunkLoadError } from './chunk-error';

describe('isChunkLoadError', () => {
  it('riconosce il messaggio di Chrome', () => {
    expect(isChunkLoadError(new TypeError('Failed to fetch dynamically imported module: http://localhost/chunk-ABC.js'))).toBe(true);
  });

  it('riconosce il messaggio di Safari (il server ha risposto con una pagina HTML)', () => {
    expect(isChunkLoadError(new TypeError("'text/html' is not a valid JavaScript MIME type for module script 'http://localhost:8080/chunk-Cc_xnoE9.js'."))).toBe(true);
  });

  it('riconosce il messaggio di Firefox', () => {
    expect(isChunkLoadError(new TypeError('error loading dynamically imported module: http://localhost/chunk-ABC.js'))).toBe(true);
  });

  it('riconosce gli errori di webpack e simili', () => {
    expect(isChunkLoadError(new Error('Loading chunk 12 failed.'))).toBe(true);
    expect(isChunkLoadError({ message: 'x' })).toBe(false);
  });

  it('non scambia altri errori per errori di caricamento', () => {
    expect(isChunkLoadError(new Error('Cannot read properties of undefined'))).toBe(false);
    expect(isChunkLoadError(null)).toBe(false);
    expect(isChunkLoadError(undefined)).toBe(false);
  });
});
