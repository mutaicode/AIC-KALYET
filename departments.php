<?php
require_once __DIR__ . "/config/bootstrap.php";

$message = "";
$messageType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Security check failed. Refresh the page and try again.";
        $messageType = "error";
    } else {
        $action = $_POST["action"] ?? "";

        if ($action === "add") {
            $departmentName = trim($_POST["department_name"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if ($departmentName === "") {
                $message = "Please enter a department name.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO departments (department_name, description) VALUES (?, ?)"
                    );
                    $stmt->bind_param("ss", $departmentName, $description);
                    if ($stmt->execute()) {
                        $stmt->close();
                        flash_set('dept', 'Department added successfully.');
                        header("Location: departments.php");
                        exit;
                    }
                    throw new RuntimeException($stmt->error);
                } catch (Throwable $ex) {
                    app_log('departments add', $ex);
                    $message = ($ex->getCode() === 1062)
                        ? "This department already exists."
                        : "Unable to add department. Please try again.";
                    $messageType = "error";
                }
            }
        }

        if ($action === "update") {
            $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
            $departmentName = trim($_POST["department_name"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if (!$id || $departmentName === "") {
                $message = "Please provide a valid department ID and name.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $conn->prepare(
                        "UPDATE departments SET department_name = ?, description = ? WHERE id = ?"
                    );
                    $stmt->bind_param("ssi", $departmentName, $description, $id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        flash_set('dept', 'Department updated successfully.');
                        header("Location: departments.php");
                        exit;
                    }
                    throw new RuntimeException($stmt->error);
                } catch (Throwable $ex) {
                    app_log('departments update', $ex);
                    $message = ($ex->getCode() === 1062)
                        ? "Another department already uses that name."
                        : "Unable to update department. Please try again.";
                    $messageType = "error";
                }
            }
        }

        if ($action === "delete") {
            $id = filter_var($_POST["id"] ?? "", FILTER_VALIDATE_INT);
            if (!$id) {
                $message = "Invalid department selected.";
                $messageType = "error";
            } else {
                try {
                    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $stmt->close();
                        flash_set('dept', 'Department deleted successfully.');
                        header("Location: departments.php");
                        exit;
                    }
                    throw new RuntimeException($stmt->error);
                } catch (Throwable $ex) {
                    app_log('departments delete', $ex);
                    $message = "Unable to delete this department. It may be linked to other records.";
                    $messageType = "error";
                }
            }
        }
    }
}

if ($flash = flash_get('dept')) {
    $message = $flash['message'];
    $messageType = $flash['type'];
}

$editDepartment = null;
if (isset($_GET["edit"]) && ctype_digit($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $stmt = $conn->prepare("SELECT id, department_name, description FROM departments WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $editDepartment = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$departments = $conn->query(
    "SELECT id, department_name, description, created_at FROM departments ORDER BY id DESC"
);