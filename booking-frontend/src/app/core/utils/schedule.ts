// Funzioni di supporto per mostrare giorni e orari dei corsi in italiano.

const DAY_NAMES = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

/** I giorni della settimana come coppie valore-nome (1 = lunedì ... 7 = domenica), per i menu a tendina. */
export const WEEK_DAYS = DAY_NAMES.slice(1).map((name, index) => ({ value: index + 1, name }));

/** Il nome del giorno ("Lunedì") dal numero ISO 1-7. Restituisce una stringa vuota per valori fuori intervallo. */
export function dayName(day: number): string {
  return DAY_NAMES[day] ?? '';
}

/** Porta un orario "HH:MM:SS" (come arriva dal backend) al formato "HH:MM". */
export function shortTime(time: string): string {
  return time.slice(0, 5);
}

/** Calcola l'ora di fine ("HH:MM") sommando la durata in minuti all'ora di inizio ("HH:MM" o "HH:MM:SS"). */
export function endTime(start: string, durationMinutes: number): string {
  const [hours, minutes] = start.split(':').map(Number);
  const total = (hours * 60 + minutes + durationMinutes) % (24 * 60);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
}

/** Descrive quando si tiene un corso, per esempio "Lunedì dalle 18:30 alle 19:30". */
export function courseSchedule(day: number, start: string, durationMinutes: number): string {
  return `${dayName(day)} dalle ${shortTime(start)} alle ${endTime(start, durationMinutes)}`;
}
