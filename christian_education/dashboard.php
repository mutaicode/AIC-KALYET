
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!in_array($role, ['christian_education', 'admin'], true)) {
    http_response_code(403);
    exit('Access denied. Only the Christian Education Leader or Admin can access this page.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)($row['total'] ?? 0);
}

$totalClasses = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM ce_classes"
);

$activeClasses = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM ce_classes
     WHERE status = 'Active'"
);

$totalLessons = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM ce_lessons"
);

$publishedLessons = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM ce_lessons
     WHERE status = 'Published'"
);

$upcomingLessons = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM ce_lessons
     WHERE lesson_date >= CURDATE()
       AND status = 'Published'"
);

$recentLessons = [];

$sql = "
    SELECT
        l.id,
        l.lesson_title,
        l.bible_reference,
        l.lesson_date,
        l.teacher_name,
        l.status,
        c.class_name
    FROM ce_lessons l
    LEFT JOIN ce_classes c ON c.id = l.class_id
    WHERE l.status <> 'Archived'
    ORDER BY l.lesson_date DESC, l.id DESC
    LIMIT 8
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentLessons[] = $row;
    }
}

$upcomingPrograms = [];

$sql = "
    SELECT
        l.id,
        l.lesson_title,
        l.bible_reference,
        l.lesson_date,
        l.teacher_name,
        c.class_name
    FROM ce_lessons l
    LEFT JOIN ce_classes c ON c.id = l.class_id
    WHERE l.status = 'Published'
      AND l.lesson_date >= CURDATE()
    ORDER BY l.lesson_date ASC, l.id ASC
    LIMIT 5
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $upcomingPrograms[] = $row;
    }
}

$username = $_SESSION['username'] ?? 'Christian Education Leader';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Christian Education Dashboard | A.I.C Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #123a66;
            color: white;
            padding: 24px 16px;
        }

        .brand {
            padding: 0 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,.2);
            margin-bottom: 20px;
        }

        .brand h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .brand p {
            margin: 0;
            color: #cbdcf0;
            font-size: 12px;
            line-height: 1.5;
        }

        .nav-label {
            margin: 22px 10px 8px;
            color: #b9cce3;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-link {
            display: block;
            color: #edf5ff;
            padding: 12px 13px;
            margin-bottom: 6px;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #24578d;
        }

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 22px 30px;
            background: white;
            box-shadow: 0 2px 8px rgba(15,23,42,.05);
        }

        .topbar h1 {
            margin: 0 0 7px;
            color: #123a66;
            font-size: 25px;
        }

        .topbar p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .user {
            padding: 10px 14px;
            background: #eaf1f8;
            border-radius: 8px;
            color: #123a66;
            font-size: 13px;
        }

        .content {
            padding: 28px;
        }

        .welcome {
            background: linear-gradient(120deg, #123a66, #2563a6);
            color: white;
            border-radius: 13px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .welcome h2 {
            margin: 0 0 10px;
            font-size: 23px;
        }

        .welcome p {
            margin: 0;
            line-height: 1.6;
            color: #e1edfa;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .stat {
            background: white;
            border-radius: 11px;
            padding: 21px;
            border-bottom: 3px solid #2563eb;
            box-shadow: 0 3px 12px rgba(15,23,42,.05);
        }

        .stat-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 13px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #123a66;
        }

        .stat-note {
            color: #64748b;
            font-size: 12px;
            margin-top: 8px;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .quick-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .quick-card h3 {
            margin: 0 0 9px;
            color: #123a66;
            font-size: 16px;
        }

        .quick-card p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            min-height: 40px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            background: #164e8a;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }

        .btn:hover {
            background: #0f3764;
        }

        .columns {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 20px;
        }

        .panel {
            background: white;
            border-radius: 11px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(15,23,42,.05);
            margin-bottom: 20px;
            min-width: 0;
        }

        .panel h2 {
            margin: 0 0 18px;
            font-size: 18px;
            color: #123a66;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
            white-space: normal;
        }

        th {
            color: #475569;
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 11px;
            background: #e2e8f0;
            color: #334155;
        }

        .badge.Published {
            background: #dcfce7;
            color: #166534;
        }

        .badge.Draft {
            background: #fef3c7;
            color: #92400e;
        }

        .program {
            padding: 14px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .program:last-child {
            border-bottom: 0;
        }

        .program h3 {
            font-size: 14px;
            color: #123a66;
            margin: 0 0 7px;
        }

        .program p {
            margin: 4px 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.5;
        }

        .date {
            display: inline-block;
            margin-bottom: 8px;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            color: #64748b;
            padding: 24px 10px;
            font-size: 13px;
        }

        @media (max-width: 1100px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 750px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 16px;
            }

            .brand {
                padding-bottom: 14px;
            }

            .nav-label {
                margin-top: 14px;
            }

            .topbar {
                padding: 20px;
            }

            .content {
                padding: 18px;
            }

            .quick-links {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <h2>A.I.C Kalyet</h2>
            <p>Christian Education Department</p>
        </div>

        <div class="nav-label">Main Menu</div>

        <a href="dashboard.php" class="nav-link active">
            Dashboard
        </a>

        <a href="classes.php" class="nav-link">
            Manage Classes
        </a>

        <a href="lessons.php" class="nav-link">
            Lessons & Bible Study
        </a>

        <a href="reports.php" class="nav-link">
            Education Reports
        </a>

        <div class="nav-label">Church</div>

        <a href="../dashboard.php" class="nav-link">
            Main Church Dashboard
        </a>
        <a href="../logout.php" class="nav-link">
            Logout
        </a>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Christian Education Dashboard</h1>
                <p>Manage classes, Bible lessons, and education programs.</p>
            </div>

            <div class="user">
                Welcome, <?= e($username); ?>
            </div>
        </header>

        <div class="content">

            <section class="welcome">
                <h2>Welcome to Christian Education</h2>
                <p>
                    Plan Bible lessons, coordinate teachers, organize
                    education classes, and keep records of church learning
                    activities in one place.
                </p>
            </section>

            <section class="stats">

                <div class="stat">
                    <div class="stat-label">Total Classes</div>
                    <div class="stat-number">
                        <?= number_format($totalClasses); ?>
                    </div>
                    <div class="stat-note">All registered classes</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Active Classes</div>
                    <div class="stat-number">
                        <?= number_format($activeClasses); ?>
                    </div>
                    <div class="stat-note">Currently active</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Total Lessons</div>
                    <div class="stat-number">
                        <?= number_format($totalLessons); ?>
                    </div>
                    <div class="stat-note">All recorded lessons</div>
                </div>

                <div class="stat">
                    <div class="stat-label">Upcoming Published Lessons</div>
                    <div class="stat-number">
                        <?= number_format($upcomingLessons); ?>
                    </div>
                    <div class="stat-note">Today and future dates</div>
                </div>

            </section>

            <section class="quick-links">

                <div class="quick-card">
                    <h3>Manage Classes</h3>
                    <p>
                        Register Sunday school classes, age groups,
                        teachers, and meeting schedules.
                    </p>
                    <a class="btn" href="classes.php">Open Classes</a>
                </div>

                <div class="quick-card">
                    <h3>Manage Lessons</h3>
                    <p>
                        Record lesson titles, Bible references,
                        teachers, dates, and notes.
                    </p>
                    <a class="btn" href="lessons.php">Open Lessons</a>
                </div>

                <div class="quick-card">
                    <h3>Education Reports</h3>
                    <p>
                        Review class information and prepare printable
                        education reports.
                    </p>
                    <a class="btn" href="reports.php">View Reports</a>
                </div>

            </section>

            <section class="columns">

                <div class="panel">
                    <h2>Recent Lessons</h2>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Lesson</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                            </thead>

                            <tbody>
                            <?php if (empty($recentLessons)): ?>
                                <tr>
                                    <td colspan="4" class="empty">
                                        No lessons have been recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLessons as $lesson): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <?= e($lesson['lesson_title']); ?>
                                            </strong>
                                            <br>
                                            <small>
                                                <?= e($lesson['bible_reference'] ?: 'No Bible reference'); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?= e($lesson['class_name'] ?: 'General'); ?>
                                        </td>

                                        <td>
                                            <?= e(date(
                                                'd M Y',
                                                strtotime($lesson['lesson_date'])
                                            )); ?>
                                        </td>

                                        <td>
                                            <span class="badge <?= e($lesson['status']); ?>">
                                                <?= e($lesson['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <h2>Upcoming Lessons</h2>

                    <?php if (empty($upcomingPrograms)): ?>
                        <div class="empty">
                            No upcoming published lessons.<br><br>
                            Add a lesson and publish it to display it here.
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingPrograms as $program): ?>
                            <div class="program">
                                <span class="date">
                                    <?= e(date(
                                        'D, d M Y',
                                        strtotime($program['lesson_date'])
                                    )); ?>
                                </span>

                                <h3><?= e($program['lesson_title']); ?></h3>

                                <p>
                                    <strong>Class:</strong>
                                    <?= e($program['class_name'] ?: 'General'); ?>
                                </p>

                                <p>
                                    <strong>Bible reference:</strong>
                                    <?= e($program['bible_reference'] ?: 'Not provided'); ?>
                                </p>

                                <p>
                                    <strong>Teacher:</strong>
                                    <?= e($program['teacher_name'] ?: 'Not assigned'); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </section>

            <div class="panel">
                <h2>Department Summary</h2>
                <p>
                    Published lessons:
                    <strong><?= number_format($publishedLessons); ?></strong>
                </p>
                <p>
                    Keep lesson records updated and publish lessons when
                    they are ready to be shared.
                </p>
            </div>

        </div>
    </main>
</div>
</body>
</html>
