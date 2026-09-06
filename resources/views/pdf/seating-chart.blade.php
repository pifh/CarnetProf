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
        }

        .teacher-desk-row.above {
            margin-bottom: 8mm;
        }

        .teacher-desk-row.below {
            margin-top: 8mm;
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
            {{-- Zeroes the inline "strut" line box a row of inline-table
                 desks would otherwise reserve on top of their own height. --}}
            font-size: 0;
            line-height: 0;
        }

        .room-row:last-child {
            margin-bottom: 0;
        }

        table.desk {
            display: inline-table;
            border-collapse: separate;
            border-spacing: 0;
            margin-right: 6mm;
            vertical-align: top;
        }

        {{-- Width/height set inline per seat (see below): fixed for every
             seat on the page, computed once to stretch the whole plan
             across the available print area. --}}
        table.desk td.seat {
            border: 0.3mm solid #9ca3af;
            border-radius: 1mm;
            text-align: center;
            vertical-align: middle;
            padding: 1mm;
        }

        {{-- A desk permanently blocked on the room layout is an empty
             space, not an empty table — same footprint (so the rest of the
             row doesn't shift), but no visible border, so nothing reads as
             a table there. --}}
        table.desk td.seat.empty-space {
            border-color: #ffffff;
        }

        .first-name {
            display: block;
            width: 100%;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            line-height: 1.4;
        }

        .last-name {
            display: block;
            width: 100%;
            margin-top: 1.5mm;
            font-size: 8px;
            font-weight: normal;
            color: #9ca3af;
        }

        .seat-notes {
            display: block;
            margin-top: 1mm;
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

    @if ($teacherDeskPosition && ! $printTeacherView)
        <table class="teacher-desk-row above">
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
                @php($seatIndexes = $printTeacherView ? range($desk->capacity - 1, 0) : range(0, $desk->capacity - 1))
                <table class="desk">
                    <tr>
                        @foreach ($seatIndexes as $seatIndex)
                            @php($seat = $desk->seats->firstWhere('seat_index', $seatIndex))
                            @php($occupant = $seat?->student)
                            <td @class(['seat', 'empty-space' => $desk->is_blocked]) style="width: {{ $seatWidthMm }}mm; height: {{ $seatHeightMm }}mm;">
                                @if ($occupant)
                                    <span class="first-name">{{ $occupant->first_name }}</span>
                                    <span class="last-name">{{ $occupant->last_name }}</span>
                                    @if ($occupant->seating_notes)
                                        <span class="seat-notes">{{ $occupant->seating_notes }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endforeach
        </div>
    @endforeach

    @if ($teacherDeskPosition && $printTeacherView)
        <table class="teacher-desk-row below">
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
</body>
</html>
