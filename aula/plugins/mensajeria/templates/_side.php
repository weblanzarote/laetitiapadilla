<?php defined('AULA') || exit; ?>
<div class="card">
    <h2 class="card-title"><?= icon('comments') ?> Mensajes
        <?php if ($unread): ?><span class="badge badge-count"><?= (int)$unread ?></span><?php endif; ?>
    </h2>
    <?php if (!$convs): ?>
        <p class="muted">No hay mensajes.</p>
    <?php else: ?>
        <ul class="mini-conv">
            <?php foreach ($convs as $c): ?>
                <li class="<?= (int)$c['unread'] ? 'is-unread' : '' ?>">
                    <a href="<?= url('messages/view', ['id' => $c['id']]) ?>">
                        <strong><?= e(Messaging::title($c, $c['people'], uid())) ?></strong>
                        <span><?= e($c['subject']) ?></span>
                        <small class="muted"><?= fmt_date((int)$c['last_message_at'], 'relative') ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="btn-row">
        <a class="link-more" href="<?= url('messages') ?>">Ir a mensajes</a>
        <?php if (Messaging::recipientsFor(user())): ?><a class="link-more" href="<?= url('messages/new') ?>"><?= icon('pen-to-square') ?> Escribir</a><?php endif; ?>
    </div>
</div>
