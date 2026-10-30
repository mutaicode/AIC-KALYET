
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

// Only the pastor can access this page.
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pastor'
) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$message = '';
$error = '';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// PRINT A SERMON
if (isset($_GET['print'])) {
    $sermonId = filter_var($_GET['print'], FILTER_VALIDATE_INT);

    if (!$sermonId) {
        exit('Invalid sermon ID.');
    }

    $stmt = $conn->prepare(
        "SELECT * FROM sermons WHERE id = ? AND preacher_id = ?"
    );
    $stmt->bind_param("ii", $sermonId, $userId);
    $stmt->execute();

    $sermon = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sermon) {
        exit('Sermon not found or access denied.');
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($sermon['title']) ?> - AIC Kalyet</title>

        <style>
            body {
                font-family: Arial, sans-serif;
                color: #222;
                max-width: 850px;
                margin: 30px auto;
                padding: 25px;
                line-height: 1.7;
            }

            .letterhead {
                text-align: center;
                border-bottom: 3px solid #174a35;
                padding-bottom: 15px;
                margin-bottom: 25px;
            }

            .letterhead h1 {
                color: #174a35;
                margin: 5px 0;
                font-size: 25px;
            }

            .letterhead p {
                margin: 4px 0;
            }

            .sermon-title {
                text-align: center;
                margin: 25px 0;
            }

            .details {
                background: #f4f7f5;
                padding: 15px;
                border-left: 4px solid #174a35;
                margin-bottom: 25px;
            }

            .sermon-content {
                white-space: pre-wrap;
                overflow-wrap: anywhere;
            }

            .outline {
                white-space: pre-wrap;
                overflow-wrap: anywhere;
            }

            .print-buttons {
                text-align: center;
                margin-bottom: 25px;
            }

            button {
                padding: 10px 18px;
                margin: 5px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
            }

            @media print {
                body {
                    margin: 0;
                    padding: 10px;
                    max-width: none;
                }

                .print-buttons {
                    display: none;
                }

                .details {
                    background: none;
                }
            }
        </style>
    </head>
    <body>
    <?php require_once __DIR__ . '/../includes/public_home_link.php'; ?>

        <div class="print-buttons">
            <button onclick="window.print()">Print / Save as PDF</button>
            <button onclick="window.close()">Close</button>
        </div>

        <header class="letterhead">
            <h1>AIC KALYET CHURCH</h1>
            <p><strong>SERMON NOTES</strong></p>
            <p>Faith • Service • Community</p>
        </header>

        <h2 class="sermon-title"><?= e($sermon['title']) ?></h2>

        <div class="details">
            <p>
                <strong>Date:</strong>
                <?= e(date('d F Y', strtotime($sermon['sermon_date']))) ?>
            </p>

            <p>
                <strong>Scripture Reference:</strong>
                <?= e($sermon['scripture_reference'] ?: 'Not specified') ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= e($sermon['status']) ?>
            </p>
        </div>

        <?php if (!empty($sermon['outline'])): ?>
            <h3>Sermon Outline</h3>
            <div class="outline"><?= e($sermon['outline']) ?></div>
        <?php endif; ?>

        <h3>Sermon Message</h3>
        <div class="sermon-content"><?= e($sermon['full_text']) ?></div>

        <p style="margin-top:40px; text-align:center;">
            <em>Prepared for ministry at AIC Kalyet Church</em>
        </p>

    </body>
    </html>
    <?php
    exit;
}

// SAVE, UPDATE OR DELETE SERMONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Your session token is invalid. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $sermonId = filter_var($_POST['sermon_id'] ?? '', FILTER_VALIDATE_INT);

            if (!$sermonId) {
                $error = 'Invalid sermon selected.';
            } else {
                $stmt = $conn->prepare(
                    "DELETE FROM sermons WHERE id = ? AND preacher_id = ?"
                );
                $stmt->bind_param("ii", $sermonId, $userId);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $message = 'Sermon deleted successfully.';
                } else {
                    $error = 'Sermon not found or could not be deleted.';
                }

                $stmt->close();
            }
        }

        if ($action === 'save') {
            $sermonId = (int) ($_POST['sermon_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $sermonDate = $_POST['sermon_date'] ?? '';
            $scripture = trim($_POST['scripture_reference'] ?? '');
            $outline = trim($_POST['outline'] ?? '');
            $fullText = trim($_POST['full_text'] ?? '');
            $status = $_POST['status'] ?? 'Draft';

            $dateObject = DateTime::createFromFormat('!Y-m-d', $sermonDate);
            $validDate = $dateObject &&
                $dateObject->format('Y-m-d') === $sermonDate;

            if ($title === '' || !$validDate || $fullText === '') {
                $error = 'Please enter a title, valid date and sermon message.';
            } elseif (!in_array($status, ['Draft', 'Published'], true)) {
                $error = 'Invalid sermon status.';
            } else {
                if ($sermonId > 0) {
                    $stmt = $conn->prepare(
                        "UPDATE sermons
                         SET title = ?, sermon_date = ?,
                             scripture_reference = ?, outline = ?,
                             full_text = ?, status = ?
                         WHERE id = ? AND preacher_id = ?"
                    );

                    $stmt->bind_param(
                        "ssssssii",
                        $title,
                        $sermonDate,
                        $scripture,
                        $outline,
                        $fullText,
                        $status,
                        $sermonId,
                        $userId
                    );

                    if ($stmt->execute()) {
                        if ($stmt->affected_rows >= 0) {
                            $message = 'Sermon updated successfully.';
                        }
                    } else {
                        $error = 'Unable to update the sermon.';
                    }

                    $stmt->close();
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO sermons
                         (title, preacher_id, sermon_date,
                          scripture_reference, outline, full_text, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "sisssss",
                        $title,
                        $userId,
                        $sermonDate,
                        $scripture,
                        $outline,
                        $fullText,
                        $status
                    );

                    if ($stmt->execute()) {
                        $message = 'Sermon saved successfully.';
                    } else {
                        $error = 'Unable to save the sermon.';
                    }

                    $stmt->close();
                }
            }
        }
    }
}

// LOAD SERMON FOR EDITING
$editSermon = null;

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId) {
        $stmt = $conn->prepare(
            "SELECT * FROM sermons WHERE id = ? AND preacher_id = ?"
        );
        $stmt->bind_param("ii", $editId, $userId);
        $stmt->execute();

        $editSermon = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// LOAD THE PASTOR'S SERMONS
$stmt = $conn->prepare(
    "SELECT id, title, sermon_date, scripture_reference, status, created_at
     FROM sermons
     WHERE preacher_id = ?
     ORDER BY sermon_date DESC, id DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$sermons = $stmt->get_result();
$stmt->close();

$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sermon Management | AIC Kalyet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6f4;
            color: #24332b;
        }

        .topbar {
            background: #174a35;
            color: white;
            padding: 20px 5%;
        }

        .topbar h1 {
            margin: 0 0 6px;
        }

        .topbar a {
            color: white;
            margin-right: 18px;
            text-decoration: none;
        }

        .container {
            width: 94%;
            max-width: 1200px;
            margin: 25px auto;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5ce;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .sermon-text {
            min-height: 280px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            background: #174a35;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-secondary {
            background: #52635a;
        }

        .btn-danger {
            background: #a62d2d;
        }

        .actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .notice {
            padding: 13px;
            margin-bottom: 18px;
            border-radius: 6px;
            background: #e0f4e5;
            color: #17592b;
        }

        .error {
            background: #fde5e5;
            color: #8b1e1e;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #e0e6e2;
        }

        th {
            background: #edf3ef;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            background: #e5e9e6;
        }

        @media (max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <h1>Sermon Management</h1>
    <p>AIC Kalyet Church — Pastor's Workspace</p>

    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<main class="container">

    <?php if ($message): ?>
        <div class="notice"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2><?= $editSermon ? 'Edit Sermon' : 'Prepare a New Sermon' ?></h2>

        <form method="POST" action="sermons.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="save">

            <input type="hidden" name="sermon_id"
                   value="<?= e($editSermon['id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="title">Sermon Title *</label>
                    <input id="title" name="title" required maxlength="200"
                           value="<?= e($editSermon['title'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="sermon_date">Sermon Date *</label>
                    <input type="date" id="sermon_date" name="sermon_date"
                           required
                           value="<?= e($editSermon['sermon_date'] ?? $today) ?>">
                </div>

                <div class="field full-width">
                    <label for="scripture_reference">Bible Scripture Reference</label>
                    <input id="scripture_reference" name="scripture_reference"
                           maxlength="200"
                           placeholder="e.g. John 3:16–21"
                           value="<?= e($editSermon['scripture_reference'] ?? '') ?>">
                </div>

                <div class="field full-width">
                    <label for="outline">Sermon Outline</label>
                    <textarea id="outline" name="outline"
                              placeholder="Introduction, main points, illustrations and conclusion..."><?= e($editSermon['outline'] ?? '') ?></textarea>
                </div>

                <div class="field full-width">
                    <label for="full_text">Full Sermon Message *</label>
                    <textarea class="sermon-text" id="full_text"
                              name="full_text" required
                              placeholder="Write your sermon message here..."><?= e($editSermon['full_text'] ?? '') ?></textarea>
                </div>

                <div class="field">
                    <label for="status">Sermon Status</label>
                    <select id="status" name="status">
                        <option value="Draft"
                            <?= (($editSermon['status'] ?? 'Draft') === 'Draft') ? 'selected' : '' ?>>
                            Draft
                        </option>
                        <option value="Published"
                            <?= (($editSermon['status'] ?? '') === 'Published') ? 'selected' : '' ?>>
                            Published
                        </option>
                    </select>
                </div>
            </div>

            <p>
                <button class="btn" type="submit">
                    <?= $editSermon ? 'Update Sermon' : 'Save Sermon' ?>
                </button>

                <?php if ($editSermon): ?>
                    <a class="btn btn-secondary" href="sermons.php">Cancel Edit</a>
                <?php endif; ?>
            </p>
        </form>
    </section>

    <section class="card">
        <h2>Saved Sermons</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Scripture</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($sermons->num_rows > 0): ?>
                    <?php while ($sermon = $sermons->fetch_assoc()): ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($sermon['sermon_date']))) ?></td>
                            <td><?= e($sermon['title']) ?></td>
                            <td><?= e($sermon['scripture_reference'] ?: '—') ?></td>
                            <td>
                                <span class="status"><?= e($sermon['status']) ?></span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn"
                                       href="sermons.php?edit=<?= (int) $sermon['id'] ?>">
                                        Edit
                                    </a>

                                    <a class="btn btn-secondary"
                                       href="sermons.php?print=<?= (int) $sermon['id'] ?>"
                                       target="_blank">
                                        Print
                                    </a>

                                    <form method="POST" action="sermons.php"
                                          onsubmit="return confirm('Delete this sermon permanently?');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="action"
                                               value="delete">
                                        <input type="hidden" name="sermon_id"
                                               value="<?= (int) $sermon['id'] ?>">
                                        <button class="btn btn-danger" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No sermons saved yet. Prepare your first sermon above.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>
