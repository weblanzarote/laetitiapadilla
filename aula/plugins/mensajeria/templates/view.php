<?php defined('AULA') || exit;
$others = array_values(array_filter($people, fn($p) => $p['id'] !== uid()));
$teacherOther = null;
foreach ($others as $o) {
    if (in_array($o['role'], ['teacher', 'admin'], true)) { $teacherOther = $o; break; }
}
$author = Db::one('SELECT id, name FROM users WHERE id = ?', [$conv['created_by']]);
?>
<div class="thread-head card">
    <div>
        <p class="kicker">
            <?php if ($conv['kind'] === 'announcement'): ?>
                <?= icon('bullhorn') ?> Aviso<?= $course ? ' · ' . e($course['title']) : '' ?>
            <?php elseif ($conv['kind'] === 'group'): ?>
                <?= icon('users') ?> Conversación de grupo
            <?php else: ?>
                <?= icon('lock') ?> Conversación privada
            <?php endif; ?>
        </p>
        <h1 class="page-title"><?= e($conv['subject']) ?></h1>
        <p class="muted">
            <?php if ($conv['kind'] === 'announcement'): ?>
                Enviado a <?= plural(count($others), 'persona', 'personas') ?>
            <?php else: ?>
                Con <?= e($title) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-row">
        <?= post_button('messages/archive', ['id' => $conv['id'], 'on' => $archived ? 0 : 1], icon($archived ? 'inbox' : 'box-archive') . ($archived ? ' Devolver a la bandeja' : ' Archivar'), 'btn btn-sm btn-ghost') ?>
    </div>
</div>

<?php if ($conv['kind'] === 'group' && is_teacher()): ?>
    <details class="card people">
        <summary><?= icon('users') ?> Participantes (<?= count($people) ?>)</summary>
        <p><?= implode(', ', array_map(fn($p) => e($p['name']), $people)) ?></p>
    </details>
<?php endif; ?>

<div class="thread" id="thread" data-poll-url="<?= e(url('messages/poll', ['id' => $conv['id']])) ?>" data-poll-every="<?= max(5, (int)Messaging::opt('msg_poll_seconds')) ?>">
    <?php foreach ($messages as $m): ?>
        <?= View::fetch('mensajeria:_message', ['m' => $m, 'conv' => $conv]) ?>
    <?php endforeach; ?>
</div>
<div id="bottom"></div>

<?php if ($canReply): ?>
    <form method="post" action="<?= url('messages/reply') ?>" enctype="multipart/form-data" class="card reply-box form" data-reply-form>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$conv['id'] ?>">
        <div class="field">
            <label for="body" class="sr-only">Tu respuesta</label>
            <textarea id="body" name="body" rows="3" placeholder="Escribe tu respuesta…" data-autosize data-ctrl-enter></textarea>
        </div>
        <div class="reply-actions">
            <?php if ((int)Messaging::opt('msg_attachments') === 1): ?>
                <label class="btn btn-ghost btn-sm file-pick"><?= icon('paperclip') ?> Adjuntar
                    <input type="file" name="attachments[]" multiple data-chunked="attachments" data-chunk-size="<?= Files::chunkBytes() ?>" data-max-size="<?= Files::maxBytes() ?>" hidden>
                </label>
            <?php endif; ?>
            <span class="file-names muted small" data-file-names></span>
            <button type="submit" class="btn btn-primary"><?= icon('paper-plane') ?> Enviar</button>
        </div>
        <small class="hint">Ctrl + Intro para enviar.</small>
    </form>
<?php elseif ($conv['kind'] === 'announcement' && !is_teacher() && $author): ?>
    <div class="card reply-note">
        <p>Los avisos son para todo el curso. ¿Tienes alguna duda?</p>
        <a class="btn btn-primary" href="<?= url('messages/new', ['to' => $author['id'], 'subject' => 'Re: ' . $conv['subject']]) ?>"><?= icon('reply') ?> Responder en privado a <?= e(explode(' ', $author['name'])[0]) ?></a>
    </div>
<?php else: ?>
    <div class="card reply-note muted">No se puede responder en esta conversación.</div>
<?php endif; ?>
