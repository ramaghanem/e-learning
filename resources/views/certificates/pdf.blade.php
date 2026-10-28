{{--
    Certificate of completion as a PDF (A4 landscape), rendered by dompdf in CertificateController@show.

    dompdf does not support flexbox or web fonts, so the layout uses tables and the
    DejaVu fonts that come with dompdf. All the CSS is inline.

    Expects:
        $course       the completed course, with instructor
        $enrollment   the student's enrollment (completed_at, certificate_code)
        $student      the logged-in student
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Certificate of Completion · {{ $course->title }}</title>

    <style>
        @page { margin: 12mm; }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            color: #13212b;
        }

        /* Outer and inner frame */
        .frame {
            border: 2.5mm solid #16423C;
            padding: 2.5mm;
        }

        .inner {
            border: .5mm solid #16423C;
            background: #f4f8f7;
            height: 172mm;
            text-align: center;
        }

        .brand {
            padding-top: 24mm;
            font-family: "DejaVu Serif", serif;
            font-size: 20pt;
            font-weight: bold;
            color: #16423C;
        }

        .title {
            margin-top: 7mm;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 3pt;
            color: #16423C;
        }

        .rule {
            width: 28mm;
            margin: 4mm auto 0;
            border-top: .8mm solid #f59e0b;
        }

        .text {
            margin-top: 11mm;
            font-size: 11pt;
            color: #4b5a64;
        }

        .name {
            margin: 3mm auto 0;
            padding-bottom: 2mm;
            width: 170mm;
            border-bottom: .3mm solid #c9d6d3;
            font-family: "DejaVu Serif", serif;
            font-size: 30pt;
            font-weight: bold;
            color: #13212b;
        }

        .course {
            margin: 3mm auto 0;
            width: 200mm;
            font-family: "DejaVu Serif", serif;
            font-size: 18pt;
            font-weight: bold;
            color: #16423C;
        }

        /* Completion date / instructor / certificate ID */
        .details {
            width: 220mm;
            margin: 20mm auto 0;
            border-collapse: collapse;
        }

        .details td {
            width: 33%;
            padding: 0 6mm;
            vertical-align: top;
            text-align: center;
        }

        .value {
            padding-bottom: 1.5mm;
            border-bottom: .3mm solid #16423C;
            font-size: 11pt;
            font-weight: bold;
        }

        .label {
            margin-top: 1.5mm;
            font-size: 8pt;
            letter-spacing: 1pt;
            color: #6b7a83;
        }
    </style>
</head>
<body>

    <div class="frame">
        <div class="inner">

            <div class="brand">LearnHub</div>

            <div class="title">CERTIFICATE OF COMPLETION</div>
            <div class="rule"></div>

            <div class="text">This is to certify that</div>
            <div class="name">{{ $student->name }}</div>

            <div class="text">has successfully completed the course</div>
            <div class="course">{{ $course->title }}</div>

            <table class="details">
                <tr>
                    <td>
                        <div class="value">{{ $enrollment->completed_at->format('F j, Y') }}</div>
                        <div class="label">DATE OF COMPLETION</div>
                    </td>
                    <td>
                        <div class="value">{{ $course->instructor?->name ?? '—' }}</div>
                        <div class="label">INSTRUCTOR</div>
                    </td>
                    <td>
                        <div class="value">{{ $enrollment->certificate_code }}</div>
                        <div class="label">CERTIFICATE ID</div>
                    </td>
                </tr>
            </table>

        </div>
    </div>

</body>
</html>
