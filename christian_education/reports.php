
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$allowedRoles = ['christian_education', 'admin'];

if (!isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowedRoles, true)) {
    http_response_code(403);
    exit('Access denied. Christian Education Leader access required.');
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');
$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

if ($classId === false || $classId === null || $classId < 1) {
    $classId = 0;
}

function validDate($date) {
    if ($date === '') {
        return true;
    }

    $parsed = DateTime::createFromFormat('!Y-m-d', $date);

    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

$error = '';

if (!validDate($fromDate) || !validDate($toDate)) {
    $error = 'Please select valid dates.';
} elseif ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $error = 'The starting date cannot be after the ending date.';
}

$classes = [];
$classResult = $conn->query(
    "SELECT id, class_name
     FROM ce_classes
     ORDER BY class_name ASC"
);

if ($classResult) {
    while ($row = $classResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

$classSummary = [];
$summaryResult = $conn->query(
    "SELECT
        c.id,
        c.class_name,
        c.age_group,
        c.teacher_name,
        c.meeting_day,
        c.status,
        COUNT(l.id) AS lesson_count,
        SUM(CASE WHEN l.status = 'Published' THEN 1 ELSE 0 END)
            AS published_count
     FROM ce_classes c
     LEFT JOIN ce_lessons l ON l.class_id = c.id
     GROUP BY c.id, c.class_name, c.age_group,
              c.teacher_name, c.meeting_day, c.status
     ORDER BY c.class_name ASC"
);

if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $classSummary[] = $row;
    }
}

$lessons = [];

if ($error === '') {
    $sql = "SELECT
                l.id,
                l.lesson_title,
                l.bible_reference,
                l.lesson_date,
                l.teacher_name,
                l.status,
                c.class_name
            FROM ce_lessons l
            LEFT JOIN ce_classes c ON c.id = l.class_id
            WHERE l.status <> 'Archived'";

    $types = '';
    $params = [];

    if ($fromDate !== '') {
        $sql .= " AND l.lesson_date >= ?";
        $types .= 's';
        $params[] = $fromDate;
    }

    if ($toDate !== '') {
        $sql .= " AND l.lesson_date <= ?";
        $types .= 's';
        $params[] = $toDate;
    }

    if ($classId > 0) {
        $sql .= " AND l.class_id = ?";
        $types .= 'i';
        $params[] = $classId;
    }

    $sql .= " ORDER BY l.lesson_date DESC, l.lesson_title ASC";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $lessons[] = $row;
        }

        $stmt->close();
    } else {
        $error = 'Unable to prepare the lesson report.';
    }
}

$totalClasses = count($classSummary);
$totalActiveClasses = 0;
$totalLessons = count($lessons);
$totalPublished = 0;

foreach ($classSummary as $class) {
    if ($class['status'] === 'Active') {
        $totalActiveClasses++;
    }
}

foreach ($lessons as $lesson) {
    if ($lesson['status'] === 'Published') {
        $totalPublished++;
    }
}

$selectedClassName = 'All Classes';

foreach ($classes as $class) {
    if ((int)$class['id'] === $classId) {
        $selectedClassName = $class['class_name'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Christian Education Reports | A.I.C Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
            color: #1e293b;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .toolbar a,
        .toolbar button {
            display: inline-block;
            padding: 11px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .back {
            background: #334155;
            color: white;
        }

        .print {
            background: #166534;
            color: white;
        }

        .filter,
        .panel {
            background: white;
            padding: 20px;
            margin-bottom: 22px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        .filter button {
            width: 100%;
            padding: 11px;
            background: #1d4ed8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .reset {
            display: inline-block;
            margin-top: 12px;
            color: #1d4ed8;
            text-decoration: none;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .stat {
            background: white;
            padding: 20px;
            border-radius: 9px;
            border-left: 5px solid #1d4ed8;
            box-shadow: 0 3px 12px rgba(0,0,0,.05);
        }

        .stat h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #64748b;
        }

        .stat strong {
            font-size: 28px;
        }

        .letterhead {
            text-align: center;
            padding-bottom: 15px;
            margin-bottom: 20px;
            border-bottom: 3px double #334155;
        }

        .letterhead h1 {
            margin: 5px 0;
            font-size: 25px;
        }

        .letterhead h2 {
            margin: 8px 0;
            font-size: 19px;
        }

        .letterhead p {
            margin: 5px 0;
        }

        .report-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #e2e8f0;
        }

        .status {
            font-weight: bold;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-top: 55px;
        }

        .signature {
            border-top: 1px solid #334155;
            padding-top: 8px;
            text-align: center;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: white;
                color: black;
                font-size: 11px;
            }

            .container {
                max-width: none;
            }

            .no-print,
            .toolbar,
            .filter {
                display: none !important;
            }

            .panel,
            .stat {
                box-shadow: none;
                border-radius: 0;
            }

            .stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .stat {
                padding: 10px;
            }

            .stat strong {
                font-size: 20px;
            }

            .panel {
                padding: 5px 0;
                margin-bottom: 15px;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
            }

            th {
                background: #e2e8f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .footer {
                color: black;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .signatures {
                gap: 20px;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="toolbar no-print">
        <a href="dashboard.php" class="back">← Back to Dashboard</a>
        <button type="button" class="print" onclick="window.print()">
            Print Report / Save as PDF
        </button>
    </div>

    <form method="GET" class="filter no-print">
        <h2>Filter Lesson Report</h2>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="filter-grid">
            <div>
                <label for="from_date">From Date</label>
                <input type="date" id="from_date" name="from_date"
                       value="<?= e($fromDate) ?>">
            </div>

            <div>
                <label for="to_date">To Date</label>
                <input type="date" id="to_date" name="to_date"
                       value="<?= e($toDate) ?>">
            </div>

            <div>
                <label for="class_id">Class</label>
                <select id="class_id" name="class_id">
                    <option value="0">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int)$class['id'] ?>"
                            <?= $classId === (int)$class['id'] ? 'selected' : '' ?>>
                            <?= e($class['class_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit">Generate Report</button>
            </div>
        </div>

        <a class="reset" href="reports.php">Clear Filters</a>
    </form>

    <div class="letterhead">
        <p><strong>AFRICA INLAND CHURCH (A.I.C)</strong></p>
        <h1>KALYET LOCAL CHURCH</h1>
        <h2>CHRISTIAN EDUCATION REPORT</h2>
        <p>Classes, Teachers and Lesson Records</p>
    </div>

    <div class="report-meta">
        <span><strong>Class:</strong> <?= e($selectedClassName) ?></span>
        <span>
            <strong>Period:</strong>
            <?= $fromDate !== '' ? e($fromDate) : 'Beginning' ?>
            to
            <?= $toDate !== '' ? e($toDate) : 'Present' ?>
        </span>
        <span><strong>Printed:</strong> <?= date('d M Y, h:i A') ?></span>
    </div>

    <div class="stats">
        <div class="stat">
            <h3>Total Classes</h3>
            <strong><?= $totalClasses ?></strong>
        </div>

        <div class="stat">
            <h3>Active Classes</h3>
            <strong><?= $totalActiveClasses ?></strong>
        </div>

        <div class="stat">
            <h3>Lessons in This Report</h3>
            <strong><?= $totalLessons ?></strong>
        </div>

        <div class="stat">
            <h3>Published Lessons in Report</h3>
            <strong><?= $totalPublished ?></strong>
        </div>
    </div>

    <section class="panel">
        <h2>1. Class Summary</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Class Name</th>
                    <th>Age Group</th>
                    <th>Teacher</th>
                    <th>Meeting Day</th>
                    <th>Status</th>
                    <th>Total Lessons</th>
                    <th>Published Lessons</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($classSummary)): ?>
                    <tr>
                        <td colspan="8">No classes have been registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classSummary as $index => $class): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e($class['class_name']) ?></td>
                            <td><?= e($class['age_group'] ?: 'N/A') ?></td>
                            <td><?= e($class['teacher_name'] ?: 'Not assigned') ?></td>
                            <td><?= e($class['meeting_day'] ?: 'N/A') ?></td>
                            <td><?= e($class['status']) ?></td>
                            <td><?= (int)$class['lesson_count'] ?></td>
                            <td><?= (int)$class['published_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>2. Lesson Records</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Lesson Date</th>
                    <th>Lesson Title</th>
                    <th>Bible Reference</th>
                    <th>Class</th>
                    <th>Teacher</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($error !== ''): ?>
                    <tr>
                        <td colspan="7">Correct the report filters to view lesson records.</td>
                    </tr>
                <?php elseif (empty($lessons)): ?>
                    <tr>
                        <td colspan="7">No lesson records match the selected filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= e(date('d M Y', strtotime($lesson['lesson_date']))) ?></td>
                            <td><?= e($lesson['lesson_title']) ?></td>
                            <td><?= e($lesson['bible_reference'] ?: 'N/A') ?></td>
                            <td><?= e($lesson['class_name'] ?: 'General') ?></td>
                            <td><?= e($lesson['teacher_name'] ?: 'Not assigned') ?></td>
                            <td class="status"><?= e($lesson['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="signatures">
        <div class="signature">
            Christian Education Leader's Signature
        </div>
        <div class="signature">
            Pastor / Church Representative's Signature
        </div>
    </div>

    <div class="footer">
        A.I.C Kalyet Local Church — Christian Education Department
    </div>

</div>
</body>
</html>
