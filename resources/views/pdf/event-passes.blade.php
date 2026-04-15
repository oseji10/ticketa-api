<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Event Passes</title>

    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            color: #222;
        }

        .passes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 8px;
            table-layout: fixed;
        }

        .passes-table td {
            width: 50%;
            vertical-align: top;
        }

        .ticket {
            border-radius: 10px;
            padding: 12px;
            height: 460px;
            box-sizing: border-box;
            page-break-inside: avoid;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.08);
        }

        .event-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .event-meta {
            font-size: 10px;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        /* 🔥 QR (no container, but safe padding) */
        .qr-box {
            margin: 0 auto 10px auto;
            text-align: center;
        }

        .qr-box img {
            width: 220px;
            height: 220px;
            display: block;
            margin: 0 auto;
            padding: 8px;              /* IMPORTANT: quiet zone */
            background: #ffffff;       /* keeps scanning reliable */
            border-radius: 6px;
        }

        /* 🔥 FRONT DESIGN - BIGGER WHITE BOX */
        .front-box {
            width: 280px;
            height: 280px;
            margin: 0 auto 10px auto;
            box-sizing: border-box;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
        }

        .front-box-inner {
            width: 100%;
            height: 100%;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 16px 14px;
        }

        /* 🔥 LOGO (no oval, bigger) */
        .front-logo-wrap {
            margin: 0 auto 12px auto;
            text-align: center;
        }

        .front-logo-wrap img {
            max-width: 140px;
            max-height: 140px;
            display: inline-block;
        }

        .front-main-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 12px;
        }

        .front-subtitle {
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 14px;
        }

        /* 🔥 WHITE NAME BOX - BIGGER FOR MANUAL WRITING */
        .name-write-box {
            background: #ffffff;
            color: #000000;
            padding: 28px 20px;
            border-radius: 8px;
            font-size: 11px;
            margin-bottom: 16px;
            border: 1px solid #e5e5e5;
            min-height: 60px;
            text-align: center;
            font-style: italic;
            color: #888;
        }

        .front-footer {
            font-size: 9px;
            line-height: 1.4;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .serial {
            margin-top: 8px;
            font-size: 42px;
            font-weight: bold;
        }

        .pass-label {
            font-size: 10px;
            margin-top: 4px;
            text-transform: uppercase;
        }

        .note {
            margin-top: 10px;
            font-size: 9px;
            line-height: 1.5;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@php
    $passRows = $passes->chunk(2);
    $logoPath = public_path('storage/images/wima-base.png');
@endphp

@foreach($passRows as $row)
    <table class="passes-table">
        @foreach($row as $pass)

                @php
                // Extract numeric part from serial number
                $rawSerial = (string) ($pass->serialNumber ?? '');
                preg_match('/(\d+)$/', $rawSerial, $matches);
                $serial = isset($matches[1]) ? (int) $matches[1] : 0;

                // Color allocation: 36 per color, 8 colors total (288 participants)
                // After 288, use last color (brown)
                if ($serial >= 1 && $serial <= 36) {
                    // Color 1: Red
                    $bg = '#EF4444'; $text = '#FFFFFF';
                } elseif ($serial >= 37 && $serial <= 72) {
                    // Color 2: Purple
                    $bg = '#8B5CF6'; $text = '#FFFFFF';
                } elseif ($serial >= 73 && $serial <= 108) {
                    // Color 3: Green
                    $bg = '#22C55E'; $text = '#FFFFFF';
                } elseif ($serial >= 109 && $serial <= 144) {
                    // Color 4: Blue
                    $bg = '#3B82F6'; $text = '#FFFFFF';
                } elseif ($serial >= 145 && $serial <= 180) {
                    // Color 5: Yellow
                    $bg = '#FACC15'; $text = '#000000';
                } elseif ($serial >= 181 && $serial <= 216) {
                    // Color 6: Pink
                    $bg = '#EC4899'; $text = '#FFFFFF';
                } elseif ($serial >= 217 && $serial <= 252) {
                    // Color 7: Orange
                    $bg = '#F59E0B'; $text = '#000000';
                } elseif ($serial >= 253 && $serial <= 288) {
                    // Color 8: Red
                    $bg = '#964B00'; $text = '#FFFFFF';
                } else {
                    // After 288, use last color (Red)
                    $bg = '#964B00'; $text = '#FFFFFF';
                }
            @endphp
            <tr>

                <!-- LEFT: QR SIDE -->
                <td>
                    <div class="ticket" style="background-color: {{ $bg }}; color: {{ $text }};">
                        <div class="event-title">
                        {{ $event->title }}
                        </div>

                        <div class="event-meta">
                            {{ $event->startDate }} - {{ $event->endDate }}<br>
                            <!-- {{ $event->location ?? '' }} -->
                        </div>

                        <div class="qr-box">
                            <img src="{{ public_path('storage/' . $pass->qrPath) }}">
                        </div>

                        <div class="serial">
                            {{ $pass->serialNumber }}
                        </div>

                        <div class="note">
                            Present this QR code at check-in
                        </div>
                    </div>
                </td>

                <!-- RIGHT: FRONT SIDE -->
                <td>
                    <div class="ticket" style="background-color: {{ $bg }}; color: {{ $text }};">

                        <div class="event-title">
                            Year 2 Cohort 2
                        </div>

                        <div class="event-meta">
                            {{ $event->startDate }} - {{ $event->endDate }}<br>
                            <!-- {{ $event->location ?? '' }} -->
                        </div>

                        <div class="front-box">
                            <div class="front-box-inner">

                                <div class="front-logo-wrap">
                                    <img src="{{ $logoPath }}">
                                </div>

                                <div class="front-main-title">
                                    Programme Access Tag
                                </div>

                                <!-- 🔥 WHITE BOX FOR MANUAL NAME WRITING (No Serial Number) -->
                                <div class="name-write-box">
                                    (Write participant name here)
                                </div>

                                <div class="front-footer">
                                    Valid for accredited participant use only
                                </div>

                            </div>
                        </div>

                    </div>
                </td>

            </tr>

        @endforeach
    </table>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>