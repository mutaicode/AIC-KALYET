
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

// Allow only Assets Officer and Admin
$allowed_roles = ['assets_officer', 'admin'];

if (!isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], $allowed_roles, true)) {
    http_response_code(403);
    exit("Access denied. Only the Assets Officer and Admin can access this page.");
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];
$message = '';
$error = '';

// Escape output safely
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = "Invalid security token. Refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $maintenance_id = (int)($_POST['maintenance_id'] ?? 0);
            $asset_id = (int)($_POST['asset_id'] ?? 0);
            $issue_description = trim($_POST['issue_description'] ?? '');
            $maintenance_date = trim($_POST['maintenance_date'] ?? '');
            $status = $_POST['status'] ?? 'Pending';
            $resolution = trim($_POST['resolution'] ?? '');
            $recorded_by = (int)$_SESSION['user_id'];

            $valid_statuses = ['Pending', 'In Progress', 'Completed'];

            if ($asset_id <= 0 || $issue_description === '') {
                $error = "Please select an asset and enter the issue description.";
            } elseif (!in_array($status, $valid_statuses, true)) {
                $error = "Please select a valid maintenance status.";
            } elseif (
                $maintenance_date !== '' &&
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $maintenance_date)
            ) {
                $error = "Please enter a valid maintenance date.";
            } else {
                // Confirm that the asset exists
                $check = $conn->prepare(
                    "SELECT id FROM assets WHERE id = ?"
                );
                $check->bind_param("i", $asset_id);
                $check->execute();
                $asset_exists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$asset_exists) {
                    $error = "The selected asset does not exist.";
                } else {
                    $date_value = $maintenance_date !== ''
                        ? $maintenance_date
                        : null;

                    $resolution_value = $resolution !== ''
                        ? $resolution
                        : null;

                    if ($maintenance_id > 0) {
                        // Update existing maintenance record
                        $stmt = $conn->prepare(
                            "UPDATE asset_maintenance
                             SET asset_id = ?,
                                 issue_description = ?,
                                 maintenance_date = ?,
                                 status = ?,
                                 resolution = ?
                             WHERE id = ?"
                        );

                        $stmt->bind_param(
                            "issssi",
                            $asset_id,
                            $issue_description,
                            $date_value,
                            $status,
                            $resolution_value,
                            $maintenance_id
                        );

                        if ($stmt->execute()) {
                            $message = "Maintenance record updated successfully.";
                        } else {
                            $error = "Unable to update maintenance record.";
                        }

                        $stmt->close();
                    } else {
                        // Create new maintenance record
                        $stmt = $conn->prepare(
                            "INSERT INTO asset_maintenance
                             (asset_id, issue_description, maintenance_date,
                              status, resolution, recorded_by)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        $stmt->bind_param(
                            "issssi",
                            $asset_id,
                            $issue_description,
                            $date_value,
                            $status,
                            $resolution_value,
                            $recorded_by
                        );

                        if ($stmt->execute()) {
                            $message = "Maintenance issue recorded successfully.";
                        } else {
                            $error = "Unable to save maintenance record.";
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Load record for editing
$edit_record = null;

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    $stmt = $conn->prepare(
        "SELECT * FROM asset_maintenance WHERE id = ?"
    );
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Load assets for dropdown
$assets_result = $conn->query(
    "SELECT id, asset_code, asset_name
     FROM assets
     WHERE status != 'Disposed'
     ORDER BY asset_name ASC"
);

if (!$assets_result) {
    die("Unable to load assets. Please check the assets table.");
}

// Search and filter
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';

$valid_statuses = ['Pending', 'In Progress', 'Completed'];

$sql = "SELECT m.*, a.asset_code, a.asset_name
        FROM asset_maintenance m
        INNER JOIN assets a ON m.asset_id = a.id
        WHERE 1=1";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (
        a.asset_code LIKE ?
        OR a.asset_name LIKE ?
        OR m.issue_description LIKE ?
    )";

    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'sss';
}

if (in_array($filter_status, $valid_statuses, true)) {
    $sql .= " AND m.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$sql .= " ORDER BY
          CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END,
          m.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$maintenance_result = $stmt->get_result();

// Dashboard counts
$count_result = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'In Progress') AS in_progress,
        SUM(status = 'Completed') AS completed
     FROM asset_maintenance"
);

$counts = $count_result->fetch_assoc();

$total_count = (int)($counts['total'] ?? 0);
$pending_count = (int)($counts['pending'] ?? 0);
$progress_count = (int)($counts['in_progress'] ?? 0);
$completed_count = (int)($counts['completed'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asset Maintenance | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #263449;
        }

        .topbar {
            background: #12345a;
            color: white;
            padding: 22px 5%;
        }

        .topbar h1 {
            font-size: 25px;
            margin-bottom: 7px;
        }

        .topbar p {
            color: #d6e5f7;
            font-size: 14px;
        }

        .navigation {
            background: #ffffff;
            padding: 15px 5%;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            border-bottom: 1px solid #e1e7ef;
        }

        .navigation a {
            text-decoration: none;
            color: #174b83;
            font-weight: bold;
            font-size: 14px;
        }

        .container {
            width: 92%;
            max-width: 1400px;
            margin: 25px auto;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 12px #152b4610;
            border-left: 4px solid #2463a5;
        }

        .stat-card h3 {
            color: #65758b;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-card strong {
            font-size: 28px;
            color: #15395f;
        }

        .panel {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 3px 12px #152b4610;
            margin-bottom: 25px;
        }

        .panel h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #163e68;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            font-size: 13px;
            color: #34465c;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cdd6e2;
            border-radius: 6px;
            font: inherit;
            font-size: 14px;
            background: #fff;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid #c8ddf7;
            border-color: #2463a5;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 6px;
            padding: 11px 16px;
            font-weight: bold;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1e5b96;
            color: white;
        }

        .btn-secondary {
            background: #e6edf5;
            color: #24415f;
        }

        .btn-small {
            padding: 7px 10px;
            background: #e6edf8;
            color: #174b83;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #e3f6e9;
            color: #176534;
        }

        .error {
            background: #fde8e7;
            color: #9b2424;
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 12px;
            margin-bottom: 20px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e6ebf1;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            background: #f3f6fa;
            color: #34465c;
        }

        .status {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .pending {
            background: #fff0d1;
            color: #8a5b00;
        }

        .in-progress {
            background: #dceeff;
            color: #17518a;
        }

        .completed {
            background: #dff5e5;
            color: #176534;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #718096;
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
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 550px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .panel {
                padding: 16px;
            }

            .topbar h1 {
                font-size: 21px;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<header class="topbar">
    <h1>Asset Maintenance</h1>
    <p>A.I.C Kalyet Church Management System</p>
</header>

<nav class="navigation">
    <a href="dashboard.php">Dashboard</a>
    <a href="assets.php">Asset Register</a>
    <a href="maintenance.php">Maintenance</a>
    <a href="asset_reports.php">Asset Reports</a>
    <a href="../dashboard.php">Main Dashboard</a>
    <a href="../logout.php">Logout</a>
</nav>

<main class="container">

    <?php if ($message !== ''): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <h3>Total Maintenance Records</h3>
            <strong><?= $total_count ?></strong>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>
            <strong><?= $pending_count ?></strong>
        </div>

        <div class="stat-card">
            <h3>In Progress</h3>
            <strong><?= $progress_count ?></strong>
        </div>

        <div class="stat-card">
            <h3>Completed</h3>
            <strong><?= $completed_count ?></strong>
        </div>
    </section>

    <section class="panel">
        <h2>
            <?= $edit_record ? 'Edit Maintenance Record' : 'Record a Maintenance Issue' ?>
        </h2>

        <form method="POST" action="maintenance.php">
            <input type="hidden" name="csrf_token"
                   value="<?= e($csrf_token) ?>">
            <input type="hidden" name="action" value="save">

            <input type="hidden" name="maintenance_id"
                   value="<?= (int)($edit_record['id'] ?? 0) ?>">

            <div class="form-grid">

                <div class="field">
                    <label for="asset_id">Select Asset *</label>

                    <select name="asset_id" id="asset_id" required>
                        <option value="">-- Select Asset --</option>

                        <?php while ($asset = $assets_result->fetch_assoc()): ?>
                            <option
                                value="<?= (int)$asset['id'] ?>"
                                <?= (int)($edit_record['asset_id'] ?? 0)
                                    === (int)$asset['id'] ? 'selected' : '' ?>>
                                <?= e($asset['asset_code']) ?> -
                                <?= e($asset['asset_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="maintenance_date">Maintenance Date</label>
                    <input type="date"
                           name="maintenance_date"
                           id="maintenance_date"
                           value="<?= e($edit_record['maintenance_date'] ?? date('Y-m-d')) ?>">
                </div>

                <div class="field full">
                    <label for="issue_description">Issue Description *</label>
                    <textarea name="issue_description"
                              id="issue_description"
                              required
                              placeholder="Describe the fault or maintenance required..."><?= e($edit_record['issue_description'] ?? '') ?></textarea>
                </div>

                <div class="field">
                    <label for="status">Maintenance Status</label>
                    <select name="status" id="status" required>
                        <?php
                        $selected_status = $edit_record['status'] ?? 'Pending';
                        foreach (['Pending', 'In Progress', 'Completed'] as $option):
                        ?>
                            <option value="<?= e($option) ?>"
                                <?= $selected_status === $option ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field full">
                    <label for="resolution">Resolution / Work Done</label>
                    <textarea name="resolution"
                              id="resolution"
                              placeholder="Describe the repair or action taken..."><?= e($edit_record['resolution'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $edit_record ? 'Update Maintenance Record' : 'Save Maintenance Issue' ?>
                </button>

                <?php if ($edit_record): ?>
                    <a href="maintenance.php" class="btn btn-secondary">
                        Cancel Editing
                    </a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">
                        Clear Form
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Maintenance History</h2>

        <form method="GET" action="maintenance.php" class="filters">
            <input type="text"
                   name="search"
                   placeholder="Search asset code, asset name or issue..."
                   value="<?= e($search) ?>">

            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach ($valid_statuses as $option): ?>
                    <option value="<?= e($option) ?>"
                        <?= $filter_status === $option ? 'selected' : '' ?>>
                        <?= e($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-primary">
                Search / Filter
            </button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Issue Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Resolution</th>
                        <th>Recorded On</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($maintenance_result->num_rows > 0): ?>

                    <?php while ($row = $maintenance_result->fetch_assoc()): ?>
                        <?php
                        $status_class = strtolower(
                            str_replace(' ', '-', $row['status'])
                        );
                        ?>

                        <tr>
                            <td>
                                <strong><?= e($row['asset_name']) ?></strong><br>
                                <small><?= e($row['asset_code']) ?></small>
                            </td>

                            <td><?= nl2br(e($row['issue_description'])) ?></td>

                            <td>
                                <?= $row['maintenance_date']
                                    ? e(date('d M Y', strtotime($row['maintenance_date'])))
                                    : 'Not set' ?>
                            </td>

                            <td>
                                <span class="status <?= e($status_class) ?>">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= $row['resolution'] !== ''
                                    ? nl2br(e($row['resolution']))
                                    : 'Not recorded' ?>
                            </td>

                            <td>
                                <?= e(date('d M Y', strtotime($row['created_at']))) ?>
                            </td>

                            <td>
                                <a class="btn btn-small"
                                   href="maintenance.php?edit=<?= (int)$row['id'] ?>">
                                    Edit
                                </a>
                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">
                            No maintenance records found. Record an issue using the form above.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<footer>
    &copy; <?= date('Y') ?> A.I.C Kalyet Church Management System
    | Assets Management
</footer>

</body>
</html>

<?php
$stmt->close();
?>
