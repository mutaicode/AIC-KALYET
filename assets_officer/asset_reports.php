
<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Restrict access
$allowed_roles = ['assets_officer', 'admin'];

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowed_roles, true)
) {
    http_response_code(403);
    exit("Access denied.");
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Report filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$asset_status = trim($_GET['asset_status'] ?? '');
$report_type = $_GET['report_type'] ?? 'assets';

$valid_conditions = ['Good', 'Fair', 'Needs Repair', 'Damaged'];
$valid_asset_statuses = ['Active', 'Under Maintenance', 'Disposed'];

if (!in_array($condition, $valid_conditions, true)) {
    $condition = '';
}

if (!in_array($asset_status, $valid_asset_statuses, true)) {
    $asset_status = '';
}

if (!in_array($report_type, ['assets', 'maintenance'], true)) {
    $report_type = 'assets';
}

// Load categories
$categories = [];
$category_result = $conn->query(
    "SELECT DISTINCT category FROM assets ORDER BY category ASC"
);

if ($category_result) {
    while ($cat = $category_result->fetch_assoc()) {
        $categories[] = $cat['category'];
    }
}

// Asset summary statistics
$summary_result = $conn->query(
    "SELECT
        COUNT(*) AS total_assets,
        SUM(status = 'Active') AS active_assets,
        SUM(status = 'Under Maintenance') AS under_maintenance,
        SUM(status = 'Disposed') AS disposed_assets,
        SUM(condition_status = 'Good') AS good_assets,
        SUM(condition_status = 'Fair') AS fair_assets,
        SUM(condition_status = 'Needs Repair') AS needs_repair,
        SUM(condition_status = 'Damaged') AS damaged_assets
     FROM assets"
);

$summary = $summary_result
    ? $summary_result->fetch_assoc()
    : [];

$total_assets = (int)($summary['total_assets'] ?? 0);
$active_assets = (int)($summary['active_assets'] ?? 0);
$under_maintenance = (int)($summary['under_maintenance'] ?? 0);
$disposed_assets = (int)($summary['disposed_assets'] ?? 0);
$good_assets = (int)($summary['good_assets'] ?? 0);
$fair_assets = (int)($summary['fair_assets'] ?? 0);
$needs_repair = (int)($summary['needs_repair'] ?? 0);
$damaged_assets = (int)($summary['damaged_assets'] ?? 0);

// Maintenance summary
$maintenance_summary_result = $conn->query(
    "SELECT
        COUNT(*) AS total_records,
        SUM(status = 'Pending') AS pending_records,
        SUM(status = 'In Progress') AS in_progress_records,
        SUM(status = 'Completed') AS completed_records
     FROM asset_maintenance"
);

$maintenance_summary = $maintenance_summary_result
    ? $maintenance_summary_result->fetch_assoc()
    : [];

$total_maintenance = (int)($maintenance_summary['total_records'] ?? 0);
$pending_maintenance = (int)($maintenance_summary['pending_records'] ?? 0);
$progress_maintenance = (int)($maintenance_summary['in_progress_records'] ?? 0);
$completed_maintenance = (int)($maintenance_summary['completed_records'] ?? 0);

// Fetch selected report
$records = [];
$sql_error = '';

if ($report_type === 'assets') {
    $sql = "SELECT *
            FROM assets
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= " AND (
            asset_code LIKE ?
            OR asset_name LIKE ?
            OR category LIKE ?
            OR location LIKE ?
            OR assigned_to LIKE ?
        )";

        $term = '%' . $search . '%';

        for ($i = 0; $i < 5; $i++) {
            $params[] = $term;
        }

        $types .= 'sssss';
    }

    if ($category !== '') {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if ($condition !== '') {
        $sql .= " AND condition_status = ?";
        $params[] = $condition;
        $types .= 's';
    }

    if ($asset_status !== '') {
        $sql .= " AND status = ?";
        $params[] = $asset_status;
        $types .= 's';
    }

    $sql .= " ORDER BY category ASC, asset_name ASC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $sql_error = "Unable to prepare the asset report.";
    } else {
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        } else {
            $sql_error = "Unable to load asset records.";
        }

        $stmt->close();
    }

} else {
    $sql = "SELECT
                m.*,
                a.asset_code,
                a.asset_name,
                a.category,
                a.location
            FROM asset_maintenance m
            INNER JOIN assets a ON m.asset_id = a.id
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($search !== '') {
        $sql .= " AND (
            a.asset_code LIKE ?
            OR a.asset_name LIKE ?
            OR a.category LIKE ?
            OR m.issue_description LIKE ?
            OR m.resolution LIKE ?
        )";

        $term = '%' . $search . '%';

        for ($i = 0; $i < 5; $i++) {
            $params[] = $term;
        }

        $types .= 'sssss';
    }

    if ($category !== '') {
        $sql .= " AND a.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if ($condition !== '') {
        $sql .= " AND a.condition_status = ?";
        $params[] = $condition;
        $types .= 's';
    }

    if ($asset_status !== '') {
        $sql .= " AND a.status = ?";
        $params[] = $asset_status;
        $types .= 's';
    }

    $sql .= " ORDER BY m.created_at DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $sql_error = "Unable to prepare the maintenance report.";
    } else {
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        } else {
            $sql_error = "Unable to load maintenance records.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asset Reports | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f2f5fa;
            color: #263449;
            font-family: Arial, sans-serif;
        }

        .topbar {
            background: #12345a;
            color: white;
            padding: 22px 5%;
        }

        .topbar h1 {
            margin: 0 0 7px;
            font-size: 25px;
        }

        .topbar p {
            margin: 0;
            color: #d7e6f6;
            font-size: 14px;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            background: white;
            padding: 15px 5%;
            border-bottom: 1px solid #dfe6ef;
        }

        .navigation a {
            color: #174b83;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .container {
            width: 92%;
            max-width: 1400px;
            margin: 25px auto;
        }

        .panel {
            background: white;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 3px 12px #152b4610;
        }

        .panel h2 {
            margin-top: 0;
            color: #163e68;
            font-size: 20px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .stat {
            padding: 18px;
            background: white;
            border-left: 4px solid #2463a5;
            border-radius: 8px;
            box-shadow: 0 3px 12px #152b4610;
        }

        .stat span {
            display: block;
            color: #65758b;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .stat strong {
            font-size: 25px;
            color: #15395f;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field label {
            font-size: 13px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccd6e2;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 11px 16px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .btn-primary {
            background: #1e5b96;
            color: white;
        }

        .btn-secondary {
            background: #e6edf5;
            color: #24415f;
        }

        .btn-print {
            background: #176534;
            color: white;
        }

        .letterhead {
            display: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 11px 10px;
            text-align: left;
            border: 1px solid #dfe5ed;
            font-size: 12px;
            vertical-align: top;
        }

        th {
            background: #eaf0f7;
            color: #193c61;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
            white-space: nowrap;
        }

        .good, .active, .completed {
            background: #e0f4e5;
            color: #176534;
        }

        .fair, .pending {
            background: #fff0d1;
            color: #875900;
        }

        .needs-repair, .in-progress, .under-maintenance {
            background: #dceeff;
            color: #17518a;
        }

        .damaged, .disposed {
            background: #fde5e5;
            color: #9b2424;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #718096;
        }

        .error {
            background: #fde8e7;
            color: #9b2424;
            border-radius: 7px;
            padding: 14px;
            margin-bottom: 18px;
        }

        .note {
            color: #68778c;
            font-size: 13px;
            margin-top: 12px;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #738096;
            font-size: 12px;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 15px;
            }
        }

        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        @media print {
            body {
                background: white;
                color: #111;
                font-size: 10px;
            }

            .topbar,
            .navigation,
            .filters-panel,
            .no-print,
            footer {
                display: none !important;
            }

            .container {
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .panel {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0 0 12px;
            }

            .stats {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 7px;
                margin-bottom: 12px;
            }

            .stat {
                box-shadow: none;
                border: 1px solid #aaa;
                padding: 8px;
            }

            .stat strong {
                font-size: 17px;
            }

            .letterhead {
                display: block;
                text-align: center;
                margin-bottom: 18px;
                padding-bottom: 12px;
                border-bottom: 2px solid #12345a;
            }

            .letterhead h1 {
                margin: 0 0 5px;
                font-size: 20px;
            }

            .letterhead h2 {
                margin: 5px 0;
                font-size: 15px;
            }

            .letterhead p {
                margin: 3px 0;
                font-size: 10px;
            }

            .report-heading {
                margin: 12px 0;
                font-size: 15px;
                text-align: center;
            }

            .table-wrap {
                overflow: visible;
            }

            table {
                width: 100%;
                min-width: 0;
                table-layout: auto;
            }

            th, td {
                padding: 6px;
                font-size: 9px;
                overflow-wrap: anywhere;
            }

            th {
                background: #eaf0f7 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            tr {
                page-break-inside: avoid;
            }

            .badge {
                border: 1px solid #aaa;
                background: white !important;
                color: #111 !important;
            }

            .print-signatures {
                display: flex;
                justify-content: space-between;
                gap: 30px;
                margin-top: 40px;
                page-break-inside: avoid;
            }

            .signature {
                width: 30%;
                border-top: 1px solid #222;
                padding-top: 7px;
                text-align: center;
                font-size: 10px;
            }
        }

        .print-signatures {
            display: none;
        }

        @media print {
            .print-signatures {
                display: flex;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<header class="topbar">
    <h1>Asset Reports</h1>
    <p>A.I.C Kalyet Church Management System</p>
</header>

<nav class="navigation no-print">
    <a href="dashboard.php">Dashboard</a>
    <a href="assets.php">Asset Register</a>
    <a href="maintenance.php">Maintenance</a>
    <a href="asset_reports.php">Asset Reports</a>
    <a href="../dashboard.php">Main Dashboard</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="container">

    <?php if ($sql_error !== ''): ?>
        <div class="error"><?= e($sql_error) ?></div>
    <?php endif; ?>

    <section class="panel filters-panel no-print">
        <h2>Generate Asset Report</h2>

        <form method="GET" action="asset_reports.php">
            <div class="filters">

                <div class="field">
                    <label for="report_type">Report Type</label>
                    <select name="report_type" id="report_type">
                        <option value="assets"
                            <?= $report_type === 'assets' ? 'selected' : '' ?>>
                            Asset Inventory
                        </option>

                        <option value="maintenance"
                            <?= $report_type === 'maintenance' ? 'selected' : '' ?>>
                            Maintenance History
                        </option>
                    </select>
                </div>

                <div class="field">
                    <label for="search">Search</label>
                    <input type="text"
                           id="search"
                           name="search"
                           value="<?= e($search) ?>"
                           placeholder="Asset name, code, issue...">
                </div>

                <div class="field">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>

                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>"
                                <?= $category === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="condition">Asset Condition</label>
                    <select name="condition" id="condition">
                        <option value="">All Conditions</option>

                        <?php foreach ($valid_conditions as $option): ?>
                            <option value="<?= e($option) ?>"
                                <?= $condition === $option ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="asset_status">Asset Status</label>
                    <select name="asset_status" id="asset_status">
                        <option value="">All Asset Statuses</option>

                        <?php foreach ($valid_asset_statuses as $option): ?>
                            <option value="<?= e($option) ?>"
                                <?= $asset_status === $option ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    Generate Report
                </button>

                <a href="asset_reports.php"
                   class="btn btn-secondary">
                    Clear Filters
                </a>

                <button type="button"
                        onclick="window.print()"
                        class="btn btn-print">
                    Print / Save as PDF
                </button>
            </div>

            <p class="note">
                Choose your report type and filters, then generate or print the report.
                Asset condition and status filters also apply to maintenance reports.
            </p>
        </form>
    </section>

    <!-- Printed church letterhead -->
    <section class="letterhead">
        <h1>A.I.C KALYET LOCAL CHURCH</h1>
        <p>ASSET MANAGEMENT AND ACCOUNTABILITY</p>
        <h2>
            <?= $report_type === 'assets'
                ? 'ASSET INVENTORY REPORT'
                : 'ASSET MAINTENANCE REPORT' ?>
        </h2>
        <p>Report generated on: <?= e(date('d F Y, h:i A')) ?></p>

        <?php if ($search !== ''): ?>
            <p>Search filter: <?= e($search) ?></p>
        <?php endif; ?>

        <?php if ($category !== ''): ?>
            <p>Category: <?= e($category) ?></p>
        <?php endif; ?>

        <?php if ($condition !== ''): ?>
            <p>Condition: <?= e($condition) ?></p>
        <?php endif; ?>

        <?php if ($asset_status !== ''): ?>
            <p>Asset status: <?= e($asset_status) ?></p>
        <?php endif; ?>
    </section>

    <h2 class="report-heading" style="display:none">
        Asset Management Report
    </h2>

    <!-- Summary cards -->
    <section class="stats">
        <div class="stat">
            <span>Total Assets</span>
            <strong><?= $total_assets ?></strong>
        </div>

        <div class="stat">
            <span>Active Assets</span>
            <strong><?= $active_assets ?></strong>
        </div>

        <div class="stat">
            <span>Under Maintenance</span>
            <strong><?= $under_maintenance ?></strong>
        </div>

        <div class="stat">
            <span>Disposed Assets</span>
            <strong><?= $disposed_assets ?></strong>
        </div>
    </section>

    <section class="panel">
        <h2>
            <?= $report_type === 'assets'
                ? 'Asset Inventory Records'
                : 'Maintenance History Records' ?>
        </h2>

        <p class="note">
            Records displayed: <?= count($records) ?>
        </p>

        <div class="table-wrap">
            <table>
                <thead>

                <?php if ($report_type === 'assets'): ?>
                    <tr>
                        <th>No.</th>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Acquisition Date</th>
                        <th>Condition</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th>No.</th>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Issue Description</th>
                        <th>Maintenance Date</th>
                        <th>Status</th>
                        <th>Resolution</th>
                    </tr>
                <?php endif; ?>

                </thead>

                <tbody>
                <?php if (count($records) > 0): ?>

                    <?php foreach ($records as $index => $row): ?>

                        <?php if ($report_type === 'assets'): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['asset_code']) ?></td>
                                <td><?= e($row['asset_name']) ?></td>
                                <td><?= e($row['category']) ?></td>
                                <td><?= e($row['location'] ?: 'Not specified') ?></td>
                                <td>
                                    <?= !empty($row['acquisition_date'])
                                        ? e(date('d M Y', strtotime($row['acquisition_date'])))
                                        : 'Not recorded' ?>
                                </td>
                                <td>
                                    <span class="badge <?= e(strtolower(str_replace(' ', '-', $row['condition_status']))) ?>">
                                        <?= e($row['condition_status']) ?>
                                    </span>
                                </td>
                                <td><?= e($row['assigned_to'] ?: 'Unassigned') ?></td>
                                <td>
                                    <span class="badge <?= e(strtolower(str_replace(' ', '-', $row['status']))) ?>">
                                        <?= e($row['status']) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php else: ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['asset_code']) ?></td>
                                <td><?= e($row['asset_name']) ?></td>
                                <td><?= e($row['category']) ?></td>
                                <td><?= nl2br(e($row['issue_description'])) ?></td>
                                <td>
                                    <?= !empty($row['maintenance_date'])
                                        ? e(date('d M Y', strtotime($row['maintenance_date'])))
                                        : 'Not recorded' ?>
                                </td>
                                <td>
                                    <span class="badge <?= e(strtolower(str_replace(' ', '-', $row['status']))) ?>">
                                        <?= e($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= nl2br(e($row['resolution'] ?: 'Not recorded')) ?></td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="9" class="empty">
                            No records match the selected filters.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Condition Summary</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Condition</th>
                        <th>Number of Assets</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Good</td>
                        <td><?= $good_assets ?></td>
                    </tr>
                    <tr>
                        <td>Fair</td>
                        <td><?= $fair_assets ?></td>
                    </tr>
                    <tr>
                        <td>Needs Repair</td>
                        <td><?= $needs_repair ?></td>
                    </tr>
                    <tr>
                        <td>Damaged</td>
                        <td><?= $damaged_assets ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 style="margin-top:25px">Maintenance Summary</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Maintenance Status</th>
                        <th>Number of Records</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Pending</td>
                        <td><?= $pending_maintenance ?></td>
                    </tr>
                    <tr>
                        <td>In Progress</td>
                        <td><?= $progress_maintenance ?></td>
                    </tr>
                    <tr>
                        <td>Completed</td>
                        <td><?= $completed_maintenance ?></td>
                    </tr>
                    <tr>
                        <th>Total Maintenance Records</th>
                        <th><?= $total_maintenance ?></th>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="print-signatures">
        <div class="signature">Prepared by: Assets Officer</div>
        <div class="signature">Verified by: Church Secretary</div>
        <div class="signature">Approved by: Church Chairperson</div>
    </div>

</main>

<footer>
    &copy; <?= date('Y') ?> A.I.C Kalyet Church Management System
</footer>

</body>
</html>
