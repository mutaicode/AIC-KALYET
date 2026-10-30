
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Require login.
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Only the Chairperson can open this dashboard.
if (strtolower(trim($_SESSION['role'])) !== 'chairperson') {
    http_response_code(403);
    exit('Access denied. This dashboard is restricted to the Chairperson.');
}

$username = $_SESSION['username'] ?? 'Chairperson';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chairperson Dashboard | AIC Kalyet</title>

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
            width: 255px;
            background: #102b4e;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
        }

        .brand {
            padding: 0 10px 25px;
            border-bottom: 1px solid rgba(255,255,255,.15);
            margin-bottom: 25px;
        }

        .brand h2 {
            font-size: 21px;
            line-height: 1.5;
        }

        .brand p {
            font-size: 12px;
            color: #b8d2e5;
            margin-top: 6px;
        }

        .menu-label {
            color: #9db4ce;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 0 10px;
            margin: 20px 0 10px;
        }

        .sidebar nav a {
            display: block;
            padding: 12px 13px;
            margin-bottom: 5px;
            color: #e5edf7;
            text-decoration: none;
            border-radius: 7px;
            font-size: 14px;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #176b65;
            color: white;
        }

        .main {
            flex: 1;
            min-width: 0;
        }

        .topbar {
            background: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .topbar h1 {
            font-size: 23px;
            color: #102b4e;
        }

        .topbar p {
            margin-top: 6px;
            color: #718096;
            font-size: 13px;
        }

        .logout {
            padding: 10px 15px;
            background: #fff0f0;
            color: #b42318;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold;
            font-size: 13px;
        }

        .content {
            padding: 30px;
            max-width: 1450px;
            margin: auto;
        }

        .welcome {
            background: linear-gradient(120deg, #12345b, #137b70);
            color: white;
            padding: 30px;
            border-radius: 14px;
            margin-bottom: 28px;
        }

        .welcome h2 {
            font-size: 25px;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #e1eeee;
            line-height: 1.7;
            max-width: 720px;
        }

        .section-title {
            font-size: 19px;
            color: #102b4e;
            margin: 28px 0 16px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .card {
            display: block;
            background: white;
            padding: 23px;
            border-radius: 12px;
            border: 1px solid #e5eaf0;
            border-top: 4px solid #176b65;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 12px rgba(16,43,78,.04);
            transition: transform .2s, box-shadow .2s;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(16,43,78,.09);
        }

        .card .icon {
            font-size: 27px;
            margin-bottom: 14px;
        }

        .card h3 {
            font-size: 16px;
            color: #12345b;
            margin-bottom: 9px;
        }

        .card p {
            color: #718096;
            font-size: 13px;
            line-height: 1.6;
        }

        .status-note {
            background: white;
            padding: 18px;
            margin-top: 25px;
            border-radius: 10px;
            border: 1px solid #e5eaf0;
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

            .brand {
                padding-bottom: 15px;
                margin-bottom: 15px;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar nav a {
                margin: 0;
            }

            .menu-label {
                margin-top: 15px;
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
            <div class="menu-label">Main Menu</div>
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="../dashboard.php">👤 General Dashboard</a>

            <div class="menu-label">Church Management</div>
            <a href="../members.php">👥 Church Members</a>
            <a href="../ministries.php">⛪ Ministries</a>
            <a href="../departments.php">🏢 Departments</a>
            <a href="../events.php">📅 Church Events</a>

            <div class="menu-label">Reports & Administration</div>
            <a href="../attendance.php">📋 Service Attendance</a>
            <a href="../financial_records.php">💰 Financial Records</a>
            <a href="../minutes.php">📝 Meeting Minutes</a>
            <a href="../announcements.php">📢 Announcements</a>
            <a href="projects_officer/projects.php">🛠️ Projects</a>
            <a href="assets_officer/assets.php">🏛️ Church Assets</a>

            <div class="menu-label">Account</div>
            <a href="../logout.php">🚪 Log Out</a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h1>Chairperson Dashboard</h1>
                <p>AIC Kalyet Church Management System</p>
            </div>

            <a href="../logout.php" class="logout">Log Out</a>
        </header>

        <main class="content">
            <section class="welcome">
                <h2>Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h2>
                <p>
                    This is your leadership overview. From here, you can access
                    church departments, financial reports, service attendance,
                    meeting minutes, announcements, projects and church assets.
                </p>
            </section>

            <h2 class="section-title">Church Overview</h2>

            <section class="cards">
                <a class="card" href="../members.php">
                    <div class="icon">👥</div>
                    <h3>Church Members</h3>
                    <p>Access church membership information.</p>
                </a>

                <a class="card" href="../attendance.php">
                    <div class="icon">📋</div>
                    <h3>Service Attendance</h3>
                    <p>Review service attendance, including male and female totals.</p>
                </a>

                <a class="card" href="../financial_records.php">
                    <div class="icon">💰</div>
                    <h3>Financial Reports</h3>
                    <p>Access church income, expenditure and financial summaries.</p>
                </a>

                <a class="card" href="../minutes.php">
                    <div class="icon">📝</div>
                    <h3>Meeting Minutes</h3>
                    <p>Access meeting records and resolutions.</p>
                </a>

                <a class="card" href="../announcements.php">
                    <div class="icon">📢</div>
                    <h3>Announcements</h3>
                    <p>Review church announcements and upcoming activities.</p>
                </a>

                <a class="card" href="projects_officer/project_reports.php">
                    <div class="icon">🛠️</div>
                    <h3>Church Projects</h3>
                    <p>Review ongoing projects and their progress.</p>
                </a>

                <a class="card" href="assets_officer/assets.php">
                    <div class="icon">🏛️</div>
                    <h3>Church Assets</h3>
                    <p>Review church property and asset records.</p>
                </a>

                <a class="card" href="../departments.php">
                    <div class="icon">🏢</div>
                    <h3>Department Reports</h3>
                    <p>Access reports from Christian Education, Youth and Women's departments.</p>
                </a>
            </section>

            <div class="status-note">
                <strong>Access level:</strong> Chairperson

                <br>
                Report links will work when their corresponding pages are created
                and protected with the correct permissions. This dashboard currently
                provides navigation and does not yet calculate live report totals.
            </div>
        </main>

        <footer>
            &copy; <?= date('Y') ?> AIC Kalyet Church Management System
        </footer>
    </div>
</div>
</body>
</html>
