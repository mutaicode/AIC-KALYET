
<?php
session_start();
require_once '../config/database.php';

// Only active pastor accounts and administrators may enter.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pastor'
) {
    header("Location: ../login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pastor';
$isAdmin = $_SESSION['role'] === 'admin';

// Sermon statistics
$draftCount = 0;
$publishedCount = 0;
$totalSermons = 0;
$recentSermons = [];
$upcomingSermons = [];
$sermonError = false;

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Draft'), 0) AS drafts,
        COALESCE(SUM(status = 'Published'), 0) AS published
    FROM sermons
    WHERE preacher_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    $totalSermons = (int)$stats['total'];
    $draftCount = (int)$stats['drafts'];
    $publishedCount = (int)$stats['published'];
    $stmt->close();
} else {
    $sermonError = true;
}

// Recent sermons belonging to the signed-in pastor
$stmt = $conn->prepare("
    SELECT id, title, sermon_date, scripture_reference, status
    FROM sermons
    WHERE preacher_id = ?
    ORDER BY sermon_date DESC, id DESC
    LIMIT 5
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentSermons[] = $row;
    }

    $stmt->close();
} else {
    $sermonError = true;
}

// Upcoming sermons
$stmt = $conn->prepare("
    SELECT id, title, sermon_date, scripture_reference
    FROM sermons
    WHERE preacher_id = ?
      AND sermon_date >= CURDATE()
    ORDER BY sermon_date ASC
    LIMIT 5
");

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $upcomingSermons[] = $row;
    }

    $stmt->close();
} else {
    $sermonError = true;
}

// Private notes count: only this author's notes
$notesCount = 0;
$notesStmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM pastoral_notes
    WHERE author_id = ?
");

if ($notesStmt) {
    $notesStmt->bind_param("i", $userId);
    $notesStmt->execute();
    $notesCount = (int)$notesStmt->get_result()
        ->fetch_assoc()['total'];
    $notesStmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pastor Dashboard | AIC Kalyet Church</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 255px;
    padding: 24px 16px;
    background: #102b4e;
    color: white;
    overflow-y: auto;
}

.brand {
    padding: 0 10px 23px;
    border-bottom: 1px solid #36516e;
}

.brand h2 {
    margin: 0 0 8px;
    font-size: 21px;
}

.brand p {
    margin: 0;
    color: #cbd5e1;
    font-size: 13px;
}

.nav-title {
    margin: 24px 10px 10px;
    color: #94a3b8;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.sidebar a {
    display: block;
    padding: 12px;
    margin: 5px 0;
    border-radius: 7px;
    color: #e2e8f0;
    text-decoration: none;
    font-size: 14px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #176b65;
    color: white;
}

.sidebar a.logout {
    margin-top: 22px;
    background: #7f1d1d;
}

.main {
    margin-left: 255px;
    padding: 26px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.topbar h1 {
    margin: 0 0 7px;
    font-size: 26px;
}

.topbar p {
    margin: 0;
    color: #64748b;
}

.date {
    padding: 11px 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
}

.welcome {
    padding: 28px;
    margin-bottom: 24px;
    border-radius: 13px;
    color: white;
    background: linear-gradient(120deg, #12345b, #176b65);
}

.welcome h2 {
    margin: 0 0 10px;
    font-size: 24px;
}

.welcome p {
    margin: 0;
    max-width: 700px;
    line-height: 1.7;
    color: #e2f5f1;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    padding: 20px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.stat-card span {
    color: #64748b;
    font-size: 13px;
}

.stat-card h3 {
    margin: 12px 0 5px;
    font-size: 28px;
}

.stat-card small {
    color: #64748b;
}

.section {
    padding: 22px;
    margin-bottom: 24px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 11px;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 17px;
}

.section-heading h2 {
    margin: 0;
    font-size: 19px;
}

.actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.action-card {
    display: block;
    padding: 19px;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
    color: #1e293b;
    text-decoration: none;
    transition: 0.2s;
}

.action-card:hover {
    border-color: #176b65;
    transform: translateY(-2px);
}

.action-card h3 {
    margin: 0 0 9px;
    font-size: 16px;
}

.action-card p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 550px;
    border-collapse: collapse;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.draft {
    background: #fef3c7;
    color: #92400e;
}

.published {
    background: #dcfce7;
    color: #166534;
}

.empty {
    padding: 22px;
    text-align: center;
    color: #64748b;
}

.btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 6px;
    background: #176b65;
    color: white;
    text-decoration: none;
    font-size: 13px;
}

.notice {
    padding: 14px;
    margin-bottom: 20px;
    border-radius: 7px;
    background: #fff7ed;
    color: #9a3412;
}

footer {
    padding: 20px 0;
    color: #64748b;
    text-align: center;
    font-size: 12px;
}

@media (max-width: 1050px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        padding: 18px;
    }

    .main {
        margin-left: 0;
        padding: 16px;
    }

    .stats, .actions {
        grid-template-columns: 1fr;
    }

    .topbar h1 {
        font-size: 22px;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

<aside class="sidebar">
    <div class="brand">
        <h2>AIC Kalyet</h2>
        <p>Pastoral Ministry</p>
    </div>

    <div class="nav-title">Pastor Menu</div>

    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="sermons.php">Sermons</a>
    <a href="pastoral_notes.php">Private Pastoral Notes</a>

    <div class="nav-title">Church Information</div>

    <a href="../members.php">Church Members</a>
    <a href="../minutes.php">Meeting Minutes</a>
    <a href="../announcements.php">Announcements</a>
    <a href="../attendance.php">Attendance Records</a>

    <div class="nav-title">Account</div>
    <a href="../dashboard.php">Main Dashboard</a>
    <a href="../logout.php" class="logout">Logout</a>
</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>Pastor Dashboard</h1>
            <p>Welcome, <?= e($username) ?>. Here is your ministry overview.</p>
        </div>

        <div class="date"><?= date('l, d F Y') ?></div>
    </header>

    <section class="welcome">
        <h2>Pastoral Ministry</h2>
        <p>
            Prepare sermons, organize ministry resources, and maintain
            confidential pastoral notes in one place.
        </p>
    </section>

    <?php if ($sermonError): ?>
        <div class="notice">
            Some sermon statistics could not be loaded. Please check that
            the sermons table and its columns match your database.
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <span>Total Sermons</span>
            <h3><?= number_format($totalSermons) ?></h3>
            <small>Your sermon records</small>
        </div>

        <div class="stat-card">
            <span>Draft Sermons</span>
            <h3><?= number_format($draftCount) ?></h3>
            <small>Still being prepared</small>
        </div>

        <div class="stat-card">
            <span>Published Sermons</span>
            <h3><?= number_format($publishedCount) ?></h3>
            <small>Ready to share</small>
        </div>

        <div class="stat-card">
            <span>Private Notes</span>
            <h3><?= number_format($notesCount) ?></h3>
            <small>Your pastoral notes</small>
        </div>

    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Quick Actions</h2>
        </div>

        <div class="actions">

            <a href="sermons.php" class="action-card">
                <h3>Manage Sermons</h3>
                <p>Create sermon drafts, add Bible references and manage publication.</p>
            </a>

            <a href="pastoral_notes.php" class="action-card">
                <h3>Private Pastoral Notes</h3>
                <p>Record counseling, follow-up, prayer and ministry notes privately.</p>
            </a>

            <a href="../members.php" class="action-card">
                <h3>Church Members</h3>
                <p>Access the church membership directory when following up with members.</p>
            </a>

            <a href="../minutes.php" class="action-card">
                <h3>Meeting Minutes</h3>
                <p>Review authorized church meeting records.</p>
            </a>

            <a href="../announcements.php" class="action-card">
                <h3>Announcements</h3>
                <p>Review church announcements and ministry information.</p>
            </a>

            <a href="../attendance.php" class="action-card">
                <h3>Attendance</h3>
                <p>Review recorded attendance summaries for church services.</p>
            </a>

        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Recent Sermons</h2>
            <a href="sermons.php" class="btn">Manage Sermons</a>
        </div>

        <?php if (empty($recentSermons)): ?>

            <p class="empty">
                You have not recorded any sermons yet.
                Use Manage Sermons to create your first sermon.
            </p>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sermon Title</th>
                            <th>Date</th>
                            <th>Scripture Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentSermons as $sermon): ?>
                        <tr>
                            <td><?= e($sermon['title']) ?></td>
                            <td><?= e(date(
                                'd M Y',
                                strtotime($sermon['sermon_date'])
                            )) ?></td>
                            <td><?= e($sermon['scripture_reference'] ?? '—') ?></td>
                            <td>
                                <span class="status <?= $sermon['status'] === 'Published'
                                    ? 'published' : 'draft' ?>">
                                    <?= e($sermon['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Upcoming Sermons</h2>
        </div>

        <?php if (empty($upcomingSermons)): ?>

            <p class="empty">
                No upcoming sermons have been scheduled.
            </p>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Scheduled Date</th>
                            <th>Scripture</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($upcomingSermons as $sermon): ?>
                        <tr>
                            <td><?= e($sermon['title']) ?></td>
                            <td><?= e(date(
                                'd M Y',
                                strtotime($sermon['sermon_date'])
                            )) ?></td>
                            <td><?= e($sermon['scripture_reference'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

    <footer>
        AIC Kalyet Church Management System &copy; <?= date('Y') ?>
    </footer>

</main>

</body>
</html>
