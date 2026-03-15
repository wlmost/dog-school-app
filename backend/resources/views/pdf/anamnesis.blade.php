<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Anamnese - {{ $response->dog->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        h1 {
            color: #2563eb;
            font-size: 18pt;
            margin-bottom: 5px;
        }
        
        h2 {
            font-size: 14pt;
            color: #1e40af;
            margin: 20px 0 10px 0;
        }
        
        h3 {
            font-size: 11pt;
            color: #1e40af;
            margin: 15px 0 8px 0;
        }
        
        .company-info {
            text-align: right;
            font-size: 9pt;
            color: #666;
            margin-bottom: 20px;
        }
        
        .info-box {
            margin: 20px 0;
            padding: 15px;
            background-color: #f3f4f6;
            border-left: 3px solid #2563eb;
        }
        
        .info-row {
            margin: 5px 0;
        }
        
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        
        .question-container {
            margin: 20px 0;
            padding: 12px;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
        }
        
        .question-text {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 8px;
        }
        
        .answer-text {
            padding: 8px;
            background-color: #f9fafb;
            border-left: 3px solid #10b981;
            margin-top: 5px;
        }
        
        .required-badge {
            color: #dc2626;
            font-size: 8pt;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .metadata {
            margin: 20px 0;
            padding: 10px;
            background-color: #fffbeb;
            border-left: 3px solid #f59e0b;
            font-size: 9pt;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
        
        .status-badge {
            padding: 3px 10px;
            background-color: #d1fae5;
            color: #065f46;
            font-weight: bold;
            font-size: 8pt;
        }
        
        .incomplete-badge {
            padding: 3px 10px;
            background-color: #fee2e2;
            color: #991b1b;
            font-weight: bold;
            font-size: 8pt;
        }
        
        .no-answer {
            font-style: italic;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <!-- Company Header -->
    <div class="company-info">
        <h1>Hundeschule Max Mustermann</h1>
        <p>Musterstraße 123 • 12345 Musterstadt</p>
        <p>Tel: +49 123 456789 • E-Mail: info@hundeschule-mustermann.de</p>
    </div>

    <h2>ANAMNESE</h2>

    <!-- Template Information -->
    <div class="metadata">
        <p><strong>Vorlage:</strong> {{ $response->template->name }}</p>
        @if($response->template->description)
            <p><strong>Beschreibung:</strong> {{ $response->template->description }}</p>
        @endif
        <p><strong>Status:</strong> 
            @if($response->isCompleted())
                <span class="status-badge">ABGESCHLOSSEN</span>
            @else
                <span class="incomplete-badge">UNVOLLSTÄNDIG</span>
            @endif
        </p>
        @if($response->completed_at)
            <p><strong>Abgeschlossen am:</strong> {{ $response->completed_at->format('d.m.Y H:i') }} Uhr</p>
        @endif
        @if($response->completedBy)
            <p><strong>Abgeschlossen von:</strong> {{ $response->completedBy->full_name }}</p>
        @endif
    </div>

    <!-- Dog Information -->
    <div class="info-box">
        <h3>Hundeinformationen</h3>
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span>{{ $response->dog->name }}</span>
        </div>
        @if($response->dog->breed)
            <div class="info-row">
                <span class="info-label">Rasse:</span>
                <span>{{ $response->dog->breed }}</span>
            </div>
        @endif
        @if($response->dog->date_of_birth)
            <div class="info-row">
                <span class="info-label">Geburtsdatum:</span>
                <span>{{ $response->dog->date_of_birth->format('d.m.Y') }} ({{ $response->dog->age }})</span>
            </div>
        @endif
        @if($response->dog->gender)
            <div class="info-row">
                <span class="info-label">Geschlecht:</span>
                <span>{{ $response->dog->gender === 'male' ? 'Rüde' : 'Hündin' }}</span>
            </div>
        @endif
        @if($response->dog->is_neutered !== null)
            <div class="info-row">
                <span class="info-label">Kastriert:</span>
                <span>{{ $response->dog->is_neutered ? 'Ja' : 'Nein' }}</span>
            </div>
        @endif
        @if($response->dog->chip_number)
            <div class="info-row">
                <span class="info-label">Chip-Nummer:</span>
                <span>{{ $response->dog->chip_number }}</span>
            </div>
        @endif
    </div>

    <!-- Customer Information -->
    <div class="info-box">
        <h3>Besitzerinformationen</h3>
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span>{{ $response->dog->customer->user->full_name }}</span>
        </div>
        @if($response->dog->customer->user->email)
            <div class="info-row">
                <span class="info-label">E-Mail:</span>
                <span>{{ $response->dog->customer->user->email }}</span>
            </div>
        @endif
        @if($response->dog->customer->phone)
            <div class="info-row">
                <span class="info-label">Telefon:</span>
                <span>{{ $response->dog->customer->phone }}</span>
            </div>
        @endif
        @if($response->dog->customer->full_address)
            <div class="info-row">
                <span class="info-label">Adresse:</span>
                <span>{{ $response->dog->customer->full_address }}</span>
            </div>
        @endif
    </div>

    <!-- Questions and Answers -->
    <h2>Fragen und Antworten</h2>

    @php
        // Create a map of answers by question_id for easy lookup
        $answersMap = $response->answers->keyBy('question_id');
    @endphp

    @foreach($response->template->questions as $question)
        <div class="question-container">
            <div class="question-text">
                {{ $question->order }}. {{ $question->question_text }}
                @if($question->is_required)
                    <span class="required-badge">PFLICHTFRAGE</span>
                @endif
            </div>
            
            @if(isset($answersMap[$question->id]) && $answersMap[$question->id]->hasValue())
                <div class="answer-text">
                    @php
                        $answer = $answersMap[$question->id];
                        $answerValue = $answer->answer_value;
                    @endphp
                    
                    @if($question->question_type === 'checkbox' && is_array(json_decode($answerValue, true)))
                        {{-- Multiple choice answers --}}
                        @php
                            $selectedOptions = json_decode($answerValue, true);
                        @endphp
                        <ul style="margin: 5px 0; padding-left: 20px;">
                            @foreach($selectedOptions as $option)
                                <li>{{ $option }}</li>
                            @endforeach
                        </ul>
                    @elseif($question->question_type === 'date')
                        {{-- Date formatting --}}
                        {{ \Carbon\Carbon::parse($answerValue)->format('d.m.Y') }}
                    @else
                        {{-- Text, textarea, radio, select --}}
                        {{ $answerValue }}
                    @endif
                </div>
            @else
                <div class="answer-text no-answer">
                    Keine Antwort angegeben
                </div>
            @endif
        </div>
    @endforeach

    <!-- Footer -->
    <div class="footer">
        <p>Hundeschule Max Mustermann • Musterstraße 123 • 12345 Musterstadt</p>
        <p>USt-IdNr: DE123456789</p>
        <p style="margin-top: 10px;">Erstellt am: {{ now()->format('d.m.Y H:i') }} Uhr</p>
    </div>
</body>
</html>
