
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Require login.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$role = strtolower(trim($_SESSION['role']));

// Allow the Secretary and Chairperson.
if (!in_array($role, ['secretary', 'chairperson'], true)) {
    http_response_code(403);
    exit('Access denied. This page is restricted to authorized church officials.');
}

$username = $_SESSION['username'] ?? 'Secretary';
$isChairperson = ($role === 'chairperson');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secretary Dashboard | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #243247;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #102b4e;
            color: white;
            padding: 26px 17px;
        }

        .brand {
            padding: 0 10px 22px;
            border-bottom: 1px solid rgba(255,255,255,.15);
            margin-bottom: 22px;
        }

        .brand h2 {
            font-size: 21px;
            line-height: 1.5;
        }

        .brand p {
            color: #b8d2e5;
            font-size: 12px;
            margin-top: 5px;
        }

        .menu-label {
            color: #9db4ce;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 10px 9px;
        }

        nav a {
            display: block;
            color: #e5edf7;
            text-decoration: none;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 5px;
            font-size: 14px;
        }

        nav a:hover,
        nav a.active {
            background: #176b65;
            color: white;
        }

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            background: white;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .topbar h1 {
            color: #102b4e;
            font-size: 23px;
        }

        .topbar p {
            color: #718096;
            margin-top: 6px;
            font-size: 13px;
        }

        .logout {
            color: #b42318;
            background: #fff0f0;
            text-decoration: none;
            padding: 10px 14px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
        }

        .content {
            max-width: 1300px;
            padding: 28px;
            margin: auto;
        }

        .welcome {
            background: linear-gradient(120deg, #12345b, #137b70);
            color: white;
            padding: 28px;
            border-radius: 13px;
            margin-bottom: 28px;
        }

        .welcome h2 {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #e1eeee;
            line-height: 1.7;
        }

        .section-title {
            color: #102b4e;
            font-size: 19px;
            margin: 25px 0 16px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .card {
            background: white;
            padding: 23px;
            border-radius: 11px;
            border: 1px solid #e5eaf0;
            border-top: 4px solid #176b65;
            box-shadow: 0 4px 12px rgba(16,43,78,.04);
        }

        .card .icon {
            font-size: 28px;
            margin-bottom: 14px;
        }

        .card h3 {
            color: #12345b;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .card p {
            color: #718096;
            font-size: 13px;
            line-height: 1.7;
        }

        .card a {
            display: inline-block;
            margin-top: 15px;
            color: #137b70;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .notice {
            background: white;
            border: 1px solid #e5eaf0;
            border-radius: 10px;
            padding: 18px;
            margin-top: 25px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.7;
        }

        footer {
            text-align: center;
            padding: 22px;
            color: #718096;
            font-size: 12px;
        }

        @media (max-width: 800px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 18px;
            }

            nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            nav a {
                margin: 0;
            }

            .content {
                padding: 20px 15px;
            }

            .topbar {
                padding: 18px;
            }
        }
    </style>
</head>

<body>
<?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <h2>AIC Kalyet</h2>
            <p>Church Management System</p>
        </div>

        <nav>
            <div class="menu-label">Secretary's Office</div>
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="../minutes.php">📝 Meeting Minutes</a>
            <a href="../announcements.php">📢 Announcements</a>
            <a href="../events.php">📅 Meetings & Events</a>

            <div class="menu-label">Church Information</div>
            <a href="../members.php">👥 Church Members</a>
            <a href="../departments.php">🏢 Departments</a>
            <a href="../attendance.php">📋 Service Attendance</a>

            <?php if ($isChairperson): ?>
                <div class="menu-label">Chairperson</div>
                <a href="../chairperson/dashboard.php">📊 Chairperson Dashboard</a>
                <a href="../financial_records.php">💰 Financial Records</a>
                <a href="../projects.php">🛠️ Church Projects</a>
                <a href="../assets.php">🏛️ Church Assets</a>
            <?php endif; ?>

            <div class="menu-label">Account</div>
            <a href="../logout.php">🚪 Log Out</a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h1>Secretary's Dashboard</h1>
                <p>Minutes, announcements and church communication</p>
            </div>

            <a href="../logout.php" class="logout">Log Out</a>
        </header>

        <main class="content">
            <section class="welcome">
                <h2>Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h2>
                <p>
                    Manage church meeting minutes, resolutions and announcements.
                    Keep church communication organized and make important
                    information available to the appropriate people.
                </p>
            </section>

            <h2 class="section-title">Secretary's Responsibilities</h2>

            <section class="cards">
                <article class="card">
                    <div class="icon">📝</div>
                    <h3>Meeting Minutes</h3>
                    <p>
                        Record meeting discussions, attendees, decisions
                        and resolutions. Prepare minutes for printing.
                    </p>
                    <a href="../minutes.php">Open Minutes →</a>
                </article>

                <article class="card">
                    <div class="icon">📢</div>
                    <h3>Announcements</h3>
                    <p>
                        Prepare and manage church announcements,
                        notices and important updates.
                    </p>
                    <a href="../announcements.php">Manage Announcements →</a>
                </article>

                <article class="card">
                    <div class="icon">📅</div>
                    <h3>Meetings & Events</h3>
                    <p>
                        Review church meetings, services and upcoming
                        events that require communication.
                    </p>
                    <a href="../events.php">View Events →</a>
                </article>

                <article class="card">
                    <div class="icon">👥</div>
                    <h3>Church Members</h3>
                    <p>
                        Access membership information when needed
                        for meeting records and official communication.
                    </p>
                    <a href="../members.php">View Members →</a>
                </article>
            </section>

            <div class="notice">
                <strong>Important:</strong>
                This dashboard provides navigation to the Secretary's
                responsibilities. The linked management pages must be created
                and secured separately. Confidential minutes should only be
                available to authorized officials.

                <?php if ($isChairperson): ?>
                    <br><br>
                    <strong>Chairperson access:</strong>
                    You are viewing this page with the Chairperson role.
                <?php endif; ?>
            </div>
        </main>

        <footer>
            &copy; <?= date('Y') ?> AIC Kalyet Church Management System
        </footer>
    </div>
</div>
</body>
</html>
