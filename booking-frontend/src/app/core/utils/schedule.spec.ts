import { courseSchedule, dayName, endTime, shortTime, WEEK_DAYS } from './schedule';

describe('schedule', () => {
  it('dà il nome italiano del giorno da 1 a 7', () => {
    expect(dayName(1)).toBe('Lunedì');
    expect(dayName(7)).toBe('Domenica');
  });

  it('restituisce una stringa vuota per un giorno fuori intervallo', () => {
    expect(dayName(0)).toBe('');
    expect(dayName(8)).toBe('');
  });

  it('elenca i sette giorni in ordine, per i menu a tendina', () => {
    expect(WEEK_DAYS).toHaveLength(7);
    expect(WEEK_DAYS[0]).toEqual({ value: 1, name: 'Lunedì' });
    expect(WEEK_DAYS[6]).toEqual({ value: 7, name: 'Domenica' });
  });

  it('accorcia un orario HH:MM:SS a HH:MM', () => {
    expect(shortTime('18:30:00')).toBe('18:30');
    expect(shortTime('09:05')).toBe('09:05');
  });

  it('calcola l\'ora di fine sommando la durata', () => {
    expect(endTime('18:30', 60)).toBe('19:30');
    expect(endTime('18:30:00', 45)).toBe('19:15');
    expect(endTime('09:00', 5)).toBe('09:05');
  });

  it('riparte da mezzanotte se la lezione finisce dopo le 24', () => {
    expect(endTime('23:30', 60)).toBe('00:30');
  });

  it('descrive quando si tiene un corso', () => {
    expect(courseSchedule(1, '18:30:00', 60)).toBe('Lunedì dalle 18:30 alle 19:30');
  });
});
