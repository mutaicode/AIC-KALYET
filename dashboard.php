
<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));
$username = $_SESSION['username'] ?? 'Church Member';

$roleTitles = [
    'admin' => 'Administrator',
    'chairperson' => 'Chairperson',
    'vice_chairperson' => 'Vice Chairperson',
    'secretary' => 'Secretary',
    'vice_secretary' => 'Vice Secretary',
    'treasurer' => 'Treasurer',
    'projects_officer' => 'Projects Officer',
    'assets_officer' => 'Assets Officer',
    'christian_education' => 'Christian Education',
    'youth_chairperson' => 'Youth Chairperson',
    'pastor' => 'Pastor',
    'chairlady' => 'Chairlady',
    'user' => 'Church Member'
];

$roleTitle = $roleTitles[$role] ?? ucfirst($role);

$canViewFinancials = in_array($role, [
    'admin', 'chairperson', 'secretary', 'treasurer'
], true);

/*
|--------------------------------------------------------------------------
| Database helper
|--------------------------------------------------------------------------
*/
function fetchRows($conn, $sql)
{
    try {
        $result = $conn->query($sql);

        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('Dashboard query error: ' . $e->getMessage());
    }

    return [];
}

function escape($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayDate($date)
{
    if (empty($date)) {
        return 'Date not set';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : escape($date);
}

/*
|--------------------------------------------------------------------------
| Published announcements
|--------------------------------------------------------------------------
*/
$announcements = fetchRows(
    $conn,
    "SELECT id, title, message, announcement_date, audience
     FROM announcements
     WHERE status = 'Published'
       AND announcement_date <= CURDATE()
     ORDER BY announcement_date DESC, id DESC
     LIMIT 5"
);

/*
|--------------------------------------------------------------------------
| Upcoming published events
|--------------------------------------------------------------------------
*/
$events = fetchRows(
    $conn,
    "SELECT id, event_name, description, event_date,
            start_time, end_time, venue, location
     FROM events
     WHERE status = 'Published'
       AND event_date >= CURDATE()
     ORDER BY event_date ASC, start_time ASC
     LIMIT 5"
);

/*
|--------------------------------------------------------------------------
| Published attendance summaries
|--------------------------------------------------------------------------
*/
$attendanceReports = fetchRows(
    $conn,
    "SELECT id, attendance_date, service_name, total_attendance,
            men_count, women_count, youth_count,
            children_count, visitors_count, notes
     FROM attendance_summaries
     WHERE status = 'Published'
     ORDER BY attendance_date DESC, id DESC
     LIMIT 5"
);

/*
|--------------------------------------------------------------------------
| Published service program
|--------------------------------------------------------------------------
*/
$serviceProgram = fetchRows(
    $conn,
    "SELECT id, service_name, program_day, start_time, end_time,
            program_order, activity_name, person_responsible, notes
     FROM service_program
     WHERE status = 'Published'
     ORDER BY program_day ASC, service_name ASC, program_order ASC"
);

/*
|--------------------------------------------------------------------------
| Published secretary's reports
| Only published meeting minutes appear here.
|--------------------------------------------------------------------------
*/
$secretaryReports = fetchRows(
    $conn,
    "SELECT id, meeting_title, meeting_date, venue, agenda,
            resolutions, recorded_by
     FROM meeting_minutes
     WHERE status = 'Published'
     ORDER BY meeting_date DESC, id DESC
     LIMIT 5"
);

/*
|--------------------------------------------------------------------------
| Total members
|--------------------------------------------------------------------------
*/
$memberRows = fetchRows(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'user' AND status = 'active'"
);

$totalMembers = (int)($memberRows[0]['total'] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Church Dashboard</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f6fb;
    color: #253247;
    line-height: 1.6;
}

a {
    color: inherit;
    text-decoration: none;
}

.topbar {
    background: #17365d;
    color: white;
    padding: 20px 5%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.brand h1 {
    margin: 0;
    font-size: 23px;
}

.brand p {
    margin: 3px 0 0;
    color: #d9e6f7;
    font-size: 13px;
}

.account {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.role-label {
    background: #285486;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 13px;
}

.logout {
    background: #ffffff;
    color: #17365d;
    padding: 8px 15px;
    border-radius: 7px;
    font-weight: bold;
}

.container {
    width: 92%;
    max-width: 1250px;
    margin: 28px auto;
}

.welcome {
    background: white;
    padding: 25px;
    border-radius: 14px;
    margin-bottom: 25px;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.welcome h2 {
    margin: 0 0 5px;
    color: #17365d;
}

.welcome p {
    margin: 0;
    color: #65748b;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 18px;
    margin-bottom: 30px;
}

.stat {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border-left: 5px solid #3274b9;
    box-shadow: 0 3px 12px rgba(0,0,0,.04);
}

.stat span {
    display: block;
    color: #68768a;
    font-size: 14px;
}

.stat strong {
    font-size: 25px;
    color: #17365d;
}

.section {
    margin-bottom: 30px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.section-heading h2 {
    margin: 0;
    color: #17365d;
    font-size: 21px;
}

.section-heading a {
    color: #2468a6;
    font-size: 14px;
    font-weight: bold;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e7edf5;
    box-shadow: 0 3px 12px rgba(0,0,0,.035);
    min-width: 0;
}

.card h3 {
    color: #234c7d;
    margin: 0 0 8px;
    font-size: 17px;
}

.card p {
    margin: 7px 0;
    overflow-wrap: anywhere;
}

.meta {
    color: #718096;
    font-size: 13px;
}

.empty {
    background: white;
    border: 1px dashed #cbd5e1;
    padding: 20px;
    border-radius: 12px;
    color: #718096;
}

.program-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.program-table th,
.program-table td {
    padding: 12px;
    border-bottom: 1px solid #e7edf5;
    text-align: left;
}

.program-table th {
    background: #e8eff8;
    color: #17365d;
}

.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.quick-links a {
    background: #17365d;
    color: white;
    padding: 11px 17px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
}

footer {
    text-align: center;
    padding: 25px;
    color: #718096;
    font-size: 13px;
}

@media (max-width: 600px) {
    .topbar {
        padding: 18px 4%;
    }

    .brand h1 {
        font-size: 20px;
    }

    .container {
        width: 94%;
        margin-top: 18px;
    }

    .welcome {
        padding: 18px;
    }

    .program-table {
        font-size: 13px;
    }

    .program-table th,
    .program-table td {
        padding: 8px;
    }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>

<header class="topbar">
    <div class="brand">
        <h1>AIC Kalyet Church</h1>
        <p>Church Management System</p>
    </div>

    <div class="account">
        <span>Hello, <?= escape($username); ?></span>
        <span class="role-label"><?= escape($roleTitle); ?></span>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<main class="container">

    <section class="welcome">
        <h2>Welcome to the Church Dashboard</h2>
        <p>
            Stay informed about church announcements, upcoming events,
            service programs, attendance, and published reports.
        </p>
    </section>

    <section class="stats">
        <div class="stat">
            <span>Active Members</span>
            <strong><?= number_format($totalMembers); ?></strong>
        </div>

        <div class="stat">
            <span>Published Announcements</span>
            <strong><?= count($announcements); ?></strong>
        </div>

        <div class="stat">
            <span>Upcoming Events</span>
            <strong><?= count($events); ?></strong>
        </div>

        <div class="stat">
            <span>Published Attendance Reports</span>
            <strong><?= count($attendanceReports); ?></strong>
        </div>
    </section>

    <!-- Announcements -->
    <section class="section">
        <div class="section-heading">
            <h2>📢 Church Announcements</h2>
            <a href="announcements.php">Manage Announcements →</a>
        </div>

        <?php if ($announcements): ?>
            <div class="cards">
                <?php foreach ($announcements as $item): ?>
                    <article class="card">
                        <h3><?= escape($item['title']); ?></h3>

                        <p><?= nl2br(escape(mb_strimwidth(
                            $item['message'], 0, 220, '...'
                        ))); ?></p>

                        <p class="meta">
                            Published for:
                            <?= escape($item['audience']); ?>
                        </p>

                        <p class="meta">
                            Date: <?= displayDate($item['announcement_date']); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">There are no published announcements to display.</div>
        <?php endif; ?>
    </section>

    <!-- Events -->
    <section class="section">
        <div class="section-heading">
            <h2>📅 Upcoming Church Events</h2>
        </div>

        <?php if ($events): ?>
            <div class="cards">
                <?php foreach ($events as $item): ?>
                    <article class="card">
                        <h3><?= escape($item['event_name']); ?></h3>

                        <p><?= nl2br(escape(mb_strimwidth(
                            $item['description'] ?? '', 0, 180, '...'
                        ))); ?></p>

                        <p>
                            <strong>Date:</strong>
                            <?= displayDate($item['event_date']); ?>
                        </p>

                        <?php if (!empty($item['start_time'])): ?>
                            <p>
                                <strong>Time:</strong>
                                <?= escape(date('g:i A', strtotime($item['start_time']))); ?>

                                <?php if (!empty($item['end_time'])): ?>
                                    – <?= escape(date('g:i A', strtotime($item['end_time']))); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <p>
                            <strong>Venue:</strong>
                            <?= escape($item['venue'] ?: $item['location'] ?: 'To be announced'); ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">There are no published upcoming events.</div>
        <?php endif; ?>
    </section>

    <!-- Regular service program -->
    <section class="section">
        <div class="section-heading">
            <h2>⛪ Regular Service Program</h2>
        </div>

        <?php if ($serviceProgram): ?>
            <div class="cards">
                <?php
                $lastService = '';

                foreach ($serviceProgram as $item):
                    $currentService = $item['program_day'] . ' - ' . $item['service_name'];

                    if ($currentService !== $lastService):
                        if ($lastService !== ''):
                            echo '</div>';
                        endif;
                        $lastService = $currentService;
                ?>
                    <div class="card">
                        <h3><?= escape($currentService); ?></h3>
                <?php endif; ?>

                        <p>
                            <strong><?= (int)$item['program_order']; ?>.</strong>
                            <?= escape($item['activity_name']); ?>
                        </p>

                        <?php if (!empty($item['start_time'])): ?>
                            <p class="meta">
                                <?= escape(date('g:i A', strtotime($item['start_time']))); ?>

                                <?php if (!empty($item['end_time'])): ?>
                                    – <?= escape(date('g:i A', strtotime($item['end_time']))); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($item['person_responsible'])): ?>
                            <p class="meta">
                                Responsible: <?= escape($item['person_responsible']); ?>
                            </p>
                        <?php endif; ?>

                <?php endforeach; ?>
                    </div>
            </div>
        <?php else: ?>
            <div class="empty">
                The regular service program has not been published yet.
            </div>
        <?php endif; ?>
    </section>

    <!-- Attendance -->
    <section class="section">
        <div class="section-heading">
            <h2>📊 Published Attendance Summaries</h2>
            <a href="attendance.php">Attendance Records →</a>
        </div>

        <?php if ($attendanceReports): ?>
            <div class="cards">
                <?php foreach ($attendanceReports as $item): ?>
                    <article class="card">
                        <h3><?= escape($item['service_name']); ?></h3>

                        <p class="meta">
                            <?= displayDate($item['attendance_date']); ?>
                        </p>

                        <p>
                            <strong>Total attendance:</strong>
                            <?= number_format((int)$item['total_attendance']); ?>
                        </p>

                        <p>
                            <strong>Men:</strong> <?= (int)$item['men_count']; ?> |
                            <strong>Women:</strong> <?= (int)$item['women_count']; ?>
                        </p>

                        <p>
                            <strong>Youth:</strong> <?= (int)$item['youth_count']; ?> |
                            <strong>Children:</strong> <?= (int)$item['children_count']; ?>
                        </p>

                        <p>
                            <strong>Visitors:</strong>
                            <?= (int)$item['visitors_count']; ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">There are no published attendance summaries.</div>
        <?php endif; ?>
    </section>

    <!-- Secretary's reports -->
    <section class="section">
        <div class="section-heading">
            <h2>📝 Published Secretary's Reports</h2>
        </div>

        <?php if ($secretaryReports): ?>
            <div class="cards">
                <?php foreach ($secretaryReports as $item): ?>
                    <article class="card">
                        <h3><?= escape($item['meeting_title']); ?></h3>

                        <p class="meta">
                            Meeting date: <?= displayDate($item['meeting_date']); ?>
                        </p>

                        <p>
                            <strong>Venue:</strong> <?= escape($item['venue']); ?>
                        </p>

                        <?php if (!empty($item['agenda'])): ?>
                            <p>
                                <strong>Agenda:</strong>
                                <?= nl2br(escape(mb_strimwidth(
                                    $item['agenda'], 0, 180, '...'
                                ))); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($item['resolutions'])): ?>
                            <p>
                                <strong>Resolutions:</strong>
                                <?= nl2br(escape(mb_strimwidth(
                                    $item['resolutions'], 0, 180, '...'
                                ))); ?>
                            </p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">There are no published secretary's reports.</div>
        <?php endif; ?>
    </section>

    <!-- Management links -->
    <section class="section">
        <div class="section-heading">
            <h2>Quick Links</h2>
        </div>

        <div class="quick-links">
            <a href="members.php">Members</a>
            <a href="departments.php">Departments</a>
            <a href="attendance.php">Attendance</a>
            <a href="announcements.php">Announcements</a>

            <?php if ($canViewFinancials): ?>
                <a href="financial_records.php">Financial Records</a>
            <?php endif; ?>
        </div>
    </section>

</main>

<footer>
    &copy; <?= date('Y'); ?> AIC Kalyet Church Management System
</footer>

</body>
</html>
