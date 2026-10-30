<?php
require_once __DIR__ . '/config/bootstrap.php';

require_role(['admin', 'chairperson', 'secretary','treasurer']);

$role = strtolower(trim($_SESSION['role']));
$canRecord = in_array($role, ['admin', 'treasurer'], true);
$userId = (int) $_SESSION['user_id'];

$message = '';
$error = '';

// Flash messages from prior request
if ($flash = flash_get('attendance')) {
    if ($flash['type'] === 'error') {
        $error = $flash['message'];
    } else {
        $message = $flash['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canRecord) {
        http_response_code(403);
        exit('You are not authorized to record attendance.');
    }

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $date     = $_POST['attendance_date'] ?? '';
        $service  = trim($_POST['service_name'] ?? '');
        $men      = filter_var($_POST['men_count'] ?? null, FILTER_VALIDATE_INT);
        $women    = filter_var($_POST['women_count'] ?? null, FILTER_VALIDATE_INT);
        $youth    = filter_var($_POST['youth_count'] ?? null, FILTER_VALIDATE_INT);
        $children = filter_var($_POST['children_count'] ?? null, FILTER_VALIDATE_INT);
        $visitors = filter_var($_POST['visitors_count'] ?? null, FILTER_VALIDATE_INT);
        $notes    = trim($_POST['notes'] ?? '');
        $status   = in_array(($_POST['status'] ?? 'Draft'), ['Draft', 'Published'], true)
            ? $_POST['status'] : 'Draft';

        $validDate = DateTime::createFromFormat('!Y-m-d', $date);
        $dateIsValid = $validDate && $validDate->format('Y-m-d') === $date;

        $counts = [$men, $women, $youth, $children, $visitors];
        $countsValid = true;
        foreach ($counts as $c) {
            if ($c === false || $c === null || $c < 0) {
                $countsValid = false;
                break;
            }
        }

        if (!$dateIsValid || $service === '' || !$countsValid) {
            $error = 'Enter a valid date, service name, and non-negative whole numbers for all attendance categories.';
        } else {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO attendance_summaries
                    (attendance_date, service_name, men_count, women_count,
                     youth_count, children_count, visitors_count, notes, status, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        men_count = VALUES(men_count),
                        women_count = VALUES(women_count),
                        youth_count = VALUES(youth_count),
                        children_count = VALUES(children_count),
                        visitors_count = VALUES(visitors_count),
                        notes = VALUES(notes),
                        status = VALUES(status),
                        recorded_by = VALUES(recorded_by)"
                );

                if (!$stmt) {
                    throw new RuntimeException('Could not prepare the attendance query: ' . $conn->error);
                }

                $stmt->bind_param(
                    'ssiiiiissi',
                    $date,
                    $service,
                    $men,
                    $women,
                    $youth,
                    $children,
                    $visitors,
                    $notes,
                    $status,
                    $userId
                );

                if (!$stmt->execute()) {
                    throw new RuntimeException('Could not save attendance: ' . $stmt->error);
                }

                $stmt->close();
                flash_set('attendance', 'Attendance saved successfully.');
                header('Location: attendance.php');
                exit;
            } catch (Throwable $ex) {
                app_log('attendance save', $ex);
                $error = 'Could not save attendance. Please check the database and try again.';
            }
        }
    }
}

$searchDate = trim($_GET['date'] ?? '');

try {
    if ($searchDate !== '') {
        $stmt = $conn->prepare(
            "SELECT a.*, u.username
             FROM attendance_summaries a
             LEFT JOIN users u ON a.recorded_by = u.id
             WHERE a.attendance_date = ?
             ORDER BY a.attendance_date DESC, a.service_name ASC"
        );
        $stmt->bind_param('s', $searchDate);
    } else {
        $stmt = $conn->prepare(
            "SELECT a.*, u.username
             FROM attendance_summaries a
             LEFT JOIN users u ON a.recorded_by = u.id
             ORDER BY a.attendance_date DESC, a.service_name ASC"
        );
    }
    $stmt->execute();
    $records = $stmt->get_result();

    $summaryResult = $conn->query(
        "SELECT
            COALESCE(SUM(men_count), 0) AS men,
            COALESCE(SUM(women_count), 0) AS women,
            COALESCE(SUM(youth_count), 0) AS youth,
            COALESCE(SUM(children_count), 0) AS children,
            COALESCE(SUM(visitors_count), 0) AS visitors,
            COALESCE(SUM(total_attendance), 0) AS total
         FROM attendance_summaries"
    );
    $summary = $summaryResult->fetch_assoc();
} catch (Throwable $ex) {
    app_log('attendance list', $ex);
    $summary = ['men' => 0, 'women' => 0, 'youth' => 0, 'children' => 0, 'visitors' => 0, 'total' => 0];
    $records = null;
    $error = $error ?: 'Unable to load attendance records.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Church Attendance | AIC Kalyet</title>
<style>
* { box-sizing: border-box; }
body { margin: 0; padding: 24px; font-family: Arial, sans-serif; background: #f3f6fb; color: #263247; }
.container { max-width: 1200px; margin: auto; }
.header { background: #17365d; color: white; padding: 25px; border-radius: 12px; margin-bottom: 22px; }
.header h1 { margin-top: 0; margin-bottom: 8px; }
.nav { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
.nav a { color: white; text-decoration: none; padding: 9px 12px; border-radius: 6px; background: rgba(255,255,255,.14); }
.card { background: white; padding: 22px; border-radius: 12px; margin-bottom: 22px; box-shadow: 0 3px 14px rgba(20,40,70,.06); }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
label { display: block; font-weight: bold; margin-bottom: 7px; }
input, select, textarea { width: 100%; padding: 11px; border: 1px solid #ccd5e0; border-radius: 7px; font: inherit; }
textarea { min-height: 90px; resize: vertical; }
.full { grid-column: 1 / -1; }
button, .button { display: inline-block; padding: 10px 15px; background: #176b4d; color: white; border: 0; border-radius: 7px; text-decoration: none; cursor: pointer; font: inherit; }
.secondary { background: #17365d; }
.alert { padding: 13px; border-radius: 7px; margin-bottom: 18px; }
.success { background: #e4f7ec; color: #176b4d; }
.error { background: #fdeaea; color: #a32626; }
.summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.stat { padding: 16px; background: #f4f7fb; border-radius: 9px; }
.stat span { display: block; color: #65738a; font-size: 13px; }
.stat strong { display: block; font-size: 24px; margin-top: 8px; }
.filter { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
.filter input { max-width: 230px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e4e9f0; white-space: nowrap; }
th { background: #f5f7fa; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
.badge.Draft { background: #fff0cf; color: #805600; }
.badge.Published { background: #d9f5e5; color: #176b4d; }
@media (max-width: 800px) { .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 600px) { body { padding: 12px; } .grid { grid-template-columns: 1fr; } .full { grid-column: auto; } .card { padding: 15px; } }
@media print {
    body { background: white; padding: 0; }
    .no-print, .form-card { display: none !important; }
    .card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
    .container { max-width: none; }
    th, td { white-space: normal; font-size: 10px; }
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>
<div class="container">

<header class="header">
    <h1>AIC Kalyet Church</h1>
    <p>Church Attendance Management</p>
    <nav class="nav no-print">
        <?php if ($role === 'admin'): ?>
            <a href="admin/dashboard.php">Admin Dashboard</a>
        <?php elseif ($role === 'chairperson'): ?>
            <a href="chairperson/dashboard.php">Chairperson Dashboard</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<?php if ($message): ?>
    <div class="alert success no-print"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert error no-print"><?= e($error) ?></div>
<?php endif; ?>

<section class="card">
    <h2>Attendance Summary — All Recorded Services</h2>
    <div class="summary">
        <div class="stat"><span>Men</span><strong><?= number_format((int)$summary['men']) ?></strong></div>
        <div class="stat"><span>Women</span><strong><?= number_format((int)$summary['women']) ?></strong></div>
        <div class="stat"><span>Youth</span><strong><?= number_format((int)$summary['youth']) ?></strong></div>
        <div class="stat"><span>Children</span><strong><?= number_format((int)$summary['children']) ?></strong></div>
        <div class="stat"><span>Visitors</span><strong><?= number_format((int)$summary['visitors']) ?></strong></div>
        <div class="stat"><span>Total Attendance</span><strong><?= number_format((int)$summary['total']) ?></strong></div>
    </div>
</section>

<?php if ($canRecord): ?>
<section class="card form-card">
    <h2>Record Service Attendance</h2>
    <form method="POST" action="attendance.php">
        <?= csrf_field() ?>
        <div class="grid">
            <div>
                <label for="attendance_date">Service Date</label>
                <input type="date" id="attendance_date" name="attendance_date" required>
            </div>
            <div>
                <label for="service_name">Service</label>
                <select id="service_name" name="service_name" required>
                    <option value="">Select service</option>
                    <option value="Sunday Service">Sunday Service</option>
                    <option value="Midweek Service">Midweek Service</option>
                    <option value="Prayer Meeting">Prayer Meeting</option>
                    <option value="Youth Service">Youth Service</option>
                    <option value="Special Service">Special Service</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label for="men_count">Men</label>
                <input type="number" id="men_count" name="men_count" min="0" value="0" required>
            </div>
            <div>
                <label for="women_count">Women</label>
                <input type="number" id="women_count" name="women_count" min="0" value="0" required>
            </div>
            <div>
                <label for="youth_count">Youth</label>
                <input type="number" id="youth_count" name="youth_count" min="0" value="0" required>
            </div>
            <div>
                <label for="children_count">Children</label>
                <input type="number" id="children_count" name="children_count" min="0" value="0" required>
            </div>
            <div>
                <label for="visitors_count">Visitors</label>
                <input type="number" id="visitors_count" name="visitors_count" min="0" value="0" required>
            </div>
            <div>
                <label for="status">Visibility</label>
                <select id="status" name="status">
                    <option value="Draft">Draft (internal only)</option>
                    <option value="Published">Publish to dashboard</option>
                </select>
            </div>
            <div class="full">
                <label for="notes">Notes (Optional)</label>
                <textarea id="notes" name="notes" placeholder="Add relevant attendance notes..."></textarea>
            </div>
            <div class="full">
                <button type="submit">Save Attendance</button>
            </div>
        </div>
    </form>
    <p><small>Saving the same service on the same date updates its existing summary.</small></p>
</section>
<?php endif; ?>

<section class="card">
    <h2>Attendance Records</h2>
    <form method="GET" action="attendance.php" class="filter no-print">
        <input type="date" name="date" value="<?= e($searchDate) ?>" aria-label="Filter by date">
        <button type="submit" class="secondary">Filter Date</button>
        <a href="attendance.php" class="button secondary">Clear Filter</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th><th>Service</th><th>Men</th><th>Women</th>
                <th>Youth</th><th>Children</th><th>Visitors</th>
                <th>Total</th><th>Status</th><th>Recorded By</th><th>Notes</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($records && $records->num_rows > 0): ?>
                <?php while ($row = $records->fetch_assoc()): ?>
                    <tr>
                        <td><?= e($row['attendance_date']) ?></td>
                        <td><?= e($row['service_name']) ?></td>
                        <td><?= number_format((int)$row['men_count']) ?></td>
                        <td><?= number_format((int)$row['women_count']) ?></td>
                        <td><?= number_format((int)$row['youth_count']) ?></td>
                        <td><?= number_format((int)$row['children_count']) ?></td>
                        <td><?= number_format((int)$row['visitors_count']) ?></td>
                        <td><strong><?= number_format((int)$row['total_attendance']) ?></strong></td>
                        <td><span class="badge <?= e($row['status'] ?? 'Draft') ?>"><?= e($row['status'] ?? 'Draft') ?></span></td>
                        <td><?= e($row['username'] ?? 'Unknown') ?></td>
                        <td><?= e($row['notes'] ?? '') ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="11">No attendance records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

</div>
</body>
</html>