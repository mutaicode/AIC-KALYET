
<?php
session_start();
require_once '../config/database.php';

// Treasurer-only dashboard
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'treasurer'
) {
    header('Location: ../login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['username'] ?? 'Treasurer';

$totalIncome = 0;
$totalExpenses = 0;
$transactionCount = 0;
$netBalance = 0;
$recentTransactions = [];
$financialTableExists = false;
$attendanceTableExists = false;
$attendanceCount = 0;

// Check whether the financial records table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'financial_records'");

if ($tableCheck && $tableCheck->num_rows > 0) {
    $financialTableExists = true;

    $summary = $conn->query("
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN LOWER(transaction_type) = 'income'
                    THEN amount ELSE 0
                END
            ), 0) AS total_income,

            COALESCE(SUM(
                CASE
                    WHEN LOWER(transaction_type) = 'expense'
                    THEN amount ELSE 0
                END
            ), 0) AS total_expenses,

            COUNT(*) AS transaction_count

        FROM financial_records
    ");

    if ($summary) {
        $data = $summary->fetch_assoc();

        $totalIncome = (float) $data['total_income'];
        $totalExpenses = (float) $data['total_expenses'];
        $transactionCount = (int) $data['transaction_count'];
        $netBalance = $totalIncome - $totalExpenses;
    }

    $recent = $conn->query("
        SELECT id, transaction_date, transaction_type,
               category, description, amount
        FROM financial_records
        ORDER BY transaction_date DESC, id DESC
        LIMIT 5
    ");

    if ($recent) {
        while ($row = $recent->fetch_assoc()) {
            $recentTransactions[] = $row;
        }
    }
}

// Check whether attendance summaries exist
$attendanceCheck = $conn->query(
    "SHOW TABLES LIKE 'attendance_summaries'"
);

if ($attendanceCheck && $attendanceCheck->num_rows > 0) {
    $attendanceTableExists = true;

    $attendanceResult = $conn->query("
        SELECT COALESCE(SUM(total_attendance), 0) AS total
        FROM attendance_summaries
    ");

    if ($attendanceResult) {
        $attendanceCount = (int) $attendanceResult
            ->fetch_assoc()['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasurer Dashboard | AIC Kalyet Church</title>

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
            padding: 25px 16px;
            background: #102b4e;
            color: white;
            overflow-y: auto;
        }

        .brand {
            padding: 0 10px 25px;
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
            margin: 25px 10px 10px;
            color: #94a3b8;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar a {
            display: block;
            padding: 12px 13px;
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

        .sidebar .logout {
            margin-top: 25px;
            background: #7f1d1d;
        }

        .main {
            margin-left: 255px;
            padding: 25px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 25px;
        }

        .topbar p {
            margin: 6px 0 0;
            color: #64748b;
        }

        .date {
            padding: 10px 14px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
        }

        .welcome {
            padding: 27px;
            margin-bottom: 25px;
            border-radius: 13px;
            color: white;
            background: linear-gradient(120deg, #12345b, #137b70);
        }

        .welcome h2 {
            margin: 0 0 10px;
            font-size: 24px;
        }

        .welcome p {
            margin: 0;
            line-height: 1.7;
            color: #e2f5f1;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 25px;
        }

        .card {
            padding: 21px;
            background: white;
            border-radius: 11px;
            box-shadow: 0 3px 12px #0f172a0b;
            border: 1px solid #e2e8f0;
        }

        .card-label {
            color: #64748b;
            font-size: 13px;
        }

        .card h3 {
            margin: 12px 0 8px;
            font-size: 23px;
            overflow-wrap: anywhere;
        }

        .card small {
            color: #64748b;
            font-size: 12px;
        }

        .income h3 {
            color: #15803d;
        }

        .expenses h3 {
            color: #b91c1c;
        }

        .balance h3 {
            color: #176b65;
        }

        .section {
            padding: 22px;
            margin-bottom: 25px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 17px;
        }

        .section-heading h2 {
            margin: 0;
            font-size: 19px;
        }

        .link-button {
            display: inline-block;
            padding: 10px 14px;
            background: #176b65;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .quick-link {
            padding: 19px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            text-decoration: none;
            color: #1e293b;
            transition: 0.2s;
        }

        .quick-link:hover {
            border-color: #176b65;
            transform: translateY(-2px);
        }

        .quick-link h3 {
            margin: 0 0 9px;
            font-size: 16px;
        }

        .quick-link p {
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
            border-collapse: collapse;
            min-width: 600px;
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

        .type-income {
            color: #15803d;
            font-weight: bold;
        }

        .type-expense {
            color: #b91c1c;
            font-weight: bold;
        }

        .notice {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 7px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 14px;
            line-height: 1.6;
        }

        .empty {
            padding: 20px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 1100px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-links {
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

            .cards,
            .quick-links {
                grid-template-columns: 1fr;
            }

            .topbar h1 {
                font-size: 21px;
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

    <div class="nav-title">Treasurer Menu</div>

    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="../financial_records.php">Financial Records</a>
    <a href="../attendance.php">Attendance Records</a>

    <div class="nav-title">Other</div>

    <a href="../dashboard.php">Main Dashboard</a>
    <a href="../logout.php" class="logout">Logout</a>
</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>Treasurer Dashboard</h1>
            <p>Welcome, <?= e($username) ?>.</p>
        </div>

        <div class="date">
            <?= date('l, d F Y') ?>
        </div>
    </header>

    <section class="welcome">
        <h2>Financial Management</h2>
        <p>
            Manage church income and expenses, review financial activity,
            and access attendance records. Keep all financial information
            accurate and up to date.
        </p>
    </section>

    <?php if (!$financialTableExists): ?>
        <div class="notice">
            The <strong>financial_records</strong> table was not found.
            Financial totals will appear once the correct table is available.
            Your existing records have not been changed.
        </div>
    <?php endif; ?>

    <section class="cards">

        <div class="card income">
            <div class="card-label">Total Income</div>
            <h3>KSh <?= number_format($totalIncome, 2) ?></h3>
            <small>Recorded income</small>
        </div>

        <div class="card expenses">
            <div class="card-label">Total Expenses</div>
            <h3>KSh <?= number_format($totalExpenses, 2) ?></h3>
            <small>Recorded expenditure</small>
        </div>

        <div class="card balance">
            <div class="card-label">Net Balance</div>
            <h3>KSh <?= number_format($netBalance, 2) ?></h3>
            <small>Income minus expenses</small>
        </div>

        <div class="card">
            <div class="card-label">Transactions</div>
            <h3><?= number_format($transactionCount) ?></h3>
            <small>Total recorded transactions</small>
        </div>

    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Quick Actions</h2>
        </div>

        <div class="quick-links">
            <a class="quick-link" href="../financial_records.php">
                <h3>Financial Records</h3>
                <p>Open the financial records page to manage church income and expenses.</p>
            </a>

            <a class="quick-link" href="../attendance.php">
                <h3>Attendance Records</h3>
                <p>View attendance summaries for men, women, youth, children and visitors.</p>
            </a>

            <a class="quick-link" href="../financial_records.php">
                <h3>Financial Reporting</h3>
                <p>Review recorded transactions and use the existing print options where available.</p>
            </a>
        </div>
    </section>

    <section class="section">
        <div class="section-heading">
            <h2>Recent Transactions</h2>
            <a class="link-button" href="../financial_records.php">
                View All Records
            </a>
        </div>

        <?php if (!$financialTableExists): ?>

            <p class="empty">
                Financial transaction data is not available yet.
            </p>

        <?php elseif (empty($recentTransactions)): ?>

            <p class="empty">
                No financial transactions have been recorded yet.
            </p>

        <?php else: ?>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td>
                                <?= e(date(
                                    'd M Y',
                                    strtotime($transaction['transaction_date'])
                                )) ?>
                            </td>

                            <td class="<?= strtolower($transaction['transaction_type']) === 'income'
                                ? 'type-income' : 'type-expense' ?>">
                                <?= e(ucfirst($transaction['transaction_type'])) ?>
                            </td>

                            <td><?= e($transaction['category']) ?></td>

                            <td><?= e($transaction['description']) ?></td>

                            <td>
                                KSh <?= number_format(
                                    (float) $transaction['amount'], 2
                                ) ?>
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
            <h2>Attendance Overview</h2>
            <a class="link-button" href="../attendance.php">
                Open Attendance
            </a>
        </div>

        <?php if ($attendanceTableExists): ?>
            <p>
                Total attendance across recorded services:
                <strong><?= number_format($attendanceCount) ?></strong>
            </p>
            <small>
                This is the sum of saved service attendance summaries,
                not a count of unique people.
            </small>
        <?php else: ?>
            <p class="empty">
                Attendance summaries are not available yet.
            </p>
        <?php endif; ?>
    </section>

</main>

</body>
</html>
