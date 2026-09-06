<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Plan de classe</title>
    <style>
        @page {
            size: A4 landscape;
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
            margin-bottom: 6mm;
        }

        .teacher-desk-row {
            width: 100%;
            margin-bottom: 8mm;
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

        {{-- Each row is its own block, spaced apart to read as an aisle
             between rows of desks. Desks within a row are laid out with
             `inline-table` (not a shared grid), so a desk's own size never
             depends on how many desks or seats are elsewhere on the page —
             and centered, so a row narrower than the widest one isn't
             stranded against the left margin. --}}
        .room-row {
            margin-bottom: 8mm;
            text-align: center;
        }

        .room-row:last-child {
            margin-bottom: 0;
        }

        table.desk {
            display: inline-table;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 3mm;
            vertical-align: top;
        }

        {{-- Width/height are set inline per seat (see below): fixed for
             every seat on the page, computed once to stretch the whole
             plan across the available print area. --}}
        table.desk td.seat {
            border: 0.3mm solid #9ca3af;
            border-radius: 1mm;
            text-align: center;
            vertical-align: middle;
            padding: 1mm;
        }

        .first-name {
            display: block;
            width: 100%;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            line-height: 1.1;
        }

        .last-name {
            display: block;
            width: 100%;
            margin-top: 0.5mm;
            font-size: 8px;
            font-weight: normal;
            color: #9ca3af;
        }

        .seat-notes {
            display: block;
            margin-top: 0.5mm;
            font-size: 6px;
            font-weight: normal;
            color: #6b7280;
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

    @foreach ($rowsOfDesks as $rowDesks)
        <div class="room-row">
            @foreach ($rowDesks as $desk)
                <table class="desk">
                    <tr>
                        @for ($seatIndex = $desk->capacity - 1; $seatIndex >= 0; $seatIndex--)
                            @php($seat = $desk->seats->firstWhere('seat_index', $seatIndex))
                            @php($occupant = $seat?->student)
                            <td class="seat" style="width: {{ $seatWidthMm }}mm; height: {{ $seatHeightMm }}mm;">
                                @if ($occupant)
                                    <span class="first-name">{{ $occupant->first_name }}</span>
                                    <span class="last-name">{{ $occupant->last_name }}</span>
                                    @if ($occupant->seating_notes)
                                        <span class="seat-notes">{{ $occupant->seating_notes }}</span>
                                    @endif
                                @endif
                            </td>
                        @endfor
                    </tr>
                </table>
            @endforeach
        </div>
    @endforeach
</body>
</html>
