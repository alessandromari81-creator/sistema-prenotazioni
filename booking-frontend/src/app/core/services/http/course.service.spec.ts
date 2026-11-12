import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { CourseService } from './course.service';

describe('CourseService (immagine del corso)', () => {
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
});
