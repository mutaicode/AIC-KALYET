
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Require login
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));

// Only the assets officer and administrator can access this dashboard
if (!in_array($role, ['assets_officer', 'admin'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Assets Officer';

// Escape output safely
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Get dashboard statistics
function getCount($conn, $sql)
{
    try {
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['total'];
        }
    } catch (Throwable $e) {
        error_log('Assets dashboard: ' . $e->getMessage());
    }

    return 0;
}

$totalAssets = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM assets"
);

$activeAssets = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM assets WHERE status = 'Active'"
);

$repairAssets = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM assets
     WHERE condition_status IN ('Needs Repair', 'Damaged')
       AND status != 'Disposed'"
);

$maintenancePending = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM asset_maintenance
     WHERE status IN ('Pending', 'In Progress')"
);

$disposedAssets = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM assets WHERE status = 'Disposed'"
);

// Recent assets
$recentAssets = [];

try {
    $result = $conn->query(
        "SELECT id, asset_code, asset_name, category,
                location, condition_status, status
         FROM assets
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );

    if ($result) {
        $recentAssets = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Recent assets: ' . $e->getMessage());
}

// Recent maintenance issues
$maintenanceItems = [];

try {
    $result = $conn->query(
        "SELECT m.id, m.issue_description, m.maintenance_date,
                m.status, a.asset_name, a.asset_code
         FROM asset_maintenance m
         INNER JOIN assets a ON a.id = m.asset_id
         WHERE m.status != 'Completed'
         ORDER BY m.created_at DESC
         LIMIT 6"
    );

    if ($result) {
        $maintenanceItems = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Maintenance list: ' . $e->getMessage());
}

function displayDate($date)
{
    if (!$date) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date('d M Y', $timestamp) : $date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Assets Officer Dashboard | AIC Kalyet</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f6fb;
    color: #263449;
}

a {
    text-decoration: none;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 245px;
    height: 100vh;
    padding: 25px 16px;
    background: #17365d;
    color: white;
    overflow-y: auto;
}

.brand {
    padding: 0 10px 25px;
    border-bottom: 1px solid #365477;
}

.brand h2 {
    font-size: 21px;
    margin: 0 0 5px;
}

.brand p {
    color: #c9d8eb;
    font-size: 12px;
    margin: 0;
}

.nav-title {
    margin: 25px 10px 10px;
    color: #aebfd5;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.nav-link {
    display: block;
    padding: 12px 13px;
    margin-bottom: 6px;
    border-radius: 7px;
    color: #e9f0f9;
    font-size: 14px;
}

.nav-link:hover,
.nav-link.active {
    background: #2b5686;
    color: white;
}

.main {
    margin-left: 245px;
    padding: 28px;
}

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.topbar h1 {
    margin: 0;
    color: #17365d;
    font-size: 27px;
}

.topbar p {
    margin: 5px 0 0;
    color: #748196;
}

.profile {
    background: white;
    border: 1px solid #e3e9f1;
    border-radius: 9px;
    padding: 11px 15px;
}

.profile strong {
    color: #17365d;
}

.profile span {
    display: block;
    color: #7b8798;
    font-size: 12px;
}

.welcome {
    padding: 24px;
    border-radius: 13px;
    color: white;
    background: linear-gradient(120deg, #17365d, #3479b8);
    margin-bottom: 25px;
}

.welcome h2 {
    margin: 0 0 6px;
}

.welcome p {
    margin: 0;
    color: #e0ebf8;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 17px;
    margin-bottom: 30px;
}

.stat {
    background: white;
    padding: 20px;
    border-radius: 11px;
    border: 1px solid #e7edf5;
    box-shadow: 0 3px 10px rgba(0,0,0,.03);
}

.stat-label {
    color: #718096;
    font-size: 13px;
}

.stat-number {
    display: block;
    font-size: 29px;
    font-weight: bold;
    color: #17365d;
    margin-top: 7px;
}

.stat-note {
    color: #8995a6;
    font-size: 12px;
}

.section {
    background: white;
    border: 1px solid #e5ebf3;
    border-radius: 12px;
    padding: 22px;
    margin-bottom: 25px;
    box-shadow: 0 3px 10px rgba(0,0,0,.025);
}

.section h2 {
    color: #17365d;
    font-size: 19px;
    margin: 0 0 18px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    text-align: left;
    padding: 13px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}

th {
    background: #f4f7fb;
    color: #53657d;
}

td {
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    background: #edf2f7;
    color: #43546b;
    font-size: 11px;
    font-weight: bold;
}

.status.good,
.status.active,
.status.completed {
    background: #dcfce7;
    color: #166534;
}

.status.fair,
.status.pending,
.status.maintenance {
    background: #fef3c7;
    color: #92400e;
}

.status.damaged,
.status.repair,
.status.disposed {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    padding: 22px;
    text-align: center;
    color: #78869a;
    background: #f8fafc;
    border-radius: 8px;
}

.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 15px;
}

.quick-link {
    display: block;
    border: 1px solid #dce5f0;
    border-radius: 10px;
    padding: 18px;
    color: #17365d;
    transition: .2s;
}

.quick-link:hover {
    border-color: #3274b9;
    background: #f4f8fd;
    transform: translateY(-2px);
}

.quick-link strong {
    display: block;
    margin-bottom: 5px;
}

.quick-link span {
    font-size: 13px;
    color: #748196;
}

.logout {
    display: block;
    margin-top: 25px;
    background: #294968;
}

footer {
    padding: 10px;
    text-align: center;
    color: #8290a3;
    font-size: 12px;
}

@media (max-width: 850px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .topbar h1 {
        font-size: 23px;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<aside class="sidebar">
    <div class="brand">
        <h2>AIC Kalyet</h2>
        <p>Church Management System</p>
    </div>

    <p class="nav-title">Assets Management</p>

    <a class="nav-link active" href="dashboard.php">
        🏠 Dashboard
    </a>

    <a class="nav-link" href="assets.php">
        📦 Asset Register
    </a>

    <a class="nav-link" href="maintenance.php">
        🔧 Maintenance & Repairs
    </a>

    <a class="nav-link" href="asset_reports.php">
        📄 Asset Reports
    </a>

    <p class="nav-title">Church</p>

    <a class="nav-link" href="../dashboard.php">
        ⛪ Main Dashboard
    </a>
    <a class="nav-link logout" href="../logout.php">
        🚪 Logout
    </a>
</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>Assets Officer Dashboard</h1>
            <p>Manage and monitor church property and equipment.</p>
        </div>

        <div class="profile">
            <strong><?= e($username); ?></strong>
            <span>Assets Officer</span>
        </div>
    </header>

    <section class="welcome">
        <h2>Welcome, <?= e($username); ?>!</h2>
        <p>
            Keep the church's assets properly recorded, maintained,
            and accounted for.
        </p>
    </section>

    <section class="stats">
        <div class="stat">
            <span class="stat-label">Total Registered Assets</span>
            <strong class="stat-number"><?= number_format($totalAssets); ?></strong>
            <span class="stat-note">All registered property and equipment</span>
        </div>

        <div class="stat">
            <span class="stat-label">Active Assets</span>
            <strong class="stat-number"><?= number_format($activeAssets); ?></strong>
            <span class="stat-note">Assets currently marked active</span>
        </div>

        <div class="stat">
            <span class="stat-label">Need Attention</span>
            <strong class="stat-number"><?= number_format($repairAssets); ?></strong>
            <span class="stat-note">Damaged or needing repair</span>
        </div>

        <div class="stat">
            <span class="stat-label">Open Maintenance Issues</span>
            <strong class="stat-number"><?= number_format($maintenancePending); ?></strong>
            <span class="stat-note">Pending or in progress</span>
        </div>

        <div class="stat">
            <span class="stat-label">Disposed Assets</span>
            <strong class="stat-number"><?= number_format($disposedAssets); ?></strong>
            <span class="stat-note">Marked as disposed</span>
        </div>
    </section>

    <section class="section">
        <h2>📦 Recently Registered Assets</h2>

        <?php if ($recentAssets): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Condition</th>
                        <th>Status</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentAssets as $asset): ?>
                        <?php
                        $conditionClass = match ($asset['condition_status']) {
                            'Good' => 'good',
                            'Fair' => 'fair',
                            'Needs Repair' => 'repair',
                            'Damaged' => 'damaged',
                            default => ''
                        };

                        $assetStatusClass = match ($asset['status']) {
                            'Active' => 'active',
                            'Under Maintenance' => 'maintenance',
                            'Disposed' => 'disposed',
                            default => ''
                        };
                        ?>
                        <tr>
                            <td><?= e($asset['asset_code']); ?></td>
                            <td><?= e($asset['asset_name']); ?></td>
                            <td><?= e($asset['category']); ?></td>
                            <td><?= e($asset['location'] ?: 'Not specified'); ?></td>
                            <td>
                                <span class="status <?= e($conditionClass); ?>">
                                    <?= e($asset['condition_status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status <?= e($assetStatusClass); ?>">
                                    <?= e($asset['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                No assets have been registered yet.
                Use the Asset Register to add church property and equipment.
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>🔧 Outstanding Maintenance and Repairs</h2>

        <?php if ($maintenanceItems): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Asset Code</th>
                        <th>Issue</th>
                        <th>Maintenance Date</th>
                        <th>Status</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($maintenanceItems as $item): ?>
                        <?php
                        $maintenanceClass = match ($item['status']) {
                            'Pending' => 'pending',
                            'In Progress' => 'maintenance',
                            'Completed' => 'completed',
                            default => ''
                        };
                        ?>
                        <tr>
                            <td><?= e($item['asset_name']); ?></td>
                            <td><?= e($item['asset_code']); ?></td>
                            <td><?= e($item['issue_description']); ?></td>
                            <td><?= e(displayDate($item['maintenance_date'])); ?></td>
                            <td>
                                <span class="status <?= e($maintenanceClass); ?>">
                                    <?= e($item['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                There are no outstanding maintenance issues.
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>⚡ Quick Actions</h2>

        <div class="quick-links">
            <a class="quick-link" href="assets.php">
                <strong>➕ Register New Asset</strong>
                <span>Add church equipment or property to the register.</span>
            </a>

            <a class="quick-link" href="maintenance.php">
                <strong>🔧 Record a Repair</strong>
                <span>Record damaged assets and maintenance work.</span>
            </a>

            <a class="quick-link" href="asset_reports.php">
                <strong>🖨️ Asset Reports</strong>
                <span>View and print the church asset inventory.</span>
            </a>

            <a class="quick-link" href="../dashboard.php">
                <strong>🏠 Main Dashboard</strong>
                <span>Return to the main church homepage.</span>
            </a>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y'); ?> AIC Kalyet Church Management System
    </footer>

</main>

</body>
</html>
