// Utente restituito dalle API (role: 'admin' | 'user')
export interface User {
  id: number;
  name: string;
  email: string;
  address?: string | null; // assente negli utenti creati prima dell'introduzione dell'indirizzo
  role: string;
}

// Dati del form di registrazione
export interface RegisterInput {
  name: string;
  email: string;
  password: string;
  address: string;
}

// Fascia oraria settimanale di una risorsa.
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

// Risorsa prenotabile (campi come nella tabella "bookables" del backend)
export interface Bookable {
  id: number;
  name: string;
  description?: string | null;
  slot_minutes: number; // durata di uno slot in minuti (5-480)
  is_active: boolean;   // false = nascosta agli utenti normali
  availabilities?: Availability[]; // le fasce ci sono solo se il backend le carica
}

// Dati che l'admin invia per creare o modificare una risorsa
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