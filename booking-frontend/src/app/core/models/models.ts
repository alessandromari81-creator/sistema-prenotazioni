// Utente restituito dalle API (role: 'admin' | 'user').
// Il profilo nasce in Laravel al primo accesso; credenziali e ruoli stanno in Keycloak.
export interface User {
  id: number;
  name: string;
  email: string;
  address?: string | null;
  role: string;
  teaches_courses?: boolean; // c'è solo in GET /api/user: vero se l'utente insegna in almeno un corso
}

// Fascia oraria settimanale di una sala.
// day_of_week: 1 = lunedì ... 7 = domenica; gli orari arrivano come "HH:MM:SS"
export interface Availability {
  id: number;
  bookable_id: number;
  day_of_week: number;
  start_time: string;
  end_time: string;
}

// Dati che l'admin invia per aggiungere una fascia (orari nel formato "HH:MM")
export interface AvailabilityInput {
  day_of_week: number;
  start_time: string;
  end_time: string;
}

// Sala prenotabile (campi come nella tabella "bookables" del backend)
export interface Bookable {
  id: number;
  name: string;
  description?: string | null;
  slot_minutes: number; // durata di uno slot in minuti (5-480)
  is_active: boolean;   // false = nascosta agli utenti normali
  availabilities?: Availability[]; // le fasce ci sono solo se il backend le carica
}

// Dati che l'admin invia per creare o modificare una sala
export interface BookableInput {
  name: string;
  description: string | null;
  slot_minutes: number;
  is_active: boolean;
}

// Slot libero restituito da GET /bookables/{id}/slots (date ISO con offset)
export interface Slot {
  starts_at: string;
  ends_at: string;
}

// Prenotazione dell'utente; "bookable" c'è solo se il backend carica la relazione
export interface Booking {
  id: number;
  bookable_id: number;
  starts_at: string;
  ends_at?: string;
  bookable?: Bookable;
  status: 'confirmed' | 'cancelled';   // stato della prenotazione, come lo restituisce il backend
}

// ---------- Corsi ----------

// Un istruttore come lo vedono gli altri utenti: solo identificativo e nome
export interface Instructor {
  id: number;
  name: string;
}

// Un corso della palestra. I campi dopo "bookable" li calcola il backend per chi guarda.
export interface Course {
  id: number;
  name: string;
  description: string | null;
  capacity: number;          // posti disponibili
  day_of_week: number;       // 1 = lunedì ... 7 = domenica
  start_time: string;        // "HH:MM:SS", ora di Roma
  duration_minutes: number;
  is_active: boolean;        // false = non accetta iscrizioni (visibile solo agli admin)
  bookable_id: number | null;
  bookable?: Bookable | null; // la sala, se indicata
  instructors?: Instructor[];
  enrolled_count?: number;   // iscritti con iscrizione valida
  spots_left?: number;       // posti ancora liberi
  is_enrolled?: boolean;     // chi guarda è iscritto?
  is_instructor?: boolean;   // chi guarda è istruttore del corso?
}

// Dati che l'admin invia per creare o modificare un corso (start_time nel formato "HH:MM")
export interface CourseInput {
  name: string;
  description: string | null;
  capacity: number;
  day_of_week: number;
  start_time: string;
  duration_minutes: number;
  bookable_id: number | null;
  is_active: boolean;
}

// Uno studente come lo vedono admin e istruttori del corso
export interface Student {
  id: number;
  name: string;
  email: string;
}

// Iscrizione a un corso, come appare nell'elenco degli iscritti ("student"). Iscriversi e annullare rispondono 204 senza corpo.
export interface Enrollment {
  id: number;
  course_id: number;
  user_id: number;
  status: 'active' | 'cancelled';
  student?: Student;
  created_at: string | null;
}

// ---------- Abbonamenti ----------

// Piano di abbonamento: durata e numero massimo di corsi contemporanei
export interface SubscriptionPlan {
  id: number;
  name: string;
  description: string | null;
  duration_days: number;
  max_courses: number;
  is_active: boolean;
}

// Dati che l'admin invia per creare o modificare un piano
export interface SubscriptionPlanInput {
  name: string;
  description: string | null;
  duration_days: number;
  max_courses: number;
  is_active: boolean;
}

// Abbonamento di un utente. Le date sono giorni "AAAA-MM-GG", estremi inclusi.
export interface Subscription {
  id: number;
  user_id: number;
  subscription_plan_id: number;
  starts_on: string;
  ends_on: string;
  cancelled_at: string | null;
  is_active: boolean; // vero se non è annullato e oggi è nel periodo
  plan?: SubscriptionPlan;
}

// Riepilogo dell'abbonamento dell'utente: quello attivo (o null), corsi in uso e ancora disponibili
export interface SubscriptionUsage {
  subscription: Subscription | null;
  courses_used: number;
  courses_left: number;
}
