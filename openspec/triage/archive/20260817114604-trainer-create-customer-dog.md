# Triage: trainer-create-customer-dog

**Pfad:** standard
**Geschätzter Umfang:** 2–4 Dateien, PHP (Backend) — Frontend voraussichtlich unverändert
**Risiko:** mittel — betrifft die Autorisierungslogik für User-Account-Erstellung (Rechteausweitung, Rollenzuweisung); Fehler hier ermöglichen Privilege Escalation
**Klarheit:** mehrdeutig — Kernanforderung ist klar, aber eine sicherheitsrelevante Detailfrage ist offen (siehe unten)

## Anforderung (Zusammenfassung)

Als Trainer soll der User auch die Möglichkeit haben, einen neuen Kunden
("Owner") und einen Hund anzulegen — nicht nur Admins. Aktuell scheitert
dieser Workflow für Trainer an einer Stelle in der Backend-Autorisierung
(siehe Befunde).

## Befunde aus dem Repo

| Datei | Befund |
|---|---|
| `backend/app/Policies/CustomerPolicy.php:43-47` | `create()` erlaubt bereits `isAdmin() || isTrainer()` |
| `backend/app/Policies/DogPolicy.php:43-47` | `create()` erlaubt bereits `isAdminOrTrainer()` |
| `backend/app/Http/Requests/StoreCustomerRequest.php:20-24` | `authorize()` erlaubt bereits Admin oder Trainer |
| `backend/app/Http/Requests/StoreDogRequest.php:21-25` | `authorize()` erlaubt bereits `isAdminOrTrainer()` |
| `frontend/src/layouts/DefaultLayout.vue:193-202` | Navigation zeigt "Kunden" (admin+trainer) und "Hunde" (admin+trainer+customer) bereits für Trainer an |
| `frontend/src/views/customers/CustomersView.vue:11` | "Neuer Kunde"-Button ist ungefiltert sichtbar (kein Rollen-Check) |
| `frontend/src/views/dogs/DogsView.vue:12` | "Neuer Hund"-Button sichtbar für `role !== 'customer'`, also auch Trainer |
| `frontend/src/components/CustomerFormModal.vue:518-527` | Beim Anlegen eines **neuen** Kunden ruft das Frontend zuerst `POST /api/v1/auth/register` (role: 'customer') auf, um den zugehörigen User-Account zu erzeugen, danach `POST /api/v1/customers` |
| `backend/routes/api.php:83-85` | Kommentar sagt **"User registration (Admins and Trainers only)"**, aber … |
| `backend/app/Http/Requests/RegisterRequest.php:20-28` | … `authorize()` prüft nur `$user->isAdmin()`. Trainer werden abgelehnt (403), obwohl der Docblock der Klasse explizit sagt: *"Admins can create any user, trainers can only create customers."* Die Einschränkung auf `role === 'customer'` für Trainer ist in `rules()` nicht implementiert. |
| `backend/tests/Feature/AuthenticationTest.php:126-140` | Test `'non-admin cannot register new user'` erstellt aktuell explizit einen Trainer und erwartet 403 — das ist der bestehende Soll-Zustand, der geändert werden muss |

**Kernbefund:** Die Autorisierung für Dog- und Customer-Erstellung selbst
ist bereits trainer-fähig. Der tatsächliche Blocker ist ausschließlich
`RegisterRequest::authorize()` (Endpunkt `POST /api/v1/auth/register`),
der für den "Neuer Kunde"-Flow im Frontend zwingend zuerst aufgerufen wird,
um den User-Account des neuen Kunden zu erzeugen. Das Anlegen eines
**Hundes für einen bereits existierenden Kunden** funktioniert für Trainer
schon heute vollständig (kein User-Account nötig).

## Rückfragen an den User

- Der Docblock von `RegisterRequest` deutet an, dass Trainer beim
  Registrieren neuer Accounts ausschließlich `role: 'customer'` setzen
  dürfen sollen (keine `admin`- oder `trainer`-Accounts anlegen können).
  Ist das die gewünschte Einschränkung, oder sollen Trainer beliebige
  Rollen registrieren dürfen? (Aus Sicherheitssicht wird dringend zur
  Einschränkung auf `customer` geraten — Privilege-Escalation sonst
  möglich: ein Trainer könnte sich sonst z. B. einen zweiten Admin-Account
  erstellen.)
- Soll ein neu angelegter Kunde bei Erstellung durch einen Trainer
  automatisch diesem Trainer zugewiesen werden? (Das Frontend setzt das
  bereits so um, `CustomerFormModal.vue:302-304` — nur zur Bestätigung,
  dass dieses Verhalten beibehalten werden soll.)

## Empfohlene nächste Aktion

`@architect` (Modus A) mit dieser Triage-Datei aufrufen. Vorschlag für den
Change-Zuschnitt (ein `dev-php`-Task genügt voraussichtlich):

1. `RegisterRequest::authorize()` erweitern: Admin ODER Trainer.
2. `RegisterRequest::rules()`/Validierung ergänzen: Wenn der anfragende
   User Trainer ist, muss `role` zwingend `'customer'` sein (z. B. via
   `Rule::in()` dynamisch pro Rolle des Anfragenden, oder `withValidator`).
3. `AuthenticationTest.php` anpassen: bestehenden Test
   `'non-admin cannot register new user'` präzisieren (Kunde weiterhin
   403; Trainer jetzt erlaubt, aber nur mit `role: 'customer'`) und neue
   Testfälle ergänzen: Trainer registriert Kunde → 201; Trainer versucht
   Admin/Trainer zu registrieren → 403.
4. Kein Frontend-Task nötig — UI, Navigation und Buttons sind bereits
   trainer-fähig.

Trotz des kleinen Dateiumfangs wird der volle Standard-Workflow (inkl.
Skeptiker und User-Gate 1) empfohlen, da Änderungen an der
Registrierungs-Autorisierung sicherheitskritisch sind (Rollenzuweisung /
Privilege Escalation).
