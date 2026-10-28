<?php
/**
 * app/views/partials/issue-card.php — one fault card (Figma Home).
 * Needs: $issue (array), $e, $baseUrl.
 */
$solved    = !empty($issue['solved']);
$hasAudio  = !empty($issue['audio']);
$audioSrc  = !empty($issue['audio_src']) ? $baseUrl . $issue['audio_src'] : '';

// Deterministic waveform (32 bars) so the same card always looks the same.
$seed = (int) $issue['id'];
$bars = [];
for ($i = 0; $i < 32; $i++) {
    $seed   = ($seed * 1103515245 + 12345) & 0x7fffffff;
    $height = 6 + ($seed % 23);
    $bars[] = '<rect x="' . ($i * 5) . '" y="' . ((32 - $height) / 2) . '" width="3" height="' . $height . '" rx="1.5"/>';
}
?>
<article class="card issue-card">
    <header class="issue-card__head">
        <span class="avatar" aria-hidden="true"><?= $e($issue['initials']) ?></span>
        <div class="issue-card__who">
            <p class="issue-card__name">
                <?= $e($issue['author']) ?>
                <?php if (!empty($issue['expert'])) : ?>
                    <span class="badge">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                        خبير
                    </span>
                <?php endif; ?>
            </p>
            <p class="issue-card__meta"><?= $e($issue['role']) ?> · <?= $e($issue['time']) ?></p>
        </div>
    </header>

    <div class="issue-card__body">
        <?php if (($issue['content_type'] ?? 'help_request') === 'help_request' && !empty($issue['device'])) : ?>
            <span class="tag tech"><?= $e($issue['device']) ?></span>
        <?php else : ?>
            <span class="tag"><?= $e($issue['type_label'] ?? 'منشور فني') ?></span>
        <?php endif; ?>
        <h3 class="issue-card__title" dir="auto"><a class="issue-card__title-link" href="<?= $e($baseUrl) ?>/issues/<?= (int) $issue['id'] ?>"><?= $e($issue['title']) ?></a></h3>
        <p class="issue-card__excerpt" dir="auto"><?= $e($issue['excerpt']) ?></p>
    </div>

    <?php if ($hasAudio) : ?>
        <div class="audio" data-audio<?= $audioSrc !== '' ? ' data-src="' . $e($audioSrc) . '"' : '' ?>>
            <button class="audio__btn" type="button" aria-label="تشغيل الملاحظة الصوتية"<?= $audioSrc === '' ? ' disabled' : '' ?>>
                <svg class="icon audio__icon audio__icon--play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/></svg>
                <svg class="icon audio__icon audio__icon--pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5h3v14H8zM13 5h3v14h-3z" fill="currentColor" stroke="none"/></svg>
            </button>
            <svg class="audio__wave" viewBox="0 0 158 32" preserveAspectRatio="none" aria-hidden="true"><?= implode('', $bars) ?></svg>
            <span class="audio__time tech"><?= $e($issue['audio']) ?></span>
        </div>
    <?php endif; ?>

    <footer class="issue-card__foot">
        <span class="status<?= $solved ? ' status--solved' : '' ?>">
            <?= $solved ? 'تم الحل · ' : '' ?><?= $e(($issue['content_type'] ?? 'help_request') === 'help_request' ? ($issue['category'] ?? 'طلب مساعدة') : ($issue['type_label'] ?? 'منشور فني')) ?>
        </span>
        <span class="issue-card__comments">
            <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
            <?= (int) $issue['comments'] ?> تعليق
        </span>
    </footer>
</article>
