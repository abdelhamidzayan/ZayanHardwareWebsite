<div class="page-head"><h1 class="page-head__title"><?= $e($test['title']) ?></h1><p class="muted">المهارة: <?= $e($test['skill_name']) ?> · الإصدار <?= $e($test['version']) ?></p></div>
<div class="container">
    <section class="card" style="padding:1rem">
        <p class="muted">المحاولات المتبقية: <?= (int) $state['attempts_remaining'] ?> · درجة النجاح: <?= $e((string) $test['pass_score']) ?>%</p>
        <?php if (!$state['can_attempt']) : ?>
            <p>لا يمكنك بدء محاولة الآن. <?php if ($state['wait_minutes'] > 0) : ?>الوقت المتبقي: <?= (int) $state['wait_minutes'] ?> دقيقة.<?php endif; ?></p>
            <a class="btn btn--secondary" href="<?= $e($baseUrl) ?>/skills/tests">العودة للاختبارات</a>
        <?php else : ?>
            <form method="post" action="<?= $e($baseUrl) ?>/skills/tests/<?= (int) $test['id'] ?>/submit" class="issue-form">
                <?= $csrf() ?>
                <?php foreach ($test['questions'] as $question) : ?>
                    <fieldset class="field">
                        <legend class="field__label"><?= $e((string) $question['question']) ?></legend>
                        <?php foreach ($question['options'] as $key => $option) : ?>
                            <label class="check"><input type="radio" name="answers[<?= (int) $question['index'] ?>]" value="<?= $e((string) $key) ?>"><span><?= $e((string) $option) ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
                <button class="btn btn--primary" type="submit">إرسال الإجابات</button>
            </form>
        <?php endif; ?>
    </section>
</div>
