<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AnamnesisTemplate;
use Illuminate\Database\Seeder;

class AnamnesisTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Welpen-Anamnese
        $puppyTemplate = AnamnesisTemplate::create([
            'name' => 'Welpen-Anamnese',
            'description' => 'Standard-Anamnese für Welpen (8-16 Wochen)',
            'is_default' => true,
            'trainer_id' => null,
        ]);

        $puppyTemplate->questions()->createMany([
            [
                'question_text' => 'Geburtsdatum des Welpen (TT.MM.JJJJ)',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 1,
            ],
            [
                'question_text' => 'Züchter / Herkunft',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 2,
            ],
            [
                'question_text' => 'Anzahl der Geschwister im Wurf',
                'question_type' => 'text',
                'is_required' => false,
                'order' => 3,
            ],
            [
                'question_text' => 'Datum der Übernahme (TT.MM.JJJJ)',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 4,
            ],
            [
                'question_text' => 'Bisherige Sozialisierung (Umwelt, Artgenossen, Menschen)',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 5,
            ],
            [
                'question_text' => 'Impfstatus (vollständig geimpft?)',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 6,
                'options' => json_encode([
                    'Ja, vollständig',
                    'Teilweise',
                    'Nein',
                    'Unbekannt'
                ]),
            ],
            [
                'question_text' => 'Gesundheitliche Auffälligkeiten oder Vorerkrankungen',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 7,
            ],
            [
                'question_text' => 'Aktuelle Fütterung (Futtermittel, Häufigkeit)',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 8,
            ],
            [
                'question_text' => 'Stubenreinheit bereits vorhanden?',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 9,
                'options' => json_encode([
                    'Ja, vollständig',
                    'Überwiegend',
                    'In Arbeit',
                    'Noch nicht begonnen'
                ]),
            ],
            [
                'question_text' => 'Bisherige Trainingserfahrung',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 10,
            ],
            [
                'question_text' => 'Trainingsziele für den Welpen',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 11,
            ],
            [
                'question_text' => 'Besondere Verhaltensauffälligkeiten oder Ängste',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 12,
            ],
        ]);

        // Verhaltensauffälligkeiten
        $behaviorTemplate = AnamnesisTemplate::create([
            'name' => 'Verhaltensanalyse',
            'description' => 'Anamnese für Hunde mit Verhaltensauffälligkeiten',
            'is_default' => true,
            'trainer_id' => null,
        ]);

        $behaviorTemplate->questions()->createMany([
            [
                'question_text' => 'Beschreibung des problematischen Verhaltens',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 1,
            ],
            [
                'question_text' => 'Seit wann tritt das Verhalten auf?',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 2,
            ],
            [
                'question_text' => 'In welchen Situationen tritt das Verhalten auf?',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 3,
            ],
            [
                'question_text' => 'Häufigkeit des Verhaltens',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 4,
                'options' => json_encode([
                    'Täglich mehrmals',
                    'Täglich',
                    'Mehrmals wöchentlich',
                    'Wöchentlich',
                    'Seltener'
                ]),
            ],
            [
                'question_text' => 'Intensität des Verhaltens (1 = leicht, 10 = sehr stark)',
                'question_type' => 'select',
                'is_required' => true,
                'order' => 5,
                'options' => json_encode([
                    '1 - sehr leicht',
                    '2',
                    '3',
                    '4',
                    '5 - mittel',
                    '6',
                    '7',
                    '8',
                    '9',
                    '10 - sehr stark'
                ]),
            ],
            [
                'question_text' => 'Gab es auslösende Ereignisse (Umzug, neues Tier, etc.)?',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 6,
            ],
            [
                'question_text' => 'Wurde das Verhalten tierärztlich abgeklärt?',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 7,
                'options' => json_encode([
                    'Ja, keine medizinischen Ursachen',
                    'Ja, medizinische Ursachen vorhanden',
                    'Nein, noch nicht',
                    'Nicht notwendig'
                ]),
            ],
            [
                'question_text' => 'Bisherige Maßnahmen/Training',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 8,
            ],
            [
                'question_text' => 'Wie reagiert der Hund auf Belohnungen?',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 9,
            ],
            [
                'question_text' => 'Sozialverhalten mit Artgenossen',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 10,
                'options' => json_encode([
                    'Freundlich und aufgeschlossen',
                    'Vorsichtig, aber positiv',
                    'Ängstlich',
                    'Aggressiv',
                    'Ignorierend'
                ]),
            ],
            [
                'question_text' => 'Sozialverhalten mit Menschen',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 11,
                'options' => json_encode([
                    'Freundlich und aufgeschlossen',
                    'Vorsichtig, aber positiv',
                    'Ängstlich bei Fremden',
                    'Aggressiv bei Fremden',
                    'Nur im Familienkreis sicher'
                ]),
            ],
            [
                'question_text' => 'Welche Ziele sollen mit dem Training erreicht werden?',
                'question_type' => 'textarea',
                'is_required' => true,
                'order' => 12,
            ],
        ]);

        // Gesundheitsanamnese
        $healthTemplate = AnamnesisTemplate::create([
            'name' => 'Gesundheitsanamnese',
            'description' => 'Gesundheitliche Vorgeschichte und aktuelle Situation',
            'is_default' => true,
            'trainer_id' => null,
        ]);

        $healthTemplate->questions()->createMany([
            [
                'question_text' => 'Kastrationsstatus',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 1,
                'options' => json_encode([
                    'Kastriert',
                    'Nicht kastriert',
                    'Sterilisiert'
                ]),
            ],
            [
                'question_text' => 'Falls kastriert: Datum der Kastration (TT.MM.JJJJ)',
                'question_type' => 'text',
                'is_required' => false,
                'order' => 2,
            ],
            [
                'question_text' => 'Aktuelle Medikamente',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 3,
            ],
            [
                'question_text' => 'Bekannte Allergien',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 4,
            ],
            [
                'question_text' => 'Chronische Erkrankungen',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 5,
            ],
            [
                'question_text' => 'Bewegungseinschränkungen (Arthrose, HD, etc.)',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 6,
            ],
            [
                'question_text' => 'Seh- oder Hörprobleme',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 7,
                'options' => json_encode([
                    'Keine bekannt',
                    'Leichte Einschränkung',
                    'Starke Einschränkung',
                    'Taub',
                    'Blind'
                ]),
            ],
            [
                'question_text' => 'Zahnstatus / Zahnhygiene',
                'question_type' => 'text',
                'is_required' => false,
                'order' => 8,
            ],
            [
                'question_text' => 'Gewicht (kg)',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 9,
            ],
            [
                'question_text' => 'Kondition / Fitness-Level',
                'question_type' => 'radio',
                'is_required' => true,
                'order' => 10,
                'options' => json_encode([
                    'Sehr gut trainiert',
                    'Gut',
                    'Durchschnittlich',
                    'Unterdurchschnittlich',
                    'Stark eingeschränkt'
                ]),
            ],
            [
                'question_text' => 'Tägliche Bewegung (Minuten/Tag)',
                'question_type' => 'text',
                'is_required' => true,
                'order' => 11,
            ],
            [
                'question_text' => 'Besondere Belastbarkeitseinschränkungen für das Training',
                'question_type' => 'textarea',
                'is_required' => false,
                'order' => 12,
            ],
        ]);

        $this->command->info('Standard-Anamnese-Templates erfolgreich erstellt!');
    }
}
