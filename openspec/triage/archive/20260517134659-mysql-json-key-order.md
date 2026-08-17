# Triage: mysql-json-key-order

**Pfad:** trivial
**Geschätzter Umfang:** 1 Datei, PHP (Pest-Test)
**Risiko:** niedrig — nur eine Test-Assertion wird angepasst, kein Produktivcode berührt
**Klarheit:** klar — Ursache und Fix sind vollständig analysiert

## Anforderung (Zusammenfassung)

Der Unit-Test `CourseRecurrenceRuleTest > it speichert recurrence_rule als array und liest es korrekt zurück` schlägt in der CI unter MySQL fehl. MySQL sortiert Schlüssel in JSON-Objekten alphabetisch beim Speichern/Lesen, während PostgreSQL die Einfügereihenfolge beibehält. Die Assertion `toBe()` (PHP `===`) prüft bei assoziativen Arrays auch die Schlüsselreihenfolge, was unter MySQL zu einem Fehlschlag führt. Der Fix ist der Austausch von `toBe()` durch `toEqual()` (PHP `==`), das die Schlüsselreihenfolge ignoriert.

## Betroffene Datei

- `tests/Unit/Models/CourseRecurrenceRuleTest.php`, Zeile 26

## Fix

```php
// Vorher
expect($fresh->recurrence_rule)->toBe($rule);

// Nachher
expect($fresh->recurrence_rule)->toEqual($rule);
```

## Empfohlene nächste Aktion

Direkter Fix durch `dev-php`: Zeile 26 in `tests/Unit/Models/CourseRecurrenceRuleTest.php` ändern — kein Architect, kein Skeptiker, kein openspec-Change nötig.
