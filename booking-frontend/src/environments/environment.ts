// URL relativi: passano dal proxy, quindi stessa origine del frontend
export const environment = {
  apiUrl: '/api',
  csrfUrl: '/sanctum/csrf-cookie', // endpoint di Sanctum che imposta il cookie CSRF
};