<?php defined('AULA') || exit;
$mine = (int)$m['user_id'] === uid();
$deleted = $m['body'] === '[deleted]';
$canDelete = !$deleted && (is_teacher() || ($mine && time() - (int)$m['created_at'] < 900));
?>
<div class="msg<?= $mine ? ' msg-mine' : '' ?>" id="m<?= (int)$m['id'] ?>" data-id="<?= (int)$m['id'] ?>">
    <?php if (!$mine): ?><span class="avatar avatar-sm"><?= e(initials($m['author'] ?? '?')) ?></span><?php endif; ?>
    <div class="msg-bubble">
        <div class="msg-head">
            <strong><?= $mine ? 'Tú' : e($m['author'] ?? 'Cuenta eliminada') ?></strong>
            <?php if (!$mine && in_array($m['author_role'] ?? '', ['teacher', 'admin'], true)): ?><span class="tag tag-sm">Docente</span><?php endif; ?>
            <time datetime="<?= date('c', (int)$m['created_at']) ?>"><?= fmt_date((int)$m['created_at'], 'relative') ?></time>
            <?php if ($canDelete): ?>
                <?= post_button('messages/delete', ['id' => $m['id']], icon('trash'), 'icon-btn icon-sm msg-del', 'Borrar mensaje', '¿Borrar este mensaje?') ?>
            <?php endif; ?>
        </div>
        <?php if ($deleted): ?>
            <p class="msg-deleted"><?= icon('ban') ?> Mensaje borrado</p>
        <?php else: ?>
            <?php if ($m['body'] !== ''): ?><div class="msg-body"><?= text_to_html((string)$m['body']) ?></div><?php endif; ?>
            <?php if ($m['files']): ?>
                <ul class="msg-files">
                    <?php foreach ($m['files'] as $f): $isImg = in_array($f['mime'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true); ?>
                        <li>
                            <?php if ($isImg): ?>
                                <a href="<?= e(Files::url($f)) ?>" target="_blank"><img src="<?= e(Files::url($f)) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></a>
                            <?php else: ?>
                                <a class="file-chip" href="<?= e(Files::url($f, !in_array($f['mime'], Files::INLINE, true))) ?>" target="_blank">
                                    <?= icon($f['mime'] === 'application/pdf' ? 'file-pdf' : (str_starts_with($f['mime'], 'audio/') ? 'file-audio' : 'paperclip')) ?>
                                    <?= e($f['name']) ?> <small><?= fmt_size((int)$f['size']) ?></small>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
