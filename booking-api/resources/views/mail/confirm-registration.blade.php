<x-mail::message>
# Ciao {{ $name }}!

Per completare la registrazione conferma il tuo indirizzo email.
Il link è valido per {{ \App\Models\PendingRegistration::TTL_HOURS }} ore. Se ti registri più volte,
vale solo l'ultima email ricevuta.

<x-mail::button :url="$confirmUrl">
Conferma registrazione
</x-mail::button>

Se non sei stato tu a registrarti, ignora questa email: nessun account verrà creato.
</x-mail::message>
