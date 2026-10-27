<?php
/**
 * app/views/layouts/flash.php — one-time messages after an action.
 * Included by app.php (shares its scope: $e).
 *
 * Optional variable:
 *   $flash  array  [['type' => 'success|error|info', 'message' => '...'], ...]
 *                  filled from the session by the View class, then cleared.
 */

$flash = $flash ?? [];

$flashIcons = [
    'success' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    'error'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
    'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
];
?>
<?php if ($flash) : ?>
    <div class="container flash-list">
        <?php foreach ($flash as $item) : ?>
            <?php $type = in_array($item['type'] ?? '', ['success', 'error', 'info'], true) ? $item['type'] : 'info'; ?>
            <div class="flash flash--<?= $type ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><?= $flashIcons[$type] ?></svg>
                <p><?= $e((string) ($item['message'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
