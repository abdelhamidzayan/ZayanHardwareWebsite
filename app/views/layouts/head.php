<?php
/**
 * app/views/layouts/head.php — shared <head> content for every layout.
 * Scope comes from the layout: $e, $baseUrl, $appName, $title.
 */
$pageTitle = isset($title) && $title !== '' ? $title . ' | ' . $appName : $appName;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $e($pageTitle) ?></title>
<meta name="theme-color" content="#ffffff">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">

<link rel="stylesheet" href="<?= $e($baseUrl) ?>/css/tokens.css">
<link rel="stylesheet" href="<?= $e($baseUrl) ?>/css/app.css">
<link rel="stylesheet" href="<?= $e($baseUrl) ?>/css/components.css">
<link rel="stylesheet" href="<?= $e($baseUrl) ?>/css/pages.css">
<link rel="stylesheet" href="<?= $e($baseUrl) ?>/css/responsive.css">
