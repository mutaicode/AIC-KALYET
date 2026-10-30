<?php
session_start();
require_once __DIR__ . '/config/database.php';

$allowedRoles = [
    'admin',
    'chairperson',
    'secretary',
    'vice_secretary',
    'pastor',
];

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    !in_array(strtolower(trim($_SESSION['role'])), $allowedRoles, true)
) {
    http_response_code(403);
    exit('Access denied.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Invalid announcement ID.');
}

$stmt = $conn->prepare(
    "SELECT a.*, u.username
     FROM announcements a
     LEFT JOIN users u ON a.created_by = u.id
     WHERE a.id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit('Unable to prepare the announcement query.');
}

$stmt->bind_param('i', $id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$announcement) {
    http_response_code(404);
    exit('Announcement not found.');
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement - <?= e($announcement['title']) ?></title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #eef2f5;
            color: #111827;
            font-family: "Times New Roman", serif;
            line-height: 1.65;
        }

        .toolbar {
            max-width: 850px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 5px;
            padding: 10px 16px;
            color: #fff;
            cursor: pointer;
            font: 14px Arial, sans-serif;
            text-decoration: none;
        }

        .toolbar a { background: #475569; }
        .toolbar button { background: #166534; }

        .document {
            max-width: 850px;
            min-height: 1050px;
            margin: 0 auto;
            padding: 45px 55px;
            background: #fff;
            box-shadow: 0 3px 15px #00000015;
        }

        .letterhead {
            margin-bottom: 30px;
            padding-bottom: 17px;
            border-bottom: 3px double #123b2a;
            text-align: center;
        }

        .letterhead h1 {
            margin: 0;
            color: #123b2a;
            font-size: 25px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .letterhead h2 {
            margin: 5px 0;
            font-size: 17px;
            text-transform: uppercase;
        }

        .letterhead p {
            margin: 3px 0;
            font-size: 13px;
        }

        .document-meta {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .document-title {
            margin: 25px 0;
            text-align: center;
            font-size: 20px;
            text-transform: uppercase;
            text-decoration: underline;
        }

        .announcement-info {
            width: 100%;
            margin-bottom: 28px;
            border-collapse: collapse;
        }

        .announcement-info td {
            padding: 8px;
            border: 1px solid #aaa;
            vertical-align: top;
        }

        .announcement-info td:first-child {
            width: 28%;
            font-weight: bold;
        }

        .announcement-body {
            min-height: 260px;
            margin-top: 20px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .signature-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 55px;
            margin-top: 75px;
        }

        .signature-line {
            padding-top: 9px;
            border-top: 1px solid #111;
            text-align: center;
        }

        .signature-line strong,
        .signature-line span {
            display: block;
        }

        .signature-line span {
            margin-top: 4px;
            font-size: 13px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 10px;
            border-top: 1px solid #999;
            text-align: center;
            font-size: 11px;
        }

        @media print {
            @page { size: A4; margin: 15mm; }

            body {
                padding: 0;
                background: #fff;
            }

            .toolbar { display: none; }

            .document {
                max-width: none;
                min-height: 0;
                padding: 0;
                box-shadow: none;
            }

            .letterhead,
            .announcement-info,
            .announcement-body,
            .signature-area {
                break-inside: avoid;
            }
        }

        @media (max-width: 600px) {
            body { padding: 12px; }
            .document { padding: 28px 22px; }
            .document-meta,
            .signature-area { grid-template-columns: 1fr; }
            .document-meta { display: block; }
            .document-meta div + div { margin-top: 8px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="announcements.php">Back to announcements</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <main class="document">
        <header class="letterhead">
            <h1>A.I.C. Kalyet Church</h1>
            <h2>Church Management System</h2>
            <p>Faith &bull; Fellowship &bull; Service</p>
        </header>

        <div class="document-meta">
            <div><strong>Announcement No.:</strong> <?= (int)$announcement['id'] ?></div>
            <div><strong>Date:</strong> <?= e($announcement['announcement_date']) ?></div>
        </div>

        <h2 class="document-title"><?= e($announcement['title']) ?></h2>

        <table class="announcement-info">
            <tr>
                <td>To</td>
                <td><?= e($announcement['audience']) ?></td>
            </tr>
            <tr>
                <td>Issued by</td>
                <td><?= e($announcement['username'] ?? 'Church Secretary') ?></td>
            </tr>
        </table>

        <div class="announcement-body"><?= e($announcement['message']) ?></div>

        <section class="signature-area">
            <div class="signature-line">
                <strong>Chairperson</strong>
                <span>Signature &amp; Date</span>
            </div>
            <div class="signature-line">
                <strong>Secretary</strong>
                <span>Signature &amp; Date</span>
            </div>
        </section>

        <footer class="footer">
            Official church communication &mdash; A.I.C. Kalyet Church
        </footer>
    </main>
</body>
</html>
