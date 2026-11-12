// Impostazioni dell'applicazione.
export const environment = {
  // URL relativo: passa dal proxy (ng serve) o da nginx (Docker), quindi ha la stessa origine del frontend
  apiUrl: '/api',

  // Keycloak: il server che gestisce login, registrazione e password. L'indirizzo è quello che raggiunge il BROWSER.
  keycloak: {
    url: 'http://localhost:8180',
    realm: 'palestra',
    clientId: 'palestra-spa', // client "pubblico" (senza segreto): la sicurezza è nel flusso PKCE
  },
};
