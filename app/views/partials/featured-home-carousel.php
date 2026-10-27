<?php if (!empty($featuredIssues)) : ?>
<section class="featured-carousel" data-featured-carousel aria-label="منشورات مختارة من الإدارة">
    <div class="featured-carousel__heading">
        <div>
            <span class="featured-carousel__eyebrow">مختار من الإدارة</span>
            <h2 class="featured-carousel__title">من على طاولة الفنيين</h2>
        </div>
        <span class="featured-carousel__live"><span></span> تحديث تلقائي</span>
    </div>
    <div class="featured-carousel__stage">
        <?php foreach ($featuredIssues as $index => $featured) : ?>
            <a class="featured-slide<?= $index === 0 ? ' is-active' : '' ?>"
               href="<?= $e($baseUrl) ?>/issues/<?= (int) $featured['id'] ?>"
               data-featured-slide
               aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
               tabindex="<?= $index === 0 ? '0' : '-1' ?>">
                <div class="featured-slide__content">
                    <div class="featured-slide__meta">
                        <span class="tag tech"><?= $e($featured['device_model']) ?></span>
                        <span><?= $e($featured['category']) ?></span>
                    </div>
                    <h3 class="featured-slide__title"><?= $e($featured['title']) ?></h3>
                    <p class="featured-slide__excerpt"><?= $e(mb_strimwidth(trim(strip_tags($featured['description'])), 0, 240, '…', 'UTF-8')) ?></p>
                    <div class="featured-slide__footer">
                        <span><?= $e($featured['author_name']) ?></span>
                        <span><?= (int) $featured['reply_count'] ?> رد</span>
                        <span class="featured-slide__read">عرض الحالة <span aria-hidden="true">←</span></span>
                    </div>
                </div>
                <span class="featured-slide__number" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="featured-carousel__progress" aria-hidden="true"><span data-featured-progress></span></div>
</section>
<?php endif; ?>
