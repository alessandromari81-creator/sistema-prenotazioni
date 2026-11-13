// Versione con cui è stata costruita l'app. In sviluppo vale "dev".
// Nel Dockerfile questo file viene riscritto con il valore di APP_VERSION (es. l'hash del commit):
// deve essere lo stesso che il backend manda nell'intestazione "X-App-Version".
export const APP_VERSION = 'dev';
