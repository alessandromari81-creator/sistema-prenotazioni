# booking-frontend

Frontend Angular del sistema di prenotazioni. Requisiti e avvio completo sono nel
[README principale](../README.md).

```bash
npm install
ng serve --proxy-config proxy.conf.json   # il proxy inoltra /api e /sanctum al backend
```

L'app è su `http://localhost:4200`. Il backend deve essere acceso (`booking-api`).
