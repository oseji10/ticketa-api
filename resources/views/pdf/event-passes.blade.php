<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Event Passes</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 15px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .header p {
            margin: 4px 0;
            font-size: 12px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            vertical-align: top;
            width: 33%;
        }

        .ticket {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            margin: 5px;
            text-align: center;
            height: 240px;
            page-break-inside: avoid;
        }

        .event-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .event-meta {
            font-size: 10px;
            color: #666;
            margin-bottom: 8px;
        }

        .qr {
            margin: 8px 0;
        }

        .serial {
            font-weight: bold;
            font-size: 11px;
            margin-top: 6px;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>{{ $event->title }}</h1>
    <p>
        {{ $event->startDate }} - {{ $event->endDate }}
        @if($event->location)
            | {{ $event->location }}
        @endif
    </p>
</div>

@php
    $chunks = $passes->chunk(3); // 3 per column
@endphp

<table>
    <tr>
        @foreach($chunks as $column)
            <td>
                @foreach($column as $pass)
                    <div class="ticket">
                        <div class="event-title">
                            {{ $event->title }}
                        </div>

                        <div class="event-meta">
                            {{ $event->startDate }} - {{ $event->endDate }} <br>
                            @if($event->location)
                                {{ $event->location }}
                            @endif
                        </div>

                        <div class="qr">
                            <img src="{{ public_path('storage/' . $pass->qrPath) }}" width="100">
                        </div>

                        <div class="serial">
                            {{ $pass->serialNumber ?? $pass->passCode }}
                        </div>
                    </div>
                @endforeach
            </td>
        @endforeach
    </tr>
</table>

</body>
</html>