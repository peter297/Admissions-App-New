<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 4px; padding-bottom: 3px; border-bottom: 2px solid #333; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
        p.sub { margin: 0 0 6px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; page-break-inside: avoid; }
        th, td { border: 1px solid #999; padding: 4px 6px; }
        th { background: #eee; text-align: center; }
        td.n { text-align: right; }
        td.name, th.name { text-align: left; }
        tr.total td { font-weight: bold; background: #f3f3f3; }
        tr.branch td { font-weight: bold; background: #e3e3e3; }
        .note { margin-top: 14px; color: #555; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="sub">{{ $subtitle }}</p>

    @foreach ($report['branches'] as $branch)
        <h2>{{ $branch['name'] }}</h2>

        @forelse ($branch['sections'] as $section)
            <h3>{{ $section['name'] }}</h3>
            <table>
                <thead>
                    <tr>
                        <th class="name" rowspan="2">Class</th>
                        <th colspan="3">Learners Enrolled</th>
                        <th colspan="3">Learners who left</th>
                    </tr>
                    <tr>
                        <th>Boys</th><th>Girls</th><th>Total</th>
                        <th>Boys</th><th>Girls</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td class="name">{{ $row['class'] }}</td>
                            <td class="n">{{ $row['boys'] }}</td>
                            <td class="n">{{ $row['girls'] }}</td>
                            <td class="n">{{ $row['total'] }}</td>
                            <td class="n">{{ $row['left_boys'] }}</td>
                            <td class="n">{{ $row['left_girls'] }}</td>
                            <td class="n">{{ $row['left_total'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="name">Total {{ $section['name'] }}</td>
                        <td class="n">{{ $section['totals']['boys'] }}</td>
                        <td class="n">{{ $section['totals']['girls'] }}</td>
                        <td class="n">{{ $section['totals']['total'] }}</td>
                        <td class="n">{{ $section['totals']['left_boys'] }}</td>
                        <td class="n">{{ $section['totals']['left_girls'] }}</td>
                        <td class="n">{{ $section['totals']['left_total'] }}</td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p>No learners to report for this period.</p>
        @endforelse

        @if (count($branch['sections']) > 0)
            <table>
                <tr class="branch">
                    <td class="name">Total {{ $branch['name'] }}</td>
                    <td class="n">{{ $branch['totals']['boys'] }}</td>
                    <td class="n">{{ $branch['totals']['girls'] }}</td>
                    <td class="n">{{ $branch['totals']['total'] }}</td>
                    <td class="n">{{ $branch['totals']['left_boys'] }}</td>
                    <td class="n">{{ $branch['totals']['left_girls'] }}</td>
                    <td class="n">{{ $branch['totals']['left_total'] }}</td>
                </tr>
            </table>
        @endif
    @endforeach

    @if ($report['show_grand'])
        <h2>All branches</h2>
        <table>
            <thead>
                <tr>
                    <th class="name" rowspan="2"></th>
                    <th colspan="3">Learners Enrolled</th>
                    <th colspan="3">Learners who left</th>
                </tr>
                <tr>
                    <th>Boys</th><th>Girls</th><th>Total</th>
                    <th>Boys</th><th>Girls</th><th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr class="branch">
                    <td class="name">School total</td>
                    <td class="n">{{ $report['grand']['boys'] }}</td>
                    <td class="n">{{ $report['grand']['girls'] }}</td>
                    <td class="n">{{ $report['grand']['total'] }}</td>
                    <td class="n">{{ $report['grand']['left_boys'] }}</td>
                    <td class="n">{{ $report['grand']['left_girls'] }}</td>
                    <td class="n">{{ $report['grand']['left_total'] }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <p class="note">
        Learners who left are those made
        inactive or transferred during the period. Classes with no learners and no leavers are not listed.
    </p>
</body>
</html>
