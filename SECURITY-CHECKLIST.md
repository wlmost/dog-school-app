# Security Checklist für Production Deployment

## Pre-Deployment Security Checklist

### Environment Configuration

- [ ] `APP_ENV=production` gesetzt
- [ ] `APP_DEBUG=false` gesetzt
- [ ] `APP_KEY` generiert und gesichert
- [ ] Alle `APP_URL` Werte auf Production Domain gesetzt
- [ ] `SANCTUM_TOKEN_EXPIRATION=480` konfiguriert
- [ ] `CORS_ALLOWED_ORIGINS` nur Production Domain(s)

### Database Security

- [ ] Starkes DB Passwort (min. 16 Zeichen)
- [ ] DB nur von Application Server erreichbar
- [ ] DB Backups verschlüsselt
- [ ] DB Connection über TLS/SSL
- [ ] Keine DB Credentials in Code

### SSL/TLS Configuration

- [ ] SSL Zertifikat installiert (Let's Encrypt empfohlen)
- [ ] HTTPS erzwungen (Nginx/Apache Redirect)
- [ ] HSTS Header aktiv (via SecurityHeaders Middleware)
- [ ] TLS 1.2+ only
- [ ] Strong Cipher Suites konfiguriert

### Server Configuration

- [ ] Server Software aktuell (Nginx/Apache)
- [ ] PHP Version aktuell (8.2+)
- [ ] Unnötige Services deaktiviert
- [ ] Firewall konfiguriert (nur 80, 443 öffentlich)
- [ ] SSH Keys statt Passwörter
- [ ] Root Login deaktiviert

### Application Security

- [ ] Alle Composer Dependencies aktualisiert
- [ ] Alle NPM Dependencies aktualisiert
- [ ] Security Headers Middleware aktiv
- [ ] Rate Limiting konfiguriert
- [ ] File Upload Limits gesetzt
- [ ] Max Request Size begrenzt

### PayPal Configuration

- [ ] `PAYPAL_MODE=live` für Production
- [ ] Live Client ID & Secret konfiguriert
- [ ] Webhook URL in PayPal Dashboard registriert
- [ ] Webhook Signature Validation aktiv (`PAYPAL_WEBHOOK_ID` gesetzt)
- [ ] Return/Cancel URLs korrekt

### Mail Configuration

- [ ] Production Mail Server konfiguriert
- [ ] `MAIL_FROM_ADDRESS` auf echte Domain
- [ ] SPF/DKIM Records gesetzt
- [ ] DMARC Policy konfiguriert
- [ ] E-Mail Queue aktiviert

### Logging & Monitoring

- [ ] Error Logging aktiviert
- [ ] Log Rotation konfiguriert
- [ ] Security Logs überwacht
- [ ] Failed Login Attempts monitored
- [ ] Uptime Monitoring eingerichtet
- [ ] Performance Monitoring (optional: New Relic, Sentry)

### Backup Strategy

- [ ] Tägliche DB Backups
- [ ] Wöchentliche Code Backups
- [ ] Backup Restore Prozess getestet
- [ ] Offsite Backup Storage
- [ ] 30 Tage Retention Policy

### Access Control

- [ ] Admin Accounts mit starken Passwörtern
- [ ] 2FA für kritische Accounts aktiviert (optional)
- [ ] Principle of Least Privilege beachtet
- [ ] Service Accounts mit minimalen Rechten
- [ ] SSH Keys rotieren

### Compliance & Documentation

- [ ] Datenschutzerklärung vorhanden
- [ ] AGB vorhanden
- [ ] Impressum vollständig
- [ ] DSGVO Compliance geprüft
- [ ] Cookie Consent implementiert (falls notwendig)
- [ ] Deployment Dokumentation aktuell

## Post-Deployment Security Tests

### Automated Scans

```bash
# OWASP ZAP Baseline Scan
docker run -t owasp/zap2docker-stable zap-baseline.py \
  -t https://yourdomain.com

# Nikto Web Server Scan
nikto -h https://yourdomain.com

# SSL Labs Test
https://www.ssllabs.com/ssltest/analyze.html?d=yourdomain.com
```

### Manual Tests

- [ ] Login Rate Limiting funktioniert
- [ ] Security Headers via curl prüfen
- [ ] CORS mit unauthorized origin testen
- [ ] File Upload mit malicious files testen
- [ ] PayPal Payment Flow testen
- [ ] E-Mail Versand testen
- [ ] Password Reset Flow testen

### Penetration Testing

- [ ] SQL Injection Tests
- [ ] XSS Tests
- [ ] CSRF Tests
- [ ] Authentication Bypass Tests
- [ ] Authorization Tests (IDOR)
- [ ] File Upload Tests
- [ ] API Fuzzing

## Security Monitoring

### Daily Checks

- [ ] Failed Login Attempts Review
- [ ] Error Logs Review
- [ ] PayPal Transaction Logs

### Weekly Checks

- [ ] Security Patches verfügbar?
- [ ] Dependency Updates verfügbar?
- [ ] Disk Space ausreichend?
- [ ] Backup Status OK?

### Monthly Checks

- [ ] Security Scan durchführen
- [ ] Access Logs analysieren
- [ ] SSL Certificate Expiry prüfen
- [ ] Performance Review

### Quarterly Reviews

- [ ] Penetration Test durchführen
- [ ] Security Policies reviewen
- [ ] Incident Response Plan testen
- [ ] Disaster Recovery testen

## Incident Response Plan

### Bei Security Incident:

1. **Identifizieren:**
   - Art des Incidents feststellen
   - Betroffene Systeme identifizieren
   - Logs sichern

2. **Eindämmen:**
   - Betroffene Services isolieren
   - Zugriffe blockieren
   - Weitere Schäden verhindern

3. **Analysieren:**
   - Root Cause ermitteln
   - Auswirkungen bewerten
   - Beweise sichern

4. **Beheben:**
   - Schwachstelle patchen
   - Passwörter rotieren
   - Systems härten

5. **Dokumentieren:**
   - Incident Report erstellen
   - Lessons Learned dokumentieren
   - Prozesse verbessern

6. **Kommunizieren:**
   - Stakeholder informieren
   - Kunden benachrichtigen (bei Datenverlust)
   - Behörden informieren (DSGVO Meldepflicht)

## Contacts

**Security Issues:**
- Security Team: security@yourdomain.com
- Emergency: [Phone Number]

**Service Providers:**
- Hosting: [Provider Contact]
- SSL: [Provider Contact]
- PayPal: support@paypal.com

## Resources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [PayPal Security Center](https://www.paypal.com/security)
- [BSI Grundschutz](https://www.bsi.bund.de/grundschutz)

---

**Last Updated:** 24. Januar 2026  
**Review Date:** [3 Monate nach Deployment]  
**Version:** 1.0
