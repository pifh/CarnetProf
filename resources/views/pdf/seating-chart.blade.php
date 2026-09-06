<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Plan de classe</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        {{-- `position: fixed` repeats this on every page, at the bottom of
             the printable area — dompdf's usual way of doing a page footer. --}}
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #9ca3af;
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
             and centered, so a row narrower than the page isn't left
             stranded against the margin. --}}
        .room-row {
            margin-bottom: 8mm;
            text-align: center;
        }

        table.desk {
            display: inline-table;
            border-collapse: separate;
            border-spacing: 0;
            margin-right: 6mm;
            vertical-align: top;
        }

        {{-- Fixed regardless of the desk's capacity, so a lone seat and a
             seat sharing a desk with three others are exactly the same
             size. --}}
        table.desk td.seat {
            border: 0.3mm solid #9ca3af;
            border-radius: 1mm;
            text-align: center;
            vertical-align: middle;
            width: 25mm;
            height: 18mm;
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
    <div class="footer">
        Plan de classe — {{ $schoolClass->name }} · {{ $application->effective_date?->format('d/m/Y') ?? 'Sans date' }}
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

    @for ($row = 0; $row < $gridSize['rows']; $row++)
        @php($rowDesks = $deskMap->values()->where('position_row', $row)->sortBy('position_col'))

        @if ($rowDesks->isNotEmpty())
            <div class="room-row">
                @foreach ($rowDesks as $desk)
                    {{-- A desk permanently blocked on the room layout has no
                         seats of its own — it renders here exactly like any
                         other desk of the same capacity, just with every
                         seat left blank. --}}
                    <table class="desk">
                        <tr>
                            @for ($seatIndex = 0; $seatIndex < $desk->capacity; $seatIndex++)
                                @php($seat = $desk->seats->firstWhere('seat_index', $seatIndex))
                                @php($occupant = $seat?->student)
                                <td class="seat">
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
        @endif
    @endfor
</body>
</html>
