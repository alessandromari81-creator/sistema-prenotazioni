import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { CourseService } from './course.service';

describe('CourseService (immagine e materiali del corso)', () => {
  let service: CourseService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(CourseService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('uploadImage invia il file in un form multipart (POST, campo "image")', () => {
    const file = new File(['x'], 'foto.png', { type: 'image/png' });
    let result: unknown;

    service.uploadImage(5, file).subscribe((c) => (result = c));
    const req = http.expectOne('/api/courses/5/image');

    expect(req.request.method).toBe('POST');
    expect(req.request.body instanceof FormData).toBe(true);
    expect((req.request.body as FormData).get('image')).toBe(file);
    req.flush({ id: 5, image_url: '/api/courses/5/image?v=1' });
    expect(result).toEqual({ id: 5, image_url: '/api/courses/5/image?v=1' });
  });

  it('removeImage usa DELETE e non si aspetta nessun corpo (204)', () => {
    let done = false;

    service.removeImage(5).subscribe(() => (done = true));
    const req = http.expectOne('/api/courses/5/image');

    expect(req.request.method).toBe('DELETE');
    req.flush(null, { status: 204, statusText: 'No Content' });
    expect(done).toBe(true);
  });

  it('materials chiede l\'elenco dei PDF del corso', () => {
    let list: unknown;

    service.materials(5).subscribe((m) => (list = m));
    const req = http.expectOne('/api/courses/5/materials');

    expect(req.request.method).toBe('GET');
    req.flush([{ id: 1, title: 'Scheda' }]);
    expect(list).toEqual([{ id: 1, title: 'Scheda' }]);
  });

  it('materialFile scarica il PDF come Blob (con il token aggiunto dall\'interceptor)', () => {
    let result: Blob | undefined;

    service.materialFile(5, 9).subscribe((b) => (result = b));
    const req = http.expectOne('/api/courses/5/materials/9');

    expect(req.request.method).toBe('GET');
    expect(req.request.responseType).toBe('blob');
    req.flush(new Blob(['%PDF']));
    expect(result instanceof Blob).toBe(true);
  });

  it('uploadMaterial invia titolo e file in un form multipart', () => {
    const file = new File(['x'], 'd.pdf', { type: 'application/pdf' });

    service.uploadMaterial(5, 'Dispensa', file).subscribe();
    const req = http.expectOne('/api/courses/5/materials');
    const body = req.request.body as FormData;

    expect(req.request.method).toBe('POST');
    expect(body.get('title')).toBe('Dispensa');
    expect(body.get('file')).toBe(file);
    req.flush({ id: 1 });
  });

  it('deleteMaterial usa DELETE e non si aspetta nessun corpo (204)', () => {
    let done = false;

    service.deleteMaterial(5, 9).subscribe(() => (done = true));
    const req = http.expectOne('/api/courses/5/materials/9');

    expect(req.request.method).toBe('DELETE');
    req.flush(null, { status: 204, statusText: 'No Content' });
    expect(done).toBe(true);
  });
});
