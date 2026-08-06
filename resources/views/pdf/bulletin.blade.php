<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 12px;
            margin: 16px 0 6px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 2px;
        }

        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .meta {
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            text-align: left;
        }

        th {
            background-color: #f3f4f6;
        }

        .text-right {
            text-align: right;
        }

        .averages {
            margin-top: 10px;
        }

        .averages td {
            border: none;
            padding: 2px 6px;
        }

        .appreciation {
            margin-top: 4px;
            white-space: pre-wrap;
        }

        .empty {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    @foreach ($bulletins as $bulletin)
        <div class="page">
            <div class="header">
                <h1>Bulletin — {{ $bulletin['term']?->label ?? 'Année complète' }}</h1>
                <div class="meta">
                    {{ $bulletin['student']->first_name }} {{ $bulletin['student']->last_name }}
                    — {{ $bulletin['schoolClass']->name }}
                    @if ($bulletin['subject'])
                        — {{ $bulletin['subject']->name }}
                    @endif
                </div>
            </div>

            <h2>Évaluations</h2>

            @if ($bulletin['evaluations']->isEmpty())
                <p class="empty">Aucune évaluation ce trimestre.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Évaluation</th>
                            <th class="text-right">Note</th>
                            <th class="text-right">Coeff.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bulletin['evaluations'] as $evaluation)
                            <tr>
                                <td>{{ $evaluation['exam_date']?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $evaluation['title'] }}</td>
                                <td class="text-right">
                                    @if ($evaluation['status'] === 'graded' && $evaluation['score'] !== null)
                                        {{ rtrim(rtrim(number_format($evaluation['score'], 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($evaluation['max_score'], 2), '0'), '.') }}
                                    @else
                                        {{ [
                                            'absent' => 'Absent',
                                            'exempted' => 'Dispensé',
                                            'to_retake' => 'À rattraper',
                                            'not_graded' => 'Non noté',
                                        ][$evaluation['status']] ?? 'Non noté' }}
                                    @endif
                                </td>
                                <td class="text-right">{{ rtrim(rtrim(number_format($evaluation['coefficient'], 2), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <table class="averages">
                <tr>
                    <td><strong>Moyenne de l'élève</strong></td>
                    <td>{{ $bulletin['average'] !== null ? number_format($bulletin['average'], 2).'/20' : 'Non calculée' }}</td>
                </tr>
                <tr>
                    <td><strong>Moyenne de la classe</strong></td>
                    <td>{{ $bulletin['classAverage'] !== null ? number_format($bulletin['classAverage'], 2).'/20' : 'Non calculée' }}</td>
                </tr>
            </table>

            <h2>Appréciation</h2>
            @if ($bulletin['appreciation'])
                <p class="appreciation">{{ $bulletin['appreciation'] }}</p>
            @else
                <p class="empty">Aucune appréciation rédigée.</p>
            @endif
        </div>
    @endforeach
</body>
</html>
