<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Trombinoscope</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1f2937;
        }

        .page {
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        h1 {
            font-size: 13px;
            margin: 0 0 1mm;
        }

        .meta {
            color: #4b5563;
            margin-bottom: 3mm;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.grid td {
            width: 16.6667%;
            text-align: center;
            vertical-align: top;
            padding: 1mm;
        }

        .photo-box {
            width: 26mm;
            height: 39mm;
            margin: 0 auto;
            background-color: #e5e7eb;
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;
            border: 0.3mm solid #d1d5db;
            border-radius: 2mm;
            font-size: 14px;
            font-weight: bold;
            color: #6b7280;
            text-align: center;
            line-height: 39mm;
        }

        .student-name {
            margin-top: 1mm;
            font-size: 7px;
            line-height: 1.1;
        }
    </style>
</head>
<body>
    @foreach ($students->chunk(30) as $pageStudents)
        <div class="page">
            <h1>Trombinoscope — {{ $schoolClass->name }}</h1>
            <div class="meta">
                {{ $students->count() }} élève{{ $students->count() > 1 ? 's' : '' }}
                @if ($schoolClass->school_year)
                    — {{ $schoolClass->school_year }}
                @endif
            </div>

            <table class="grid">
                @foreach ($pageStudents->chunk(6) as $row)
                    <tr>
                        @foreach ($row as $student)
                            <td>
                                @if ($student->currentPhoto)
                                    <div class="photo-box" style="background-image: url('{{ \Illuminate\Support\Facades\Storage::disk('public')->path($student->currentPhoto->path) }}')"></div>
                                @else
                                    @php($initials = mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1)))
                                    <div class="photo-box">{{ $initials }}</div>
                                @endif
                                <div class="student-name">{{ $student->full_name }}</div>
                            </td>
                        @endforeach
                        @for ($i = $row->count(); $i < 6; $i++)
                            <td></td>
                        @endfor
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
