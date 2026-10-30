<?php
require_once __DIR__ . "/config/bootstrap.php";

function getCount(mysqli $conn, string $table): int
{
    $allowedTables = [
        "members",
        "departments",
        "ministries",
        "events"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM `$table`"
    );

    return $result
        ? (int) $result->fetch_assoc()["total"]
        : 0;
}

$activeMembersResult = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'user' AND status = 'active'"
);
if ($activeMembersResult) {
    $membersCount = (int) $activeMembersResult->fetch_assoc()["total"];
} else {
    $membersCount = 0;
    app_log(
        'homepage active members',
        new RuntimeException('Unable to count active members: ' . $conn->error)
    );
}
$departmentsCount = getCount($conn, "departments");
$ministriesCount = getCount($conn, "ministries");
$eventsCount = getCount($conn, "events");

$leadershipGroups = [
    [
        "title" => "Church Leadership",
        "icon" => "bi-building",
        "leaders" => [
            [
                "position" => "Chairperson",
                "name" => "John Kamau",
                "image" => "assets/images/leaders/chairperson.jpeg",
            ],
            [
                "position" => "Vice Chairperson",
                "name" => "Peter Otieno",
                "image" => "assets/images/leaders/vice-chairperson.jpeg",
            ],
            [
                "position" => "Secretary",
                "name" => "Mary Wanjiku",
                "image" => "assets/images/leaders/secretary.jpeg",
            ],
            [
                "position" => "Vice Secretary",
                "name" => "Sarah Kimani",
                "image" => "assets/images/leaders/vice-secretary.jpeg",
            ],
            [
                "position" => "Treasurer",
                "name" => "James Mwangi",
                "image" => "assets/images/leaders/treasurer.jpeg",
            ],
        ],
    ],
    [
        "title" => "Women Leadership",
        "icon" => "bi-heart",
        "leaders" => [
            [
                "position" => "Women Chairperson",
                "name" => "Elizabeth Njeri",
                "image" => "assets/images/leaders/women-chairperson.jpeg",
            ],
            [
                "position" => "Women Vice Chairperson",
                "name" => "Grace Mwangi",
                "image" => "assets/images/leaders/women-vice-chairperson.jpeg",
            ],
            [
                "position" => "Women Secretary",
                "name" => "Susan Wambui",
                "image" => "assets/images/leaders/women-secretary.jpeg",
            ],
            [
                "position" => "Women Vice Secretary",
                "name" => "Mercy Kimani",
                "image" => "assets/images/leaders/women-vice-secretary.jpeg",
            ],
            [
                "position" => "Women Treasurer",
                "name" => "Anne Wanjiru",
                "image" => "assets/images/leaders/women-treasurer.jpeg",
            ],
        ],
    ],
    [
        "title" => "Youth Leadership",
        "icon" => "bi-stars",
        "leaders" => [
            [
                "position" => "Youth Chairperson",
                "name" => "Michael Otieno",
                "image" => "assets/images/leaders/youth-chairperson.jpeg",
            ],
            [
                "position" => "Youth Vice Chairperson",
                "name" => "Emily Auma",
                "image" => "assets/images/leaders/youth-vice-chairperson.jpeg",
            ],
            [
                "position" => "Youth SECRETARY",
                "name" => "David Mwangi",
                "image" => "assets/images/leaders/youth-secretary.jpeg",
            ],
            [
                "position" => "Youth Vice Secretary",
                "name" => "Lisa Wambui",
                "image" => "assets/images/leaders/youth-vice-secretary.jpeg",
            ],
            [
                "position" => "Youth Treasurer",
                "name" => "Brian Kimani",
                "image" => "assets/images/leaders/youth-treasurer.jpeg",
            ],
        ],
    ],
];

$publishedEvents = [];
$publishedAnnouncements = [];
$churchMemories = [];

$memoriesResult = $conn->query(
    "SELECT title, image_path, username
     FROM church_memories
     INNER JOIN users ON users.id = church_memories.uploaded_by
     ORDER BY church_memories.created_at DESC, church_memories.id DESC
     LIMIT 12"
);

if ($memoriesResult) {
    $churchMemories = $memoriesResult->fetch_all(MYSQLI_ASSOC);
} else {
    app_log(
        'homepage church memories',
        new RuntimeException('Unable to load church memories: ' . $conn->error)
    );
}

$eventsResult = $conn->query(
    "SELECT event_name, event_date, location, description
     FROM events
     WHERE status = 'Published' AND event_date >= CURDATE()
     ORDER BY event_date ASC, id DESC
     LIMIT 6"
);

if ($eventsResult) {
    while ($event = $eventsResult->fetch_assoc()) {
        $publishedEvents[] = $event;
    }
} else {
    app_log(
        'homepage events',
        new RuntimeException('Unable to load published events: ' . $conn->error)
    );
}

$announcementsResult = $conn->query(
    "SELECT title, message, announcement_date, audience
     FROM announcements
     WHERE status = 'Published'
     ORDER BY announcement_date DESC, id DESC
     LIMIT 6"
);

if ($announcementsResult) {
    while ($announcement = $announcementsResult->fetch_assoc()) {
        $publishedAnnouncements[] = $announcement;
    }
} else {
    app_log(
        'homepage announcements',
        new RuntimeException(
            'Unable to load published announcements: ' . $conn->error
        )
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>A.I.C Kalyet | Church Management System</title>
    <meta name="description"
          content="A.I.C Kalyet Church Management System for managing members, attendance, ministries, events and church finances.">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top main-navbar">
    <div class="container">
        <a class="navbar-brand brand" href="#home">
            <span class="brand-icon">
                <i class="bi bi-house-heart-fill"></i>
            </span>

            <span>
                <strong>A.I.C KALYET</strong>
                <small>Church Management System</small>
            </span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavigation"
                aria-controls="mainNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="#home">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#leadership">Leadership</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#memories">Memories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#gallery">Gallery</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#updates">Updates</a>
                </li>
            </ul>

            <div class="d-flex flex-column flex-lg-row gap-2 nav-actions">
    <a href="login.php" class="btn btn-outline-primary px-4">
        <i class="bi bi-person-circle me-1"></i> Login
    </a>

    <a href="register.php" class="btn btn-outline-success px-4">
        <i class="bi bi-person-plus me-1"></i> Register
    </a>
</div>
        </div>
    </div>
</nav>


<!-- HERO SECTION -->
<header class="hero-section" id="home">
    <div class="hero-image hero-image-left"></div>
    <div class="hero-image hero-image-right"></div>
    <div class="hero-overlay"></div>

    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-8 col-xl-7">

                <span class="hero-badge">
                    <i class="bi bi-stars me-2"></i>
                    Faith • Community • Service
                </span>

                <p class="welcome-text mt-4 mb-1">Welcome to</p>

                <h1>
                    A.I.C Kalyet
                    <span>Church Management System</span>
                </h1>

                <p class="hero-description">
                    Connecting our church family through technology.
                    Manage members, attendance, giving, events and
                    ministries in one secure and easy-to-use platform.
                </p>

                <div class="hero-platform">
                    <i class="bi bi-shield-check"></i>
                    Simple Church Management
                    <span class="hero-divider">|</span>
                    Built for Our Church
                </div>

                <div class="hero-buttons mt-4">
    <a href="login.php" class="btn btn-primary btn-lg">
        Get Started <i class="bi bi-arrow-right ms-2"></i>
    </a>

    <a href="register.php" class="btn btn-outline-light btn-lg">
        <i class="bi bi-person-plus me-2"></i> Register
    </a>

    <a href="#features" class="btn btn-outline-light btn-lg">
        <i class="bi bi-grid me-2"></i> Explore Features
    </a>
</div>

            </div>
        </div>
    </div>

    <div class="hero-verse">
        <i class="bi bi-cross"></i>
        <p>One Church.<br>One Family.<br>One Faith.</p>
    </div>
</header>


<!-- FEATURES -->
<section class="features-section section-padding" id="features">
    <div class="container">

        <div class="section-heading text-center">
            <span class="section-label">OUR PLATFORM</span>
            <h2>Everything Your Church Needs</h2>
            <p>Simple tools to support church administration and ministry.</p>
        </div>

        <div class="row g-4 mt-3">

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-people"></i></div>
                    <h5>Member Management</h5>
                    <p>Register members and maintain organized church records.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-calendar-check"></i></div>
                    <h5>Attendance</h5>
                    <p>Record attendance for services, meetings and ministries.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-wallet2"></i></div>
                    <h5>Giving & Finance</h5>
                    <p>Organize offerings, donations, expenses and reports.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-calendar-event"></i></div>
                    <h5>Events & Calendar</h5>
                    <p>Plan church events, meetings, conferences and camps.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-diagram-3"></i></div>
                    <h5>Ministries</h5>
                    <p>Organize youth, choir, Sunday school and other departments.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-megaphone"></i></div>
                    <h5>Announcements</h5>
                    <p>Share church news, notices and upcoming activities.</p>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-robot"></i></div>
                    <h5>AI Assistant</h5>
                    <p>Plan programs and help prepare reports using AI.</p>
                    <span class="coming-soon">Planned feature</span>
                </div>
            </div>

            <div class="col-6 col-md-4 col-lg-3">
                <div class="feature-card">
                    <div class="feature-icon"><i class="bi bi-shield-lock"></i></div>
                    <h5>Privacy & Security</h5>
                    <p>Protect church records with role-based access.</p>
                </div>
            </div>

        </div>
    </div>
</section>
<!-- Church Statistics Section -->
<section class="church-stats py-5">
    <div class="container">
        <div class="row text-center g-4">

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <h2 class="stat-number"
    data-target="<?= $membersCount ?>">
    <?= $membersCount ?>
</h2>
                    <p>Active Members</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">⛪</div>
                    <h2 class="stat-number"
    data-target="<?= $departmentsCount ?>">
    <?= $departmentsCount ?>
</h2>
                    <p>Departments</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">🙏</div>
                    <h2 class="stat-number"
    data-target="<?= $ministriesCount ?>">
    <?= $ministriesCount ?>
</h2>
                    <p>Ministries</p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <h2 class="stat-number"
    data-target="<?= $eventsCount ?>">
    <?= $eventsCount ?>
</h2>
                    <p>Church Events</p>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- LEADERSHIP -->
<section class="bg-light section-padding" id="leadership">
    <div class="container">
        <div class="section-heading text-center">
            <span class="section-label">OUR LEADERSHIP</span>
            <h2>Serving Our Church Family</h2>
            <p>Meet the leaders serving the church, women and youth ministries.</p>
        </div>

        <div class="mt-4">
            <?php foreach ($leadershipGroups as $group): ?>
                <div class="leadership-group bg-white border rounded-4 shadow-sm p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-center gap-2 mb-4">
                        <span class="d-flex align-items-center justify-content-center rounded-circle
                                     bg-primary-subtle text-primary p-2">
                            <i class="bi <?= e($group['icon']) ?>"></i>
                        </span>
                        <h3 class="h4 fw-bold text-primary-emphasis mb-0">
                            <?= e($group['title']) ?>
                        </h3>
                    </div>

                    <div class="row g-4">
                        <?php foreach ($group['leaders'] as $leader): ?>
    <div class="col-6 col-md-4 col-lg">
        <article class="leader-card h-100 text-center border rounded-4 p-3 shadow-sm">
            <div class="ratio ratio-1x1 w-75 mx-auto mb-3 rounded-circle overflow-hidden">
                <img src="<?= e($leader['image']) ?>"
                     class="w-100 h-100 object-fit-cover"
                     alt="<?= e($leader['position']) ?>">
            </div>

            <h4 class="h6 fw-bold text-primary-emphasis mb-1">
                <?= e($leader['name']) ?>
            </h4>

            <p class="small text-secondary mb-0">
                <?= e($leader['position']) ?>
            </p>
        </article>
    </div>
<?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CHURCH MEMORIES -->
<section class="section-padding bg-light" id="memories">
    <div class="container">
        <div class="section-heading text-center">
            <span class="section-label">CHURCH MEMORIES</span>
            <h2>Moments We Share</h2>
            <p>Celebrate favourite memories from our church family.</p>
        </div>

        <?php if ($churchMemories !== []): ?>
            <div class="memories-marquee mt-4" aria-label="Church memories">
                <div class="memories-marquee-track">
                    <?php for ($copy = 0; $copy < 2; $copy++): ?>
                        <?php foreach ($churchMemories as $memory): ?>
                            <article class="memory-marquee-card"
                                     <?= $copy === 1 ? 'aria-hidden="true"' : '' ?>>
                                <img src="<?= e($memory['image_path']) ?>"
                                     class="memory-marquee-image"
                                     loading="<?= $copy === 0 ? 'eager' : 'lazy' ?>"
                                     alt="<?= e($memory['title'] ?: 'Church memory') ?>">
                                <div class="p-3">
                                    <h3 class="h6 mb-1">
                                        <?= e($memory['title'] ?: 'Church memory') ?>
                                    </h3>
                                    <p class="small text-secondary mb-0">
                                        Shared by <?= e($memory['username']) ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center bg-white border rounded-4 p-5 mt-4">
                <i class="bi bi-images display-5 text-primary"></i>
                <p class="text-secondary mt-3 mb-3">No church memories have been shared yet.</p>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn btn-primary" href="memories.php">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Share a memory
                </a>
            <?php else: ?>
                <a class="btn btn-outline-primary" href="login.php">
                    Login to share a memory
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- PUBLIC ANNOUNCEMENTS AND EVENTS -->
<section class="public-updates-section section-padding" id="updates">
    <div class="container">
        <div class="section-heading text-center">
            <span class="section-label">CHURCH UPDATES</span>
            <h2>Announcements & Upcoming Events</h2>
            <p>Stay informed about the latest activities from our church leadership.</p>
        </div>

        <div class="row g-4 mt-3">
            <div class="col-lg-6">
                <div class="updates-panel h-100">
                    <div class="updates-panel-heading">
                        <h3><i class="bi bi-megaphone-fill me-2"></i>Latest Announcements</h3>
                    </div>

                    <?php if ($publishedAnnouncements === []): ?>
                        <p class="updates-empty mb-0">There are no published announcements at the moment.</p>
                    <?php else: ?>
                        <div class="updates-list">
                            <?php foreach ($publishedAnnouncements as $announcement): ?>
                                <article class="update-item">
                                    <div class="update-item-icon">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                    <div>
                                        <h4><?= e($announcement['title']) ?></h4>
                                        <p><?= nl2br(e($announcement['message'])) ?></p>
                                        <small>
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= e(format_date($announcement['announcement_date'], 'd M Y')) ?>
                                            <?php if (!empty($announcement['audience'])): ?>
                                                <span class="ms-2">
                                                    <i class="bi bi-people me-1"></i>
                                                    <?= e($announcement['audience']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="updates-panel h-100">
                    <div class="updates-panel-heading">
                        <h3><i class="bi bi-calendar-event-fill me-2"></i>Upcoming Events</h3>
                    </div>

                    <?php if ($publishedEvents === []): ?>
                        <p class="updates-empty mb-0">There are no upcoming published events at the moment.</p>
                    <?php else: ?>
                        <div class="updates-list">
                            <?php foreach ($publishedEvents as $event): ?>
                                <article class="update-item">
                                    <div class="update-item-icon event-icon">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div>
                                        <h4><?= e($event['event_name']) ?></h4>
                                        <?php if (!empty($event['description'])): ?>
                                            <p><?= nl2br(e($event['description'])) ?></p>
                                        <?php endif; ?>
                                        <small>
                                            <i class="bi bi-calendar3 me-1"></i>
                                            <?= e(format_date($event['event_date'], 'd M Y')) ?>
                                            <?php if (!empty($event['location'])): ?>
                                                <span class="ms-2">
                                                    <i class="bi bi-geo-alt me-1"></i>
                                                    <?= e($event['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ABOUT OUR CHURCH -->
<section class="about-section section-padding" id="about">
    <div class="container">
        <div class="row align-items-center g-5">

            <div class="col-lg-5">
                <span class="section-label">ABOUT OUR CHURCH</span>

                <h2 class="section-title">
                    Building a Stronger Church Together
                </h2>

                <p class="about-description">
                    A.I.C Kalyet is a place of worship, fellowship and service.
                    We are committed to growing in faith, nurturing believers
                    and making a positive impact in our community.
                </p>

                <p>
                    Our Church Management System is designed to make
                    administration easier, improve coordination and help
                    our leaders focus on serving people.
                </p>

                <ul class="benefit-list">
                    <li><i class="bi bi-check-circle-fill"></i> Easy to use</li>
                    <li><i class="bi bi-check-circle-fill"></i> Secure records</li>
                    <li><i class="bi bi-check-circle-fill"></i> Mobile friendly</li>
                    <li><i class="bi bi-check-circle-fill"></i> Organized ministries</li>
                </ul>

                <a href="#contact" class="btn btn-primary mt-3">
                    Learn More <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>

            <div class="col-lg-7" id="gallery">
                <div class="church-gallery">
                    <img src="assets/images/church-hero-1.jpg.jpeg"
                         alt="A.I.C Kalyet church grounds"
                         class="gallery-main">

                    <img src="assets/images/church-hero-2.jpg.jpeg"
                         alt="A.I.C Kalyet church building"
                         class="gallery-secondary">

                    <div class="gallery-caption">
                        <i class="bi bi-heart-fill"></i>
                        <span>One Church, One Family</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>


<!-- DASHBOARD PREVIEW -->
<section class="dashboard-section section-padding">
    <div class="container">

        <div class="section-heading text-center">
            <span class="section-label">SMART ADMINISTRATION</span>
            <h2>A Clear View of Church Activities</h2>
            <p>One place for authorized leaders to manage church operations.</p>
        </div>

        <div class="dashboard-preview mt-5">

            <aside class="preview-sidebar">
                <div class="preview-brand">
                    <i class="bi bi-house-heart-fill"></i>
                    <span>A.I.C Kalyet<small>Management System</small></span>
                </div>

                <div class="preview-menu-item active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-people"></i> Members
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-calendar-check"></i> Attendance
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-diagram-3"></i> Ministries
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-calendar-event"></i> Events
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-wallet2"></i> Giving & Finance
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-megaphone"></i> Announcements
                </div>
                <div class="preview-menu-item">
                    <i class="bi bi-robot"></i> AI Assistant
                </div>
            </aside>

            <div class="preview-content">
                <div class="preview-topbar">
                    <div>
                        <h5>Church Overview</h5>
                        <p>Welcome to your management dashboard.</p>
                    </div>
                    <span class="preview-user">
                        <i class="bi bi-person-circle"></i> Church Official
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <i class="bi bi-people text-primary"></i>
                            <small>Total Members</small>
                            <h4>--</h4>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <i class="bi bi-calendar-check text-success"></i>
                            <small>Attendance</small>
                            <h4>--</h4>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <i class="bi bi-calendar-event text-warning"></i>
                            <small>Upcoming Events</small>
                            <h4>--</h4>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <i class="bi bi-wallet2 text-info"></i>
                            <small>Giving Summary</small>
                            <h4>--</h4>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="preview-panel">
                            <h6><i class="bi bi-calendar3 me-2"></i>Upcoming Activities</h6>
                            <p>Church services and meetings will appear here.</p>
                            <span class="preview-placeholder">Connect your events module to display records.</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="preview-panel ai-panel">
                            <h6><i class="bi bi-robot me-2"></i>Church AI Assistant</h6>
                            <p>Get help preparing church reports and programs.</p>
                            <span class="preview-placeholder">AI integration planned for a later phase.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <p class="text-center text-secondary small mt-3">
            Dashboard preview only. Live statistics will appear after the database is connected.
        </p>
    </div>
</section>


<!-- CALL TO ACTION -->
<section class="cta-section">
    <div class="container text-center">
        <i class="bi bi-people-fill cta-icon"></i>
        <h2>Growing Together in Faith and Service</h2>
        <p>Organizing our church today for a stronger community tomorrow.</p>
        <a href="login.php" class="btn btn-light btn-lg">
            Access Church System <i class="bi bi-arrow-right ms-2"></i>
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer class="site-footer" id="contact">
    <div class="container">
        <div class="row g-4">

            <div class="col-lg-4">
                <a class="footer-brand" href="#home">
                    <i class="bi bi-house-heart-fill"></i>
                    <span>
                        <strong>A.I.C KALYET</strong>
                        <small>Church Management System</small>
                    </span>
                </a>
                <p class="footer-description mt-3">
                    Serving our church family through faith, fellowship
                    and responsible stewardship.
                </p>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Quick Links</h6>
                <a href="#home">Home</a>
                <a href="#features">Features</a>
                <a href="#about">About Us</a>
                <a href="#gallery">Gallery</a>
            </div>

            <div class="col-6 col-lg-3">
                <h6>Our Platform</h6>
                <a href="#features">Member Management</a>
                <a href="#features">Attendance</a>
                <a href="#features">Giving & Finance</a>
                <a href="#features">Events & Ministries</a>
            </div>

            <div class="col-lg-3">
                <h6>Contact</h6>
                <p><i class="bi bi-geo-alt me-2"></i>A.I.C Kalyet, Kenya</p>
                <p><i class="bi bi-envelope me-2"></i>Church office contact</p>
                <p class="small text-white-50">
                    Replace these details with your official contact information.
                </p>
            </div>

        </div>

        <hr class="footer-divider">

        <div class="footer-bottom">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> A.I.C Kalyet.
                All rights reserved.
            </p>
            <a href="#home">Back to top ↑</a>
        </div>
    </div>
</footer>


<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="assets/js/script.js"></script>

</body>
</html>