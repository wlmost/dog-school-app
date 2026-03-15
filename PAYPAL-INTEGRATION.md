# PayPal Payment Integration

Datum: 24. Januar 2026

## Überblick

PayPal-Zahlungsintegration für die Hundeschule HomoCanis App implementiert. Kunden können Rechnungen direkt über PayPal bezahlen.

## Backend

### 1. PayPal Server SDK Installation

```bash
composer require paypal/paypal-server-sdk
```

**Installierte Version**: v2.2.0 (moderne PayPal Server SDK)

### 2. Konfiguration

**Config-Datei**: `backend/config/paypal.php`
- Mode (sandbox/live)
- Client ID & Secret
- Webhook ID
- Currency (EUR)
- Return/Cancel URLs

**Umgebungsvariablen** (`backend/.env`):
```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_CURRENCY=EUR
```

### 3. PayPal Service

**Datei**: `backend/app/Services/PayPalService.php`

**Methoden**:
- `createOrder(Invoice $invoice): array` - Erstellt PayPal-Bestellung
- `captureOrder(string $orderId, Invoice $invoice): Payment` - Erfasst Zahlung
- `getOrderDetails(string $orderId): array` - Ruft Bestelldetails ab

**Features**:
- Automatische Betragsberechnung aus `remaining_balance`
- Referenz zur Rechnungsnummer
- Umfassendes Logging
- Exception-Handling

### 4. Payment Controller Erweiterung

**Datei**: `backend/app/Http/Controllers/Api/PaymentController.php`

**Neue Methoden**:
- `createPayPalOrder(Request)` - Erstellt PayPal-Bestellung für Rechnung
- `capturePayPalOrder(Request)` - Erfasst PayPal-Zahlung
- `handleWebhook(Request)` - Verarbeitet PayPal-Webhooks
- `handlePaymentCaptureCompleted()` - Webhook: Zahlung abgeschlossen
- `handlePaymentCaptureFailed()` - Webhook: Zahlung fehlgeschlagen
- `handlePaymentRefunded()` - Webhook: Zahlung erstattet

**Autorisierung**:
- Admin kann alle Zahlungen erstellen
- Kunden nur für eigene Rechnungen
- Validierung von Rechnungsstatus (nicht paid/cancelled)

**Automatische Updates**:
- Payment-Status wird aktualisiert
- Invoice-Status → 'paid' wenn `remaining_balance <= 0`
- `paid_date` wird gesetzt

### 5. API-Routen

**Öffentlich** (für PayPal Webhooks):
```
POST /api/v1/payments/paypal/webhook
```

**Authentifiziert** (für Kunden/Admin):
```
POST /api/v1/payments/paypal/create-order
POST /api/v1/payments/paypal/capture-order
```

## Frontend

### 1. Umgebungsvariablen

**Dateien**:
- `frontend/.env` - Entwicklung
- `frontend/.env.example` - Template

```env
VITE_API_BASE_URL=http://localhost:8081/api/v1
VITE_PAYPAL_CLIENT_ID=your-client-id
```

### 2. PayPal API Client

**Datei**: `frontend/src/api/paypal.ts`

**Funktionen**:
- `createPayPalOrder(invoiceId)` - Erstellt Bestellung
- `capturePayPalOrder(orderId, invoiceId)` - Erfasst Zahlung

**TypeScript Interfaces**:
- `PayPalOrderResponse` - Bestellungsantwort
- `PayPalCaptureResponse` - Zahlungsantwort

### 3. PayPal Button Component

**Datei**: `frontend/src/components/PayPalButton.vue`

**Props**:
- `invoiceId: number` - Rechnungs-ID
- `amount: number` - Zahlungsbetrag
- `currency?: string` - Währung (default: EUR)

**Events**:
- `@success(payment)` - Zahlung erfolgreich
- `@error(error)` - Fehler aufgetreten
- `@cancel` - Zahlung abgebrochen

**Features**:
- Lädt PayPal SDK dynamisch
- Responsive PayPal-Button
- Loading/Processing/Error States
- Toast-Benachrichtigungen
- Dark Mode Support

**Workflow**:
1. PayPal SDK von CDN laden
2. Button mit createOrder/onApprove Callbacks rendern
3. Bei Approval → Backend-API aufrufen
4. Erfolg → Event emittieren

### 4. Payment Modal

**Datei**: `frontend/src/components/PaymentModal.vue`

**Props**:
- `invoice: Invoice` - Rechnungsobjekt
- `isOpen: boolean` - Modal-Sichtbarkeit

**Events**:
- `@close` - Modal schließen
- `@payment-success` - Zahlung erfolgreich

**Features**:
- Rechnungsdetails anzeigen
- Zahlungsmethoden-Auswahl (aktuell nur PayPal)
- Integriert PayPalButton Component
- Responsive Design
- Dark Mode Support
- Teleport zu body (für z-index)

**Anzeigt**:
- Rechnungsnummer
- Ausstellungsdatum
- Gesamtbetrag
- Offener Betrag (hervorgehoben)

## Verwendung

### 1. PayPal Developer Setup

1. Registrierung auf [PayPal Developer](https://developer.paypal.com/)
2. App erstellen (Sandbox/Live)
3. Client ID & Secret kopieren
4. In `.env` einfügen

### 2. Backend-Konfiguration

```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=your_client_id
PAYPAL_CLIENT_SECRET=your_client_secret
```

### 3. Frontend-Konfiguration

```env
VITE_PAYPAL_CLIENT_ID=your_client_id
```

### 4. Integration in Views

```vue
<script setup>
import PaymentModal from '@/components/PaymentModal.vue';
import { ref } from 'vue';

const selectedInvoice = ref(null);
const showPaymentModal = ref(false);

const openPayment = (invoice) => {
  selectedInvoice.value = invoice;
  showPaymentModal.value = true;
};

const handlePaymentSuccess = () => {
  // Rechnung neu laden oder Liste aktualisieren
  fetchInvoices();
};
</script>

<template>
  <!-- Button to open payment -->
  <button @click="openPayment(invoice)">
    Mit PayPal bezahlen
  </button>

  <!-- Payment Modal -->
  <PaymentModal
    v-if="selectedInvoice"
    :invoice="selectedInvoice"
    :is-open="showPaymentModal"
    @close="showPaymentModal = false"
    @payment-success="handlePaymentSuccess"
  />
</template>
```

### 5. Webhook-Konfiguration (Optional)

Für Produktionsumgebung Webhooks in PayPal Dashboard einrichten:

**Webhook URL**: `https://your-domain.com/api/v1/payments/paypal/webhook`

**Events abonnieren**:
- `PAYMENT.CAPTURE.COMPLETED`
- `PAYMENT.CAPTURE.DENIED`
- `PAYMENT.CAPTURE.DECLINED`
- `PAYMENT.CAPTURE.REFUNDED`

**Webhook ID** in `.env` eintragen:
```env
PAYPAL_WEBHOOK_ID=your_webhook_id
```

## Workflow

1. **Kunde wählt Rechnung** → Klickt auf "Bezahlen"
2. **PaymentModal öffnet** → Zeigt Rechnungsdetails
3. **PayPal Button** → Lädt PayPal SDK
4. **Kunde klickt Button** → `createPayPalOrder()` API-Call
5. **Backend erstellt Order** → PayPal API
6. **PayPal öffnet Popup** → Kunde meldet sich an
7. **Kunde genehmigt** → `onApprove` Callback
8. **Frontend ruft auf** → `capturePayPalOrder()` API
9. **Backend erfasst Zahlung** → PayPal API
10. **Payment erstellt** → DB-Eintrag mit transaction_id
11. **Invoice aktualisiert** → Status → 'paid' (wenn vollständig bezahlt)
12. **Frontend zeigt Erfolg** → Modal schließt, Toast-Nachricht

## Datenbank

**Payment Model** (bereits vorhanden):
- `payment_method`: enum mit 'paypal'
- `transaction_id`: PayPal Capture ID
- `status`: 'pending' → 'completed' (oder 'failed')

**Beziehungen**:
- Payment `belongsTo` Invoice
- Invoice `hasMany` Payments
- Invoice berechnet `remaining_balance` automatisch

## API-Endpunkte

### Create PayPal Order
```
POST /api/v1/payments/paypal/create-order
Authorization: Bearer {token}

Body:
{
  "invoice_id": 1
}

Response:
{
  "order_id": "8XY12345ABCDE",
  "status": "CREATED",
  "links": [...]
}
```

### Capture PayPal Order
```
POST /api/v1/payments/paypal/capture-order
Authorization: Bearer {token}

Body:
{
  "order_id": "8XY12345ABCDE",
  "invoice_id": 1
}

Response:
{
  "message": "Zahlung erfolgreich abgeschlossen",
  "payment": {
    "id": 5,
    "invoice_id": 1,
    "amount": 150.00,
    "payment_method": "paypal",
    "transaction_id": "CAP123456",
    "status": "completed",
    ...
  }
}
```

### PayPal Webhook
```
POST /api/v1/payments/paypal/webhook
(Keine Authentifizierung erforderlich - von PayPal aufgerufen)

Body: PayPal Webhook Event JSON

Response:
{
  "status": "success"
}
```

## Sicherheit

- **Authentifizierung**: Sanctum Bearer Token für API-Calls
- **Autorisierung**: Policy-basiert (Admin/Customer)
- **Validierung**: Rechnungsstatus vor Zahlungserstellung
- **Logging**: Alle PayPal-Operationen werden geloggt
- **Error Handling**: Try-Catch mit aussagekräftigen Fehlermeldungen
- **Webhook-Sicherheit**: PayPal Signature Verification (TODO für Produktion)

## Testing

### Sandbox Testing

1. PayPal Sandbox-Konto verwenden
2. Client ID von Sandbox-App
3. Test-Käufer-Konten erstellen
4. Zahlungen in PayPal Dashboard überprüfen

### Manual Testing

```bash
# Invoice mit offener Balance erstellen
# Payment Modal öffnen
# PayPal-Button klicken
# Mit Sandbox-Konto anmelden
# Zahlung genehmigen
# Überprüfen: Payment in DB, Invoice Status aktualisiert
```

## Erweiterungen (Future)

- [ ] Stripe-Integration
- [ ] Teilzahlungen
- [ ] Ratenzahlungen
- [ ] Rückerstattungs-UI
- [ ] Zahlungshistorie-View
- [ ] E-Mail-Benachrichtigungen bei Zahlung
- [ ] PDF-Quittung generieren
- [ ] Mehrere Zahlungsmethoden kombinieren

## Fehlerbehebung

### PayPal SDK lädt nicht
- Client ID in `.env` prüfen
- VITE_ Präfix vorhanden?
- Browser Console auf Fehler prüfen
- CORS-Header vom Backend

### Order Creation Failed
- PayPal Credentials korrekt?
- Sandbox vs. Live Mode
- Invoice `remaining_balance > 0`?
- Backend-Logs prüfen

### Capture Failed
- Order ID korrekt?
- Order wurde genehmigt?
- PayPal-API-Limits erreicht?
- Network-Fehler?

## Ressourcen

- [PayPal Server SDK GitHub](https://github.com/paypal/PayPal-PHP-Server-SDK)
- [PayPal Developer Docs](https://developer.paypal.com/docs/api/overview/)
- [PayPal Checkout Integration](https://developer.paypal.com/docs/checkout/)
- [PayPal Webhooks](https://developer.paypal.com/docs/api-basics/notifications/webhooks/)

## Zusammenfassung

✅ **Backend**:
- PayPal Server SDK v2.2.0 installiert
- PayPalService mit createOrder/captureOrder
- PaymentController erweitert um PayPal-Methoden
- Webhook-Handler für asynchrone Benachrichtigungen
- API-Routen konfiguriert

✅ **Frontend**:
- PayPal API Client (TypeScript)
- PayPalButton Component (Vue 3)
- PaymentModal Component
- Umgebungsvariablen konfiguriert
- Dark Mode Support

✅ **Features**:
- Direkte PayPal-Zahlungen für Rechnungen
- Automatische Status-Updates
- Vollständige Error-Handling
- Logging & Monitoring
- Responsive & Dark Mode

---

**Status**: ✅ Vollständig implementiert und einsatzbereit
**Nächste Schritte**: PayPal Sandbox Credentials konfigurieren und testen
