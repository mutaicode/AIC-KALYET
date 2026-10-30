
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!in_array($role, ['projects_officer', 'admin',], true)) {
    http_response_code(403);
    exit('Access denied. Only the Projects Officer or Admin can view this report.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? 'All';

$allowedStatuses = ['All', 'Planned', 'Ongoing', 'On Hold', 'Completed', 'Cancelled'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'All';
}

$startValid = $startDate === '' ||
    (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate);
$endValid = $endDate === '' ||
    (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate);

if (!$startValid || !$endValid ||
    ($startDate !== '' && $endDate !== '' && $startDate > $endDate)) {
    $startDate = '';
    $endDate = '';
    $dateError = 'Please enter valid dates, with the start date not after the end date.';
} else {
    $dateError = '';
}

/*
 * Project list filters use the project start date.
 * Transaction totals use the transaction date.
 */
$where = [];
$params = [];
$types = '';

if ($status !== 'All') {
    $where[] = 'p.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($startDate !== '') {
    $where[] = '(p.start_date IS NOT NULL AND p.start_date >= ?)';
    $params[] = $startDate;
    $types .= 's';
}

if ($endDate !== '') {
    $where[] = '(p.start_date IS NOT NULL AND p.start_date <= ?)';
    $params[] = $endDate;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        p.id,
        p.project_code,
        p.project_name,
        p.project_category,
        p.location,
        p.start_date,
        p.expected_completion_date,
        p.actual_completion_date,
        p.budget,
        p.amount_spent,
        p.progress_percentage,
        p.status,
        COALESCE(t.contributions, 0) AS contributions,
        COALESCE(t.expenses, 0) AS expenses
    FROM projects p
    LEFT JOIN (
        SELECT
            project_id,
            SUM(CASE WHEN transaction_type = 'Contribution'
                     THEN amount ELSE 0 END) AS contributions,
            SUM(CASE WHEN transaction_type = 'Expense'
                     THEN amount ELSE 0 END) AS expenses
        FROM project_transactions
        WHERE (? = '' OR transaction_date >= ?)
          AND (? = '' OR transaction_date <= ?)
        GROUP BY project_id
    ) t ON t.project_id = p.id
    $whereSql
    ORDER BY
        CASE p.status
            WHEN 'Ongoing' THEN 1
            WHEN 'Planned' THEN 2
            WHEN 'On Hold' THEN 3
            WHEN 'Completed' THEN 4
            WHEN 'Cancelled' THEN 5
            ELSE 6
        END,
        p.project_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit('Unable to prepare the report query. Check your database table names and columns.');
}

$transactionStart = $startDate;
$transactionEnd = $endDate;

$bindTypes = 'ssss' . $types;
$bindValues = [$transactionStart, $transactionStart, $transactionEnd, $transactionEnd];

foreach ($params as $param) {
    $bindValues[] = $param;
}

if ($bindTypes !== '') {
    $stmt->bind_param($bindTypes, ...$bindValues);
}

$stmt->execute();
$result = $stmt->get_result();

$projects = [];
$totalBudget = 0;
$totalRecordedSpent = 0;
$totalContributions = 0;
$totalExpenses = 0;
$statusCounts = [
    'Planned' => 0,
    'Ongoing' => 0,
    'On Hold' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

while ($row = $result->fetch_assoc()) {
    $projects[] = $row;

    $totalBudget += (float)$row['budget'];
    $totalRecordedSpent += (float)$row['amount_spent'];
    $totalContributions += (float)$row['contributions'];
    $totalExpenses += (float)$row['expenses'];

    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']]++;
    }
}

$stmt->close();

$projectCount = count($projects);
$financialBalance = $totalContributions - $totalExpenses;

function money($amount): string {
    return 'KSh ' . number_format((float)$amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Reports | A.I.C Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f1f5f9;
            color: #1e293b;
            font-family: Arial, Helvetica, sans-serif;
        }

        .container {
            max-width: 1250px;
            margin: auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0;
            color: #123a66;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 10px 15px;
            color: white;
            background: #164e8a;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: #0f3764;
        }

        .btn-secondary {
            background: #475569;
        }

        .btn-green {
            background: #15803d;
        }

        .filters, .panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .06);
        }

        .filters form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-weight: bold;
            font-size: 13px;
        }

        input, select {
            min-width: 160px;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat {
            background: white;
            padding: 18px;
            border-radius: 10px;
            border-left: 4px solid #2563eb;
            box-shadow: 0 3px 12px rgba(15, 23, 42, .05);
        }

        .stat small {
            display: block;
            color: #64748b;
            margin-bottom: 8px;
        }

        .stat strong {
            font-size: 20px;
            overflow-wrap: anywhere;
        }

        .panel h2 {
            margin-top: 0;
            color: #123a66;
            font-size: 20px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 11px 9px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #eaf1f8;
            color: #123a66;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 20px;
            background: #e2e8f0;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .Ongoing { background: #dcfce7; color: #166534; }
        .Completed { background: #dbeafe; color: #1d4ed8; }
        .Planned { background: #fef3c7; color: #92400e; }
        .On-Hold { background: #ffedd5; color: #9a3412; }
        .Cancelled { background: #fee2e2; color: #991b1b; }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .status-item {
            border: 1px solid #e2e8f0;
            padding: 12px;
            border-radius: 8px;
        }

        .status-item strong {
            display: block;
            font-size: 22px;
            margin-top: 7px;
        }

        .letterhead {
            display: none;
        }

        .error {
            padding: 12px;
            margin-bottom: 15px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .status-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 12px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filters form, .field, input, select {
                width: 100%;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: white;
                color: #000;
                font-size: 10px;
            }

            .container {
                max-width: none;
            }

            .no-print, .filters {
                display: none !important;
            }

            .letterhead {
                display: block;
                text-align: center;
                padding-bottom: 12px;
                margin-bottom: 15px;
                border-bottom: 2px solid #123a66;
            }

            .letterhead h1 {
                color: #123a66;
                font-size: 21px;
                margin-bottom: 5px;
            }

            .letterhead h2 {
                margin: 5px 0;
                font-size: 15px;
            }

            .panel, .stat {
                box-shadow: none;
                border: 1px solid #ccc;
                break-inside: avoid;
            }

            .stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 7px;
            }

            .stat {
                padding: 9px;
            }

            .stat strong {
                font-size: 13px;
            }

            .status-grid {
                grid-template-columns: repeat(5, 1fr);
            }

            table {
                width: 100%;
                font-size: 9px;
            }

            th, td {
                padding: 6px 5px;
                font-size: 9px;
                border: 1px solid #bbb;
            }

            th {
                background: #eaf1f8 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            tr {
                break-inside: avoid;
            }

            .badge {
                border: 1px solid #aaa;
                background: white !important;
                color: black !important;
            }

            .report-footer {
                margin-top: 30px;
                display: block;
            }

            .signature-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 60px;
                margin-top: 35px;
            }

            .signature {
                border-top: 1px solid #333;
                padding-top: 8px;
                text-align: center;
            }
        }

        .report-footer {
            display: none;
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<div class="container">

    <div class="topbar no-print">
        <div>
            <h1>Project Reports</h1>
            <p>Project progress and financial summaries</p>
        </div>

        <div>
            <a class="btn btn-secondary" href="dashboard.php">Back to Dashboard</a>
            <button class="btn btn-green" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <div class="letterhead">
        <h1>A.I.C KALYET LOCAL CHURCH</h1>
        <h2>PROJECTS OFFICE</h2>
        <p><strong>PROJECT AND FINANCIAL REPORT</strong></p>
        <p>
            Reporting period:
            <?= e($startDate ?: 'Beginning'); ?>
            to
            <?= e($endDate ?: 'Present'); ?>
        </p>
        <p>Generated on <?= date('d M Y, h:i A'); ?></p>
    </div>

    <?php if ($dateError !== ''): ?>
        <div class="error no-print"><?= e($dateError); ?></div>
    <?php endif; ?>

    <div class="filters no-print">
        <h2>Filter Report</h2>

        <form method="GET">
            <div class="field">
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date"
                       value="<?= e($startDate); ?>">
            </div>

            <div class="field">
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date"
                       value="<?= e($endDate); ?>">
            </div>

            <div class="field">
                <label for="status">Project Status</label>
                <select id="status" name="status">
                    <?php foreach ($allowedStatuses as $option): ?>
                        <option value="<?= e($option); ?>"
                            <?= $status === $option ? 'selected' : ''; ?>>
                            <?= e($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn" type="submit">Apply Filters</button>
            <a class="btn btn-secondary" href="project_reports.php">Reset</a>
        </form>

        <p>
            <small>
                Date filters use project start dates for the project list and
                transaction dates for contribution and expense totals.
            </small>
        </p>
    </div>

    <div class="stats">
        <div class="stat">
            <small>Projects in Report</small>
            <strong><?= number_format($projectCount); ?></strong>
        </div>

        <div class="stat">
            <small>Total Project Budgets</small>
            <strong><?= money($totalBudget); ?></strong>
        </div>

        <div class="stat">
            <small>Recorded Project Spending</small>
            <strong><?= money($totalRecordedSpent); ?></strong>
        </div>

        <div class="stat">
            <small>Total Contributions</small>
            <strong><?= money($totalContributions); ?></strong>
        </div>

        <div class="stat">
            <small>Total Transaction Expenses</small>
            <strong><?= money($totalExpenses); ?></strong>
        </div>

        <div class="stat">
            <small>Contribution Balance</small>
            <strong><?= money($financialBalance); ?></strong>
        </div>

        <div class="stat">
            <small>Completed Projects</small>
            <strong><?= number_format($statusCounts['Completed']); ?></strong>
        </div>

        <div class="stat">
            <small>Ongoing Projects</small>
            <strong><?= number_format($statusCounts['Ongoing']); ?></strong>
        </div>
    </div>

    <div class="panel">
        <h2>Project Status Summary</h2>

        <div class="status-grid">
            <?php foreach ($statusCounts as $label => $count): ?>
                <div class="status-item">
                    <span><?= e($label); ?></span>
                    <strong><?= number_format($count); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Project Register and Financial Summary</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Project Code / Name</th>
                    <th>Category / Location</th>
                    <th>Start Date</th>
                    <th>Expected Completion</th>
                    <th>Budget (KSh)</th>
                    <th>Recorded Spending (KSh)</th>
                    <th>Contributions (KSh)</th>
                    <th>Transaction Expenses (KSh)</th>
                    <th>Balance (KSh)</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$projects): ?>
                    <tr>
                        <td colspan="12" class="empty">
                            No projects match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $index => $project): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>

                            <td>
                                <strong><?= e($project['project_code']); ?></strong><br>
                                <?= e($project['project_name']); ?>
                            </td>

                            <td>
                                <?= e($project['project_category'] ?: 'N/A'); ?><br>
                                <?= e($project['location'] ?: 'N/A'); ?>
                            </td>

                            <td>
                                <?= $project['start_date']
                                    ? e(date('d M Y', strtotime($project['start_date'])))
                                    : 'N/A'; ?>
                            </td>

                            <td>
                                <?= $project['expected_completion_date']
                                    ? e(date('d M Y', strtotime($project['expected_completion_date'])))
                                    : 'N/A'; ?>
                            </td>

                            <td><?= number_format((float)$project['budget'], 2); ?></td>

                            <td><?= number_format((float)$project['amount_spent'], 2); ?></td>

                            <td><?= number_format((float)$project['contributions'], 2); ?></td>

                            <td><?= number_format((float)$project['expenses'], 2); ?></td>

                            <td>
                                <?= number_format(
                                    (float)$project['contributions'] -
                                    (float)$project['expenses'],
                                    2
                                ); ?>
                            </td>

                            <td><?= (int)$project['progress_percentage']; ?>%</td>

                            <td>
                                <span class="badge <?= e(str_replace(' ', '-', $project['status'])); ?>">
                                    <?= e($project['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-footer">
        <p>
            <strong>Note:</strong> This report is based on the project and
            transaction records available in the church management system.
        </p>

        <div class="signature-grid">
            <div class="signature">
                Projects Officer's Signature and Date
            </div>
            <div class="signature">
                Chairperson's Signature and Date
            </div>
        </div>
    </div>

</div>
</body>
</html>
