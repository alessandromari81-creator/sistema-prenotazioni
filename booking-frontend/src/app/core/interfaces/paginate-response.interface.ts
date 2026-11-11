export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string | null;
  last_page: number;
  last_page_url: string | null;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  next_page_url: string | null;
  prev_page_url: string | null;
}