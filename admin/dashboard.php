
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Only logged-in administrators can access this page
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strtolower(trim($_SESSION['role'])) !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

// Count registered users
$totalUsers = 0;
$pendingUsers = 0;
$activeUsers = 0;
$inactiveUsers = 0;

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($result) {
    $totalUsers = (int) $result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM users
     GROUP BY status"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = strtolower(trim($row['status']));
        $count = (int) $row['total'];

        if ($status === 'pending') {
            $pendingUsers = $count;
        } elseif ($status === 'active') {
            $activeUsers = $count;
        } else {
            $inactiveUsers += $count;
        }
    }
}

$systemCounts = [
    'members' => 0,
    'attendance' => 0,
    'announcements' => 0,
    'events' => 0,
    'minutes' => 0,
    'memories' => 0,
];

$countQueries = [
    'members' => "SELECT COUNT(*) AS total FROM members",
    'attendance' => "SELECT COUNT(*) AS total FROM attendance_summaries",
    'announcements' => "SELECT COUNT(*) AS total FROM announcements WHERE status = 'Published'",
    'events' => "SELECT COUNT(*) AS total FROM events WHERE status = 'Published'",
    'minutes' => "SELECT COUNT(*) AS total FROM meeting_minutes WHERE status = 'Published'",
    'memories' => "SELECT COUNT(*) AS total FROM church_memories",
];

foreach ($countQueries as $key => $query) {
    $result = $conn->query($query);
    if ($result) {
        $systemCounts[$key] = (int) $result->fetch_assoc()['total'];
    } else {
        error_log('Admin dashboard query failed for ' . $key . ': ' . $conn->error);
    }
}

$pendingMinutes = 0;
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM meeting_minutes
     WHERE status = 'Draft'"
);
if ($result) {
    $pendingMinutes = (int) $result->fetch_assoc()['total'];
}

$recentUsers = [];
$result = $conn->query(
    "SELECT username, email, role, status, created_at
     FROM users
     WHERE role <> 'admin'
     ORDER BY created_at DESC, id DESC
     LIMIT 6"
);
if ($result) {
    $recentUsers = $result->fetch_all(MYSQLI_ASSOC);
}

$adminName = htmlspecialchars(
    $_SESSION['username'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: #123b2a;
            color: white;
            padding: 25px 18px;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 12px;
            color: #cbd5e1;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 13px;
            margin-bottom: 8px;
            border-radius: 7px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #256b4b;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            font-size: 27px;
            margin-bottom: 7px;
        }

        .topbar p {
            color: #64748b;
        }

        .logout {
            background: #dc2626;
            color: white;
            text-decoration: none;
            padding: 11px 17px;
            border-radius: 7px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 23px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
        }

        .card p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .card h2 {
            font-size: 32px;
        }

        .card small {
            display: block;
            color: #64748b;
            margin-top: 8px;
        }

        .card.warning {
            border-left: 4px solid #f59e0b;
        }

        .card.success {
            border-left: 4px solid #16a34a;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        }

        .section h2 {
            font-size: 20px;
            margin-bottom: 12px;
        }

        .section p {
            color: #64748b;
            line-height: 1.7;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .action {
            display: block;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 18px;
            text-decoration: none;
            color: #123b2a;
        }

        .action:hover {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .action strong {
            display: block;
            margin-bottom: 7px;
        }

        .action span {
            color: #64748b;
            font-size: 13px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .section-heading h2 {
            margin-bottom: 0;
        }

        .section-heading a {
            color: #166534;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        th,
        td {
            padding: 13px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 14px;
        }

        th {
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .status {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.active {
            background: #dcfce7;
            color: #166534;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            color: #64748b;
            text-align: center;
            padding: 25px;
        }

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .subtitle {
                margin-bottom: 15px;
            }

            .main {
                margin-left: 0;
                padding: 18px;
            }

            .cards,
            .actions {
                grid-template-columns: 1fr;
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
    <div class="brand">AIC KALYET</div>
    <div class="subtitle">Church Management System</div>

    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="users.php">User Management</a>
    <a href="roles.php">Role Assignment</a>
    <a href="../dashboard.php">Main Dashboard</a>
    <a href="../memories.php">Church Memories</a>
    <a href="../logout.php">Logout</a>
</aside>

<main class="main">
    <header class="topbar">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Welcome, <?php echo $adminName; ?>. Manage system access here.</p>
        </div>

        <a class="logout" href="../logout.php">Logout</a>
    </header>

    <section class="cards">
        <div class="card">
            <p>Total Registered Users</p>
            <h2><?php echo $totalUsers; ?></h2>
        </div>

        <div class="card">
            <p>Pending Approval</p>
            <h2><?php echo $pendingUsers; ?></h2>
        </div>

        <div class="card">
            <p>Active Accounts</p>
            <h2><?php echo $activeUsers; ?></h2>
        </div>

        <div class="card">
            <p>Inactive Accounts</p>
            <h2><?php echo $inactiveUsers; ?></h2>
        </div>

        <div class="card">
            <p>Church Members</p>
            <h2><?php echo $systemCounts['members']; ?></h2>
            <small>Member records</small>
        </div>

        <div class="card">
            <p>Attendance Records</p>
            <h2><?php echo $systemCounts['attendance']; ?></h2>
            <small>Recorded summaries</small>
        </div>

        <div class="card success">
            <p>Published Content</p>
            <h2><?php echo $systemCounts['announcements'] + $systemCounts['events'] + $systemCounts['minutes']; ?></h2>
            <small>Announcements, events and reports</small>
        </div>

        <div class="card">
            <p>Church Memories</p>
            <h2><?php echo $systemCounts['memories']; ?></h2>
            <small>Uploaded photos</small>
        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <h2>System Administration</h2>
            <?php if ($pendingUsers > 0 || $pendingMinutes > 0): ?>
                <span class="status pending">
                    <?php echo $pendingUsers + $pendingMinutes; ?> items need attention
                </span>
            <?php endif; ?>
        </div>
        <p>
            Manage registration approvals, activate or deactivate accounts,
            and assign official church roles. Only authorized administrators
            should change user permissions.
        </p>

        <div class="actions">
            <a class="action" href="users.php">
                <strong>User Management</strong>
                <span>Review registrations and manage account status.</span>
            </a>

            <a class="action" href="roles.php">
                <strong>Role Assignment</strong>
                <span>Assign approved users to church leadership roles.</span>
            </a>
            <a class="action" href="../memories.php">
                <strong>Church Memories</strong>
                <span>Review uploaded memories and delete inappropriate photos.</span>
            </a>
            <a class="action" href="../announcements.php">
                <strong>Announcements</strong>
                <span>Publish and manage church announcements.</span>
            </a>
            <a class="action" href="../events.php">
                <strong>Events</strong>
                <span>Manage upcoming church events and publication status.</span>
            </a>
        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Recent Registrations</h2>
            <a href="users.php">View all users</a>
        </div>
        <?php if ($recentUsers === []): ?>
            <div class="empty">No non-admin registrations found.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentUsers as $user): ?>
                        <?php $status = strtolower(trim($user['status'] ?? '')); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['role'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <span class="status <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($user['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
