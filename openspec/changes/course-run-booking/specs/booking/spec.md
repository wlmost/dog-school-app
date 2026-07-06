# Spec Delta: Booking Capability

**Change:** course-run-booking  
**Capability:** booking

---

## Änderungen gegenüber bisherigem Stand

### Buchungseinheit (GEÄNDERT)

**Alt:** Eine Buchung referenziert eine einzelne `TrainingSession`
(`training_session_id`).

**Neu:** Eine Buchung referenziert einen `CourseRun` (`course_run_id`). Eine
Buchung gilt für alle Sessions des Durchlaufs.

### Buchungserstellung (GEÄNDERT)

**Request-Payload:**
```json
{
  "courseRunId": integer (required),
  "customerId":  integer (required),
  "dogId":       integer (required),
  "notes":       string  (optional)
}
```

**Validierungsregeln:**
1. `courseRunId` muss existieren
2. `courseRun.canAcceptNewBookings()` muss true sein
3. Kein Duplikat: (courseRunId, dogId) mit status pending|confirmed
4. `dogId` gehört zu `customerId`
5. `courseRun.start_date` darf nicht in der Vergangenheit liegen,
   außer `courseRun.isOpenGroup() === true`

### Stornierung (GEÄNDERT)

**Frist-Berechnung:** `courseRun.start_date` minus `course.cancellation_deadline_hours`  
(bisher: `trainingSession.session_date` minus `course.cancellation_deadline_hours`)

**Semantik:** Die Buchung für den gesamten Durchlauf wird storniert —
keine Einzel-Session-Stornierung.

### Kapazitätsprüfung (GEÄNDERT)

**Alt:** Kapazität auf `TrainingSession`-Ebene (`session.max_participants`).

**Neu:** Kapazität auf `CourseRun`-Ebene:
```
effectiveMaxParticipants = courseRun.max_participants ?? course.max_participants
belegtePlätze = bookings WHERE course_run_id = X AND status IN ('pending','confirmed')
```

### Drop-In / Open Group (PRÄZISIERT)

Buchungen für Kurse mit `course_type = 'open_group'` sind auch dann möglich,
wenn `courseRun.start_date` in der Vergangenheit liegt.

---

## Unverändertes Verhalten

- Buchungs-Workflow: `pending` → `confirmed` → `cancelled`
- Kunden können Stornierungsanfragen stellen; Trainer/Admin genehmigen
- `BookingCreated`-Event wird nach jeder Buchung dispatched
- Admins und Trainer können sofort stornieren (ohne Fristprüfung)
