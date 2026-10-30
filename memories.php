<?php
require_once __DIR__ . '/config/bootstrap.php';

require_login();

$userId = (int) $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? ''));
$isAdmin = $role === 'admin';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'delete' && $isAdmin) {
        $memoryId = filter_var($_POST['memory_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$memoryId) {
            $error = 'Invalid memory selected.';
        } else {
            $stmt = $conn->prepare(
                'SELECT image_path FROM church_memories WHERE id = ? LIMIT 1'
            );
            $stmt->bind_param('i', $memoryId);
            $stmt->execute();
            $memory = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$memory) {
                $error = 'The selected memory was not found.';
            } else {
                $stmt = $conn->prepare('DELETE FROM church_memories WHERE id = ?');
                $stmt->bind_param('i', $memoryId);

                if (!$stmt->execute()) {
                    $error = 'Unable to delete the memory.';
                } else {
                    $filePath = __DIR__ . '/' . ltrim($memory['image_path'], '/');
                    if (is_file($filePath) && !unlink($filePath)) {
                        app_log(
                            'memory image delete',
                            new RuntimeException('Unable to remove image file: ' . $filePath)
                        );
                    }
                    $message = 'Memory deleted successfully.';
                }
                $stmt->close();
            }
        }
    } elseif (($_POST['action'] ?? '') === 'upload') {
        $file = $_FILES['memory_image'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Choose a valid image to upload.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Images must be 5 MB or smaller.';
        } elseif ($title !== '' && mb_strlen($title) > 150) {
            $error = 'The memory title must be 150 characters or fewer.';
        } else {
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

            if (!isset($allowedTypes[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
                $error = 'Only valid JPG, PNG or WebP images are allowed.';
            } else {
                $uploadDirectory = __DIR__ . '/assets/uploads/memories';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
                    $error = 'The memory upload folder could not be created.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
                    $relativePath = 'assets/uploads/memories/' . $fileName;
                    $targetPath = __DIR__ . '/' . $relativePath;

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        $error = 'The image could not be saved.';
                    } else {
                        $stmt = $conn->prepare(
                            'INSERT INTO church_memories (title, image_path, uploaded_by)
                             VALUES (NULLIF(?, \'\'), ?, ?)'
                        );
                        $stmt->bind_param('ssi', $title, $relativePath, $userId);

                        if (!$stmt->execute()) {
                            unlink($targetPath);
                            $error = 'The memory could not be saved.';
                        } else {
                            $message = 'Church memory uploaded successfully.';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    } elseif (!$isAdmin && ($_POST['action'] ?? '') === 'delete') {
        http_response_code(403);
        exit('Only administrators can delete church memories.');
    }
}

$memories = [];
$result = $conn->query(
    'SELECT m.id, m.title, m.image_path, m.created_at, u.username
     FROM church_memories m
     INNER JOIN users u ON u.id = m.uploaded_by
     ORDER BY m.created_at DESC, m.id DESC'
);

if ($result) {
    $memories = $result->fetch_all(MYSQLI_ASSOC);
} else {
    app_log(
        'memory list',
        new RuntimeException('Unable to load church memories: ' . $conn->error)
    );
    $error = $error ?: 'Unable to load church memories.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Memories | A.I.C Kalyet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Church Memories</h1>
            <p class="text-secondary mb-0">Share special moments from our church family.</p>
        </div>
        <a class="btn btn-outline-primary" href="index.php">Back to home</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Upload a favourite memory</h2>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload">
                <div class="col-md-5">
                    <label class="form-label" for="title">Caption (optional)</label>
                    <input class="form-control" id="title" name="title" maxlength="150">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="memory_image">Photo</label>
                    <input class="form-control" id="memory_image" name="memory_image"
                           type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                           required>
                    <div class="form-text">JPG, PNG or WebP; maximum 5 MB.</div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Upload</button>
                </div>
            </form>
        </div>
    </section>

    <section class="row g-4">
        <?php foreach ($memories as $memory): ?>
            <div class="col-sm-6 col-lg-4">
                <article class="card h-100 border-0 shadow-sm overflow-hidden">
                    <img src="<?= e($memory['image_path']) ?>"
                         class="card-img-top memory-thumbnail p-2"
                         loading="lazy"
                         alt="<?= e($memory['title'] ?: 'Church memory') ?>">
                    <div class="card-body">
                        <h2 class="h6"><?= e($memory['title'] ?: 'Church memory') ?></h2>
                        <p class="small text-secondary mb-2">
                            Uploaded by <?= e($memory['username']) ?>
                        </p>
                        <?php if ($isAdmin): ?>
                            <form method="POST"
                                  onsubmit="return confirm('Delete this church memory?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="memory_id"
                                       value="<?= (int) $memory['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    Delete
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </section>

    <?php if ($memories === []): ?>
        <div class="text-center text-secondary py-5">No church memories uploaded yet.</div>
    <?php endif; ?>
</main>
</body>
</html>
