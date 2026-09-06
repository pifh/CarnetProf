<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Plan de classe</title>
    <style>
        @page {
            size: A4 {{ $orientation }};
            margin: 10mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        h1 {
            font-size: 15px;
            margin: 0 0 1mm;
        }

        .meta {
            color: #4b5563;
            margin-bottom: 5mm;
        }

        .teacher-desk-row {
            width: 100%;
            margin-bottom: 5mm;
        }

        .teacher-desk-row td {
            width: 33.333%;
        }

        .teacher-desk {
            display: inline-block;
            padding: 2mm 6mm;
            border: 0.4mm solid #9ca3af;
            border-radius: 2mm;
            background-color: #f3f4f6;
            font-size: 9px;
            font-weight: bold;
        }

        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 2mm;
            table-layout: fixed;
        }

        table.grid td.desk-cell {
            vertical-align: top;
            padding: 0;
        }

        table.desk {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0.8mm;
        }

        table.desk td.seat {
            border: 0.3mm solid #9ca3af;
            border-radius: 1mm;
            text-align: center;
            vertical-align: middle;
            height: 16mm;
            width: 20mm;
            font-size: 8px;
            padding: 1mm;
        }

        table.desk td.seat.empty {
            color: #9ca3af;
            border-style: dashed;
        }

        table.desk td.seat.blocked {
            background-color: #e5e7eb;
            color: #6b7280;
        }

        .seat-notes {
            display: block;
            margin-top: 0.5mm;
            font-size: 6px;
            font-weight: normal;
            color: #6b7280;
        }

        .empty-desk {
            border: 0.3mm dashed #9ca3af;
            color: #9ca3af;
            text-align: center;
            font-size: 8px;
            padding: 6mm 0;
        }
    </style>
</head>
<body>
    <h1>Plan de classe — {{ $schoolClass->name }}</h1>
    <div class="meta">
        {{ $application->effective_date?->format('d/m/Y') ?? 'Sans date' }}
    </div>

    @if ($teacherDeskPosition)
        <table class="teacher-desk-row">
            <tr>
                <td style="text-align: left;">
                    @if ($teacherDeskPosition === 'left')
                        <span class="teacher-desk">Bureau du professeur</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    @if ($teacherDeskPosition === 'center')
                        <span class="teacher-desk">Bureau du professeur</span>
                    @endif
                </td>
                <td style="text-align: right;">
                    @if ($teacherDeskPosition === 'right')
                        <span class="teacher-desk">Bureau du professeur</span>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <table class="grid">
        @for ($row = 0; $row < $gridSize['rows']; $row++)
            <tr>
                @for ($col = 0; $col < $gridSize['cols']; $col++)
                    @php($desk = $deskMap->get($row.'-'.$col))
                    <td class="desk-cell">
                        @if ($desk && $desk->is_blocked)
                            <div class="empty-desk">Emplacement vide</div>
                        @elseif ($desk)
                            <table class="desk">
                                <tr>
                                    @for ($seatIndex = 0; $seatIndex < $desk->capacity; $seatIndex++)
                                        @php($seat = $desk->seats->firstWhere('seat_index', $seatIndex))
                                        @php($occupant = $seat?->student)
                                        @php($isBlocked = $seat?->is_blocked ?? false)
                                        <td @class([
                                            'seat',
                                            'empty' => ! $occupant && ! $isBlocked,
                                            'blocked' => $isBlocked,
                                        ])>
                                            @if ($occupant)
                                                {{ $occupant->first_name }}<br>{{ $occupant->last_name }}
                                                @if ($occupant->seating_notes)
                                                    <span class="seat-notes">{{ $occupant->seating_notes }}</span>
                                                @endif
                                            @elseif ($isBlocked)
                                                Bloqué
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            </table>
                        @endif
                    </td>
                @endfor
            </tr>
        @endfor
    </table>
</body>
</html>
