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

        /* 🔥 FRONT DESIGN */
        .front-box {
            width: 240px;
            height: 240px;
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
            padding: 12px 10px;
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
            margin-bottom: 8px;
        }

        .front-subtitle {
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 14px;
        }

        .serial-pill {
            display: inline-block;
            background: #ffffff;
            color: #111111;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
            border: 1px solid #e5e5e5;
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

    $url = 'https://wimanigeria.com/assets/images/wima-base.png';
$contents = file_get_contents($url);

$fileName = 'logo.jpg';
$path = storage_path('app/public/' . $fileName);

file_put_contents($path, $contents);

$logoPath = asset('storage/' . $fileName);
@endphp

@foreach($passRows as $row)
    <table class="passes-table">
        @foreach($row as $pass)

            @php
                $rawSerial = (string) ($pass->serialNumber ?? '');
                preg_match('/(\d+)$/', $rawSerial, $matches);
                $serial = isset($matches[1]) ? (int) $matches[1] : 0;

                if ($serial >= 1 && $serial <= 39) {
                    $bg = '#F59E0B'; $text = '#000000';
                } elseif ($serial >= 40 && $serial <= 78) {
                    $bg = '#8B5CF6'; $text = '#FFFFFF';
                } elseif ($serial >= 79 && $serial <= 117) {
                    $bg = '#3B82F6'; $text = '#FFFFFF';
                } elseif ($serial >= 118 && $serial <= 156) {
                    $bg = '#FFFFFF'; $text = '#000000';
                } elseif ($serial >= 157 && $serial <= 194) {
                    $bg = '#FACC15'; $text = '#000000';
                } elseif ($serial >= 195 && $serial <= 232) {
                    $bg = '#22C55E'; $text = '#FFFFFF';
                } elseif ($serial >= 233 && $serial <= 270) {
                    $bg = '#EC4899'; $text = '#FFFFFF';
                } elseif ($serial >= 271 && $serial <= 308) {
                    $bg = '#EF4444'; $text = '#FFFFFF';
                } elseif ($serial >= 309 && $serial <= 329) {
                    $bg = '#8B4513'; $text = '#FFFFFF';
                } else {
                    $bg = '#FFFFFF'; $text = '#000000';
                }
            @endphp

            <tr>

                <!-- LEFT: QR -->
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

                <!-- RIGHT: FRONT -->
                <td>
                    <div class="ticket" style="background-color: {{ $bg }}; color: {{ $text }};">

                        <div class="event-title">
                            {{ $event->title }}
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

                                <!-- <div class="front-subtitle">
                                    This pass grants authorized access.<br>
                                    Keep it safe and present it when required.
                                </div> -->

                                <div class="serial-pill">
                                    {{ $pass->serialNumber }}
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