<?php
$scriptDirectory = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
$appRoot = realpath(__DIR__ . '/..');
$relativeDirectory = $scriptDirectory && $appRoot
    ? trim(str_replace('\\', '/', substr($scriptDirectory, strlen($appRoot))), '/')
    : '';
$directoryDepth = $relativeDirectory === ''
    ? 0
    : substr_count($relativeDirectory, '/') + 1;
$homeHref = str_repeat('../', $directoryDepth) . 'index.php';
?>
<a class="public-home-link" href="<?= htmlspecialchars($homeHref, ENT_QUOTES, 'UTF-8'); ?>">
    <span aria-hidden="true">&larr;</span> Homepage
</a>
<style>
.public-home-link{
    position:fixed;
    right:20px;
    bottom:20px;
    z-index:1000;
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:11px 15px;
    border-radius:999px;
    background:#17365d;
    color:#fff;
    font:600 14px Arial,sans-serif;
    text-decoration:none;
    box-shadow:0 3px 12px rgba(0,0,0,.2)
}
.public-home-link:hover,.public-home-link:focus{
    background:#2b5686;
    color:#fff
}
@media print{.public-home-link{display:none!important}}
</style>
