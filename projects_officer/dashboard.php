
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Require login
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Allow only Projects Officer and Administrator
$role = strtolower(trim($_SESSION['role']));

if (!in_array($role, ['projects_officer', 'admin'], true)) {
    header('Location: ../dashboard.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Projects Officer';

// Escape output safely
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Get a single numeric value from a query
function getNumber($conn, $sql)
{
    try {
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (float)($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('Projects dashboard: ' . $e->getMessage());
    }

    return 0;
}

// Project statistics
$totalProjects = getNumber(
    $conn,
    "SELECT COUNT(*) AS total FROM projects"
);

$ongoingProjects = getNumber(
    $conn,
    "SELECT COUNT(*) AS total FROM projects
     WHERE status = 'Ongoing'"
);

$completedProjects = getNumber(
    $conn,
    "SELECT COUNT(*) AS total FROM projects
     WHERE status = 'Completed'"
);

$onHoldProjects = getNumber(
    $conn,
    "SELECT COUNT(*) AS total FROM projects
     WHERE status = 'On Hold'"
);

// Financial totals for projects only
$totalBudget = getNumber(
    $conn,
    "SELECT COALESCE(SUM(budget), 0) AS total
     FROM projects"
);

$totalContributions = getNumber(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM project_transactions
     WHERE transaction_type = 'Contribution'"
);

$totalExpenses = getNumber(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM project_transactions
     WHERE transaction_type = 'Expense'"
);

$projectBalance = $totalContributions - $totalExpenses;

// Recent projects
$recentProjects = [];

try {
    $result = $conn->query(
        "SELECT id, project_code, project_name,
                project_category, budget, amount_spent,
                progress_percentage, status,
                expected_completion_date
         FROM projects
         ORDER BY created_at DESC, id DESC
         LIMIT 8"
    );

    if ($result) {
        $recentProjects = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Recent projects: ' . $e->getMessage());
}

// Recent contributions and expenses
$recentTransactions = [];

try {
    $result = $conn->query(
        "SELECT t.id, t.transaction_type,
                t.transaction_date, t.category,
                t.description, t.contributor_or_payee,
                t.amount, p.project_name, p.project_code
         FROM project_transactions t
         INNER JOIN projects p ON p.id = t.project_id
         ORDER BY t.transaction_date DESC, t.id DESC
         LIMIT 8"
    );

    if ($result) {
        $recentTransactions = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Recent project transactions: ' . $e->getMessage());
}

// Format dates for display
function displayDate($date)
{
    if (!$date || $date === '0000-00-00') {
        return 'Not specified';
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

<title>Projects Officer Dashboard | AIC Kalyet</title>

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
    margin-top: 4px;
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
    line-height: 1.6;
}

.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(175px, 1fr));
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
    font-size: 26px;
    font-weight: bold;
    color: #17365d;
    margin-top: 9px;
    overflow-wrap: anywhere;
}

.stat-note {
    display: block;
    color: #8995a6;
    font-size: 12px;
    margin-top: 7px;
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
    min-width: 760px;
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
    padding: 5px 9px;
    border-radius: 20px;
    background: #edf2f7;
    color: #43546b;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}

.status.ongoing,
.status.completed,
.status.contribution {
    background: #dcfce7;
    color: #166534;
}

.status.planned,
.status.on-hold {
    background: #fef3c7;
    color: #92400e;
}

.status.cancelled,
.status.expense {
    background: #fee2e2;
    color: #991b1b;
}

.empty {
    padding: 22px;
    text-align: center;
    color: #78869a;
    background: #f8fafc;
    border-radius: 8px;
    line-height: 1.7;
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
    margin-bottom: 7px;
}

.quick-link span {
    font-size: 13px;
    color: #748196;
    line-height: 1.5;
}

.logout {
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

    <p class="nav-title">Projects Management</p>

    <a class="nav-link active" href="dashboard.php">
        🏠 Dashboard
    </a>

    <a class="nav-link" href="projects.php">
        🏗️ Project Register
    </a>

    <a class="nav-link" href="transactions.php">
        💰 Contributions & Expenses
    </a>

    <a class="nav-link" href="project_reports.php">
        📄 Project Reports
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
            <h1>Projects Officer Dashboard</h1>
            <p>Manage church projects, contributions, expenses and progress.</p>
        </div>

        <div class="profile">
            <strong><?= e($username); ?></strong>
            <span><?= e(ucwords(str_replace('_', ' ', $role))); ?></span>
        </div>
    </header>

    <section class="welcome">
        <h2>Welcome, <?= e($username); ?>!</h2>
        <p>
            Monitor ongoing church projects, track funds received and spent,
            and keep project records up to date.
        </p>
    </section>

    <section class="stats">

        <div class="stat">
            <span class="stat-label">Total Projects</span>
            <strong class="stat-number">
                <?= number_format($totalProjects); ?>
            </strong>
            <span class="stat-note">All registered projects</span>
        </div>

        <div class="stat">
            <span class="stat-label">Ongoing Projects</span>
            <strong class="stat-number">
                <?= number_format($ongoingProjects); ?>
            </strong>
            <span class="stat-note">Projects currently underway</span>
        </div>

        <div class="stat">
            <span class="stat-label">Completed Projects</span>
            <strong class="stat-number">
                <?= number_format($completedProjects); ?>
            </strong>
            <span class="stat-note">Projects marked completed</span>
        </div>

        <div class="stat">
            <span class="stat-label">Projects On Hold</span>
            <strong class="stat-number">
                <?= number_format($onHoldProjects); ?>
            </strong>
            <span class="stat-note">Projects temporarily paused</span>
        </div>

        <div class="stat">
            <span class="stat-label">Total Project Budget</span>
            <strong class="stat-number">
                KSh <?= number_format($totalBudget, 2); ?>
            </strong>
            <span class="stat-note">Combined registered budgets</span>
        </div>

        <div class="stat">
            <span class="stat-label">Contributions Received</span>
            <strong class="stat-number">
                KSh <?= number_format($totalContributions, 2); ?>
            </strong>
            <span class="stat-note">Recorded project contributions</span>
        </div>

        <div class="stat">
            <span class="stat-label">Project Expenses</span>
            <strong class="stat-number">
                KSh <?= number_format($totalExpenses, 2); ?>
            </strong>
            <span class="stat-note">Recorded project expenditure</span>
        </div>

        <div class="stat">
            <span class="stat-label">Contributions Less Expenses</span>
            <strong class="stat-number">
                KSh <?= number_format($projectBalance, 2); ?>
            </strong>
            <span class="stat-note">Across all project transactions</span>
        </div>

    </section>

    <section class="section">
        <h2>🏗️ Recently Registered Projects</h2>

        <?php if ($recentProjects): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project Code</th>
                            <th>Project Name</th>
                            <th>Category</th>
                            <th>Budget</th>
                            <th>Amount Spent</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Expected Completion</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentProjects as $project): ?>
                        <?php
                        $statusClass = match ($project['status']) {
                            'Ongoing' => 'ongoing',
                            'Completed' => 'completed',
                            'On Hold' => 'on-hold',
                            'Cancelled' => 'cancelled',
                            default => 'planned'
                        };
                        ?>

                        <tr>
                            <td><?= e($project['project_code']); ?></td>
                            <td><?= e($project['project_name']); ?></td>
                            <td><?= e($project['project_category'] ?: 'General'); ?></td>
                            <td>KSh <?= number_format((float)$project['budget'], 2); ?></td>
                            <td>KSh <?= number_format((float)$project['amount_spent'], 2); ?></td>
                            <td><?= (int)$project['progress_percentage']; ?>%</td>
                            <td>
                                <span class="status <?= e($statusClass); ?>">
                                    <?= e($project['status']); ?>
                                </span>
                            </td>
                            <td><?= e(displayDate($project['expected_completion_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                No projects have been registered yet.<br>
                Use the Project Register to add the church's first project.
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>💰 Recent Project Contributions & Expenses</h2>

        <?php if ($recentTransactions): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Contributor / Payee</th>
                            <th>Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <?php
                        $transactionClass =
                            $transaction['transaction_type'] === 'Contribution'
                            ? 'contribution'
                            : 'expense';
                        ?>

                        <tr>
                            <td><?= e(displayDate($transaction['transaction_date'])); ?></td>
                            <td>
                                <?= e($transaction['project_code']); ?><br>
                                <?= e($transaction['project_name']); ?>
                            </td>
                            <td>
                                <span class="status <?= e($transactionClass); ?>">
                                    <?= e($transaction['transaction_type']); ?>
                                </span>
                            </td>
                            <td><?= e($transaction['category']); ?></td>
                            <td><?= e($transaction['description']); ?></td>
                            <td><?= e($transaction['contributor_or_payee'] ?: 'Not specified'); ?></td>
                            <td>KSh <?= number_format((float)$transaction['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty">
                No project contributions or expenses have been recorded yet.<br>
                Use Contributions & Expenses to record the first transaction.
            </div>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>⚡ Quick Actions</h2>

        <div class="quick-links">

            <a class="quick-link" href="projects.php">
                <strong>➕ Register a Project</strong>
                <span>Add a project, budget, timeline and expected completion date.</span>
            </a>

            <a class="quick-link" href="transactions.php">
                <strong>💰 Record Contributions or Expenses</strong>
                <span>Record money received, expenditure, contributors and payees.</span>
            </a>

            <a class="quick-link" href="project_reports.php">
                <strong>🖨️ Project Reports</strong>
                <span>Review project progress and prepare printable reports.</span>
            </a>

            <a class="quick-link" href="../dashboard.php">
                <strong>🏠 Main Dashboard</strong>
                <span>Return to the main church management dashboard.</span>
            </a>

        </div>
    </section>

    <footer>
        &copy; <?= date('Y'); ?> AIC Kalyet Church Management System
    </footer>

</main>

</body>
</html>
