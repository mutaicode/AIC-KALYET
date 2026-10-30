
<?php
require_once __DIR__ . "/config/database.php";

$message = "";
$messageType = "success";

function escapeMinistry($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
    $name = trim($_POST["ministry_name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($action === "delete") {
        if (!$id) {
            $message = "Invalid ministry selected.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare(
                "DELETE FROM ministries WHERE id = ?"
            );
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                header("Location: ministries.php?status=deleted");
                exit;
            }

            $stmt->close();
            $message = "The ministry could not be deleted. It may be linked to other records.";
            $messageType = "error";
        }
    } elseif ($action === "add" || $action === "update") {
        if ($name === "") {
            $message = "Please enter the ministry name.";
            $messageType = "error";
        } elseif ($action === "update" && !$id) {
            $message = "Invalid ministry selected for editing.";
            $messageType = "error";
        } else {
            if ($action === "add") {
                $stmt = $conn->prepare(
                    "INSERT INTO ministries (ministry_name, description)
                     VALUES (?, ?)"
                );
                $stmt->bind_param("ss", $name, $description);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE ministries
                     SET ministry_name = ?, description = ?
                     WHERE id = ?"
                );
                $stmt->bind_param("ssi", $name, $description, $id);
            }

            if ($stmt->execute()) {
                $stmt->close();
                header(
                    "Location: ministries.php?status=" .
                    ($action === "add" ? "added" : "updated")
                );
                exit;
            }

            if ($stmt->errno === 1062) {
                $message = "A ministry with that name already exists.";
            } else {
                $message = "Unable to save the ministry. Please try again.";
            }

            $messageType = "error";
            $stmt->close();
        }
    }
}

// Status messages after saving
if (isset($_GET["status"])) {
    $statuses = [
        "added" => "Ministry added successfully.",
        "updated" => "Ministry updated successfully.",
        "deleted" => "Ministry deleted successfully."
    ];

    if (isset($statuses[$_GET["status"]])) {
        $message = $statuses[$_GET["status"]];
    }
}

// Load ministry for editing
$editMinistry = null;

if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $editId = (int) $_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT id, ministry_name, description
         FROM ministries WHERE id = ?"
    );
    $stmt->bind_param("i", $editId);
    $stmt->execute();

    $result = $stmt->get_result();
    $editMinistry = $result->fetch_assoc();
    $stmt->close();

    if (!$editMinistry) {
        $message = "Ministry not found.";
        $messageType = "error";
    }
}

// Fetch all ministries
$ministries = $conn->query(
    "SELECT id, ministry_name, description, created_at
     FROM ministries ORDER BY id DESC"
);

if (!$ministries) {
    die("Unable to load ministries. Please check your database table.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ministries Management | A.I.C Kalyet</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .ministry-container {
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
        }

        .ministry-header {
            background: linear-gradient(135deg, #0d47a1, #1976d2);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .ministry-header h1 {
            margin: 0 0 8px;
        }

        .ministry-card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(0, 0, 0, .08);
            margin-bottom: 25px;
        }

        .ministry-card h2 {
            color: #0d47a1;
            margin-top: 0;
        }

        .ministry-form label {
            display: block;
            font-weight: 600;
            margin: 15px 0 7px;
        }

        .ministry-form input,
        .ministry-form textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #ccd5e0;
            border-radius: 7px;
            font: inherit;
        }

        .ministry-form input:focus,
        .ministry-form textarea:focus {
            outline: 2px solid #90caf9;
            border-color: #1976d2;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            margin: 4px 2px;
        }

        .btn-primary { background: #1565c0; }
        .btn-edit { background: #00897b; }
        .btn-delete { background: #c62828; }
        .btn-cancel { background: #616161; }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .message-success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .message-error {
            background: #ffebee;
            color: #b71c1c;
        }

        .table-wrapper { overflow-x: auto; }

        .ministry-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        .ministry-table th,
        .ministry-table td {
            padding: 13px;
            border-bottom: 1px solid #e0e0e0;
            text-align: left;
        }

        .ministry-table th {
            background: #eaf2fb;
            color: #0d47a1;
        }

        .ministry-table tr:hover { background: #f8fbff; }

        .empty-state {
            padding: 20px;
            text-align: center;
            color: #666;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #1565c0;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .ministry-container {
                padding: 12px;
                margin: 15px auto;
            }

            .ministry-card { padding: 16px; }
        }
    </style>
</head>

<body>
<div class="ministry-container">

    <a class="back-link" href="index.php">&larr; Back to Homepage</a>

    <div class="ministry-header">
        <h1>Ministries Management</h1>
        <p>Manage ministries at A.I.C Kalyet Church.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType === "error"
            ? "message-error" : "message-success" ?>">
            <?= escapeMinistry($message) ?>
        </div>
    <?php endif; ?>

    <div class="ministry-card">
        <h2>
            <?= $editMinistry ? "Edit Ministry" : "Add New Ministry" ?>
        </h2>

        <form class="ministry-form" method="POST" action="ministries.php">
            <input
                type="hidden"
                name="action"
                value="<?= $editMinistry ? "update" : "add" ?>"
            >

            <?php if ($editMinistry): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$editMinistry["id"] ?>"
                >
            <?php endif; ?>

            <label for="ministry_name">Ministry Name *</label>
            <input
                type="text"
                id="ministry_name"
                name="ministry_name"
                maxlength="150"
                required
                value="<?= escapeMinistry(
                    $editMinistry["ministry_name"] ?? ""
                ) ?>"
                placeholder="e.g. Worship Ministry"
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                placeholder="Enter a brief description"
            ><?= escapeMinistry($editMinistry["description"] ?? "") ?></textarea>

            <button type="submit" class="btn btn-primary">
                <?= $editMinistry ? "Update Ministry" : "Add Ministry" ?>
            </button>

            <?php if ($editMinistry): ?>
                <a href="ministries.php" class="btn btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="ministry-card">
        <h2>Registered Ministries</h2>

        <div class="table-wrapper">
            <table class="ministry-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ministry Name</th>
                        <th>Description</th>
                        <th>Date Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($ministries->num_rows > 0): ?>
                    <?php $number = 1; ?>

                    <?php while ($ministry = $ministries->fetch_assoc()): ?>
                        <tr>
                            <td><?= $number++ ?></td>

                            <td>
                                <strong><?= escapeMinistry(
                                    $ministry["ministry_name"]
                                ) ?></strong>
                            </td>

                            <td><?= escapeMinistry(
                                $ministry["description"] ?: "No description"
                            ) ?></td>

                            <td><?= escapeMinistry(
                                $ministry["created_at"]
                            ) ?></td>

                            <td>
                                <a
                                    class="btn btn-edit"
                                    href="ministries.php?edit=<?= (int)$ministry["id"] ?>"
                                >Edit</a>

                                <form
                                    method="POST"
                                    action="ministries.php"
                                    style="display:inline"
                                    onsubmit="return confirm('Delete this ministry? This cannot be undone.');"
                                >
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$ministry["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-delete"
                                    >Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty-state">
                            No ministries have been added yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
