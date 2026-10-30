
<?php
session_start();
require_once 'config/database.php';

// Only authorized officials can print attendance reports
$allowedRoles = ['admin', 'chairperson', 'treasurer'];

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowedRoles, true)
) {
    http_response_code(403);
    exit('Access denied.');
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid attendance report ID.');
}

$stmt = $conn->prepare("
    SELECT
        a.*,
        u.username AS recorder
    FROM attendance_summaries a
    INNER JOIN users u ON u.id = a.recorded_by
    WHERE a.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$attendance = $result->fetch_assoc();
$stmt->close();

if (!$attendance) {
    http_response_code(404);
    exit('Attendance report not found.');
}

$total = (int) $attendance['men_count']
       + (int) $attendance['women_count']
       + (int) $attendance['youth_count']
       + (int) $attendance['children_count']
       + (int) $attendance['visitors_count'];

$reportDate = date(
    'd F Y',
    strtotime($attendance['attendance_date'])
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report - AIC Kalyet Church</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f1f5f9;
            color: #1e293b;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        button, .back-link {
            padding: 11px 18px;
            border: none;
            border-radius: 6px;
            background: #14532d;
            color: white;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .back-link {
            background: #475569;
        }

        .report {
            max-width: 850px;
            margin: auto;
            padding: 40px;
            background: white;
            border: 1px solid #d1d5db;
        }

        .letterhead {
            text-align: center;
            border-bottom: 3px solid #166534;
            padding-bottom: 18px;
            margin-bottom: 25px;
        }

        .letterhead h1 {
            margin: 0 0 8px;
            color: #14532d;
            font-size: 27px;
            text-transform: uppercase;
        }

        .letterhead h2 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .letterhead p {
            margin: 5px 0;
            font-size: 12px;
            color: #475569;
        }

        .report-title {
            text-align: center;
            margin: 25px 0;
        }

        .report-title h2 {
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .report-title p {
            margin: 0;
            color: #475569;
        }

        .details {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .details th,
        .details td {
            border: 1px solid #cbd5e1;
            padding: 12px;
            text-align: left;
        }

        .details th {
            width: 40%;
            background: #f8fafc;
        }

        .total-row th,
        .total-row td {
            background: #ecfdf5;
            color: #14532d;
            font-size: 17px;
            font-weight: bold;
        }

        .notes {
            margin-top: 25px;
            padding: 15px;
            border: 1px solid #cbd5e1;
            min-height: 65px;
        }

        .notes h3 {
            margin-top: 0;
            font-size: 15px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            gap: 35px;
            margin-top: 65px;
        }

        .signature {
            width: 45%;
            border-top: 1px solid #334155;
            padding-top: 10px;
            text-align: center;
            font-size: 13px;
        }

        .footer {
            margin-top: 45px;
            padding-top: 12px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 11px;
            color: #64748b;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 15mm;
            }

            body {
                padding: 0;
                background: white;
                color: black;
            }

            .toolbar {
                display: none !important;
            }

            .report {
                max-width: none;
                padding: 0;
                border: none;
            }

            .letterhead h1 {
                color: black;
            }

            .total-row th,
            .total-row td {
                background: #eee !important;
                color: black;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>

<div class="toolbar">
    <a class="back-link" href="attendance.php">Back to Attendance</a>
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<main class="report">

    <header class="letterhead">
        <h1>AIC Kalyet Church</h1>
        <h2>Official Church Records</h2>
        <p>Church Attendance and Service Statistics</p>
        <p>Official Document — For Church Use</p>
    </header>

    <section class="report-title">
        <h2>Attendance Report</h2>
        <p><?= e($attendance['service_name']) ?></p>
    </section>

    <table class="details">
        <tr>
            <th>Attendance Date</th>
            <td><?= e($reportDate) ?></td>
        </tr>
        <tr>
            <th>Service / Meeting</th>
            <td><?= e($attendance['service_name']) ?></td>
        </tr>
        <tr>
            <th>Men</th>
            <td><?= number_format((int) $attendance['men_count']) ?></td>
        </tr>
        <tr>
            <th>Women</th>
            <td><?= number_format((int) $attendance['women_count']) ?></td>
        </tr>
        <tr>
            <th>Youth</th>
            <td><?= number_format((int) $attendance['youth_count']) ?></td>
        </tr>
        <tr>
            <th>Children</th>
            <td><?= number_format((int) $attendance['children_count']) ?></td>
        </tr>
        <tr>
            <th>Visitors</th>
            <td><?= number_format((int) $attendance['visitors_count']) ?></td>
        </tr>
        <tr class="total-row">
            <th>Total Attendance</th>
            <td><?= number_format($total) ?></td>
        </tr>
    </table>

    <section class="notes">
        <h3>Notes / Observations</h3>
        <?php if (trim((string) $attendance['notes']) !== ''): ?>
            <p><?= nl2br(e($attendance['notes'])) ?></p>
        <?php else: ?>
            <p>No notes recorded.</p>
        <?php endif; ?>
    </section>

    <div class="signatures">
        <div class="signature">
            Recorded by: <?= e($attendance['recorder']) ?>
        </div>
        <div class="signature">
            Verified by: Church Chairperson
        </div>
    </div>

    <footer class="footer">
        AIC Kalyet Church — Official Attendance Record<br>
        Generated on <?= e(date('d F Y, h:i A')) ?>
    </footer>

</main>

</body>
</html>
