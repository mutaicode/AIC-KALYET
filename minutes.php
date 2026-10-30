
<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Authorized roles
$allowedRoles = [
    'admin',
    'chairperson',
    'secretary',
    'vice_secretary',
    'pastor'
];

if (
    !isset($_SESSION['user_id']) ||
    !in_array(strtolower(trim($_SESSION['role'] ?? '')), $allowedRoles, true)
) {
    http_response_code(403);
    exit('Access denied. You are not authorized to access meeting minutes.');
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Save meeting minutes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Security check failed. Refresh the page and try again.';
    } else {
        $title = trim($_POST['meeting_title'] ?? '');
        $date = trim($_POST['meeting_date'] ?? '');
        $venue = trim($_POST['venue'] ?? '');
        $attendees = trim($_POST['attendees'] ?? '');
        $agenda = trim($_POST['agenda'] ?? '');
        $discussions = trim($_POST['discussions'] ?? '');
        $resolutions = trim($_POST['resolutions'] ?? '');

        $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
        $validDate = $dateObject && $dateObject->format('Y-m-d') === $date;

        if ($title === '' || $date === '' || $venue === '' || $discussions === '') {
            $error = 'Please complete the meeting title, date, venue, and discussions.';
        } elseif (!$validDate) {
            $error = 'Please enter a valid meeting date.';
        } else {
            $recordedBy = (int) $_SESSION['user_id'];

            $stmt = $conn->prepare(
                "INSERT INTO meeting_minutes
                (meeting_title, meeting_date, venue, attendees, agenda,
                 discussions, resolutions, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                'sssssssi',
                $title,
                $date,
                $venue,
                $attendees,
                $agenda,
                $discussions,
                $resolutions,
                $recordedBy
            );

            if ($stmt->execute()) {
                $message = 'Meeting minutes saved successfully.';
            } else {
                $error = 'Could not save the minutes. Please try again.';
            }

            $stmt->close();
        }
    }
}

// Search saved minutes
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT m.id, m.meeting_title, m.meeting_date, m.venue,
                m.created_at, u.username AS recorder
         FROM meeting_minutes m
         LEFT JOIN users u ON m.recorded_by = u.id
         WHERE m.meeting_title LIKE ?
            OR m.venue LIKE ?
         ORDER BY m.meeting_date DESC, m.id DESC"
    );

    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query(
        "SELECT m.id, m.meeting_title, m.meeting_date, m.venue,
                m.created_at, u.username AS recorder
         FROM meeting_minutes m
         LEFT JOIN users u ON m.recorded_by = u.id
         ORDER BY m.meeting_date DESC, m.id DESC"
    );
}

if (!$result) {
    die('Unable to load meeting minutes. Please check the database.');
}

$countResult = $conn->query("SELECT COUNT(*) AS total FROM meeting_minutes");
$totalMinutes = (int) $countResult->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Meeting Minutes | AIC Kalyet</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

header {
    background: #123b2a;
    color: white;
    padding: 25px 30px;
}

header h1 { margin: 0 0 8px; }
header p { margin: 0; color: #d1fae5; }

.container {
    max-width: 1250px;
    margin: 28px auto;
    padding: 0 20px;
}

.nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 22px;
}

.nav a {
    background: #123b2a;
    color: white;
    padding: 11px 15px;
    border-radius: 6px;
    text-decoration: none;
}

.panel {
    background: white;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 24px;
    box-shadow: 0 3px 12px #00000008;
}

.panel h2 { margin-top: 0; }

.notice {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 18px;
    background: #dcfce7;
    color: #166534;
}

.error { background: #fee2e2; color: #991b1b; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.field.full { grid-column: 1 / -1; }

label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 7px;
}

input, textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font: inherit;
}

textarea { resize: vertical; }

button, .print-link {
    display: inline-block;
    padding: 11px 16px;
    background: #166534;
    color: white;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
}

.search {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.search input { flex: 1; }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    min-width: 750px;
    border-collapse: collapse;
}

th, td {
    text-align: left;
    padding: 13px;
    border-bottom: 1px solid #e2e8f0;
}

th { background: #e2e8f0; }

@media(max-width: 700px) {
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .container { padding: 0 10px; }
    header { padding: 20px; }
    .search { flex-direction: column; }
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/includes/public_home_link.php'; ?>
<header>
    <h1>Meeting Minutes</h1>
    <p>AIC Kalyet Church Management System</p>
</header>

<div class="container">
    <nav class="nav">
        <a href="secretary/dashboard.php">Secretary Dashboard</a>
        <a href="chairperson/dashboard.php">Chairperson Dashboard</a>
        <a href="dashboard.php">Main Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>

    <?php if ($message !== ''): ?>
        <div class="notice"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Record New Meeting Minutes</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="meeting_title">Meeting Title *</label>
                    <input id="meeting_title" name="meeting_title"
                        maxlength="200" required>
                </div>

                <div class="field">
                    <label for="meeting_date">Meeting Date *</label>
                    <input id="meeting_date" type="date"
                        name="meeting_date" required>
                </div>

                <div class="field full">
                    <label for="venue">Venue *</label>
                    <input id="venue" name="venue" maxlength="200" required>
                </div>

                <div class="field full">
                    <label for="attendees">Attendees</label>
                    <textarea id="attendees" name="attendees"
                        rows="3" placeholder="Enter names of attendees"></textarea>
                </div>

                <div class="field full">
                    <label for="agenda">Agenda</label>
                    <textarea id="agenda" name="agenda" rows="3"></textarea>
                </div>

                <div class="field full">
                    <label for="discussions">Discussions / Proceedings *</label>
                    <textarea id="discussions" name="discussions"
                        rows="6" required></textarea>
                </div>

                <div class="field full">
                    <label for="resolutions">Resolutions / Action Points</label>
                    <textarea id="resolutions" name="resolutions"
                        rows="4"></textarea>
                </div>
            </div>

            <p>
                <button type="submit">Save Meeting Minutes</button>
            </p>
        </form>
    </section>

    <section class="panel">
        <h2>Saved Meeting Minutes (<?= $totalMinutes ?>)</h2>

        <form method="GET" class="search">
            <input type="search" name="search"
                value="<?= e($search) ?>"
                placeholder="Search by meeting title or venue">
            <button type="submit">Search</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Meeting</th>
                        <th>Venue</th>
                        <th>Recorded By</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="5">No meeting minutes found.</td></tr>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($row['meeting_date']) ?></td>
                            <td><?= e($row['meeting_title']) ?></td>
                            <td><?= e($row['venue']) ?></td>
                            <td><?= e($row['recorder'] ?? 'Unknown') ?></td>
                            <td>
                                <a class="print-link"
                                    href="minutes_print.php?id=<?= (int) $row['id'] ?>">
                                    View / Print
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</body>
</html>
