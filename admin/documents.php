<?php
/** Documents remis aux clients : règlement intérieur, contrat, état des lieux… (PDF ou Word). */
require __DIR__ . '/_init.php';
require_perm('contenu');

/** Vérifie un fichier envoyé et le range dans /documents sous un nom propre. Retourne le nom, ou lève une erreur. */
function store_document(array $f, string $title): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'Fichier trop lourd pour l’hébergement.' : 'Aucun fichier reçu.');
    }
    if ($f['size'] > 15 * 1024 * 1024) {
        throw new RuntimeException('Fichier trop lourd (15 Mo maximum).');
    }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ok = ($ext === 'pdf' && $mime === 'application/pdf')
        || ($ext === 'docx' && in_array($mime, [DOC_TYPES['docx'], 'application/zip', 'application/octet-stream'], true) && str_starts_with((string)file_get_contents($f['tmp_name'], false, null, 0, 2), 'PK'));
    if (!$ok) {
        throw new RuntimeException('Format refusé : envoyez un PDF (recommandé) ou un fichier Word .docx.');
    }
    if (!is_dir(DOC_DIR)) {
        @mkdir(DOC_DIR, 0775, true);
    }
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: 'document')), '-') ?: 'document';
    $name = substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], DOC_DIR . '/' . $name)) {
        throw new RuntimeException('Impossible d’enregistrer le fichier (droits du dossier « documents »).');
    }
    @chmod(DOC_DIR . '/' . $name, 0644);
    return $name;
}

function doc_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Supprime un fichier du dossier documents s'il n'est plus utilisé par aucune ligne. */
function doc_unlink(string $filename): void
{
    $st = db()->prepare('SELECT COUNT(*) FROM documents WHERE filename = ?');
    $st->execute([$filename]);
    if (!(int)$st->fetchColumn() && $filename !== '' && basename($filename) === $filename) {
        @unlink(DOC_DIR . '/' . $filename);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = (string)($_POST['do'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
    $desc = mb_substr(trim((string)($_POST['description'] ?? '')), 0, 300);
    try {
        if ($do === 'add') {
            if ($title === '') {
                throw new RuntimeException('Indiquez le titre du document.');
            }
            $name = store_document($_FILES['file'] ?? [], $title);
            $pos = (int)db()->query('SELECT COALESCE(MAX(position), 0) + 1 FROM documents')->fetchColumn();
            db()->prepare('INSERT INTO documents(title, description, filename, position, visible, in_emails) VALUES(?,?,?,?,1,?)')
                ->execute([$title, $desc, $name, $pos, !empty($_POST['in_emails']) ? 1 : 0]);
            admin_log('Document ajouté : ' . $title);
            flash('Document « ' . $title . ' » ajouté.');
        } elseif ($d = doc_get($id)) {
            if ($do === 'save') {
                db()->prepare('UPDATE documents SET title = ?, description = ?, visible = ?, in_emails = ? WHERE id = ?')
                    ->execute([$title !== '' ? $title : $d['title'], $desc, !empty($_POST['visible']) ? 1 : 0, !empty($_POST['in_emails']) ? 1 : 0, $id]);
                if (!empty($_FILES['file']['name'])) {
                    $name = store_document($_FILES['file'], $title !== '' ? $title : $d['title']);
                    db()->prepare('UPDATE documents SET filename = ? WHERE id = ?')->execute([$name, $id]);
                    doc_unlink($d['filename']);
                    admin_log('Document remplacé : ' . $d['title']);
                }
                flash('Document enregistré.');
            } elseif ($do === 'delete') {
                db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
                doc_unlink($d['filename']);
                admin_log('Document supprimé : ' . $d['title']);
                flash('Document supprimé.');
            } elseif ($do === 'up' || $do === 'down') {
                $rows = db()->query('SELECT id FROM documents ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN);
                $i = array_search($id, array_map('intval', $rows), true);
                $j = $do === 'up' ? $i - 1 : $i + 1;
                if ($i !== false && isset($rows[$j])) {
                    [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
                    $upd = db()->prepare('UPDATE documents SET position = ? WHERE id = ?');
                    foreach ($rows as $k => $rid) {
                        $upd->execute([$k + 1, $rid]);
                    }
                }
            }
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('documents.php');
}

$docs = db()->query('SELECT * FROM documents ORDER BY position, id')->fetchAll();
admin_header('Documents', 'documents');
?>
<h1>Documents</h1>
<p class="intro">Règlement intérieur, contrat, état des lieux… Les clients les retrouvent sur la page <a href="../<?= e(u('conditions.php')) ?>#documents" target="_blank">Conditions de location</a>, dans leur espace « Ma réservation », et en lien dans l’e-mail de confirmation et le rappel avant l’événement (si la case « Dans les e-mails » est cochée).</p>
<div class="hint">Préférez le format <strong>PDF</strong> : il s’ouvre sur tous les téléphones sans application. Depuis Word : Fichier › Enregistrer sous › PDF.</div>

<?php foreach ($docs as $n => $d): $exists = is_file(DOC_DIR . '/' . $d['filename']); ?>
<form method="post" enctype="multipart/form-data" class="card"<?= (int)$d['visible'] ? '' : ' style="opacity:.75"' ?>>
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
    <strong>📄 <?php if ($exists): ?><a href="../<?= e(document_src($d)) ?>" target="_blank"><?= e($d['filename']) ?></a> <span class="muted">(<?= e(document_meta($d)) ?>)</span><?php else: ?><span style="color:#b42318">Fichier introuvable : <?= e($d['filename']) ?></span><?php endif; ?></strong>
    <span style="display:flex;gap:6px">
      <?php if ($n > 0): ?><button class="btn light sm" name="do" value="up" title="Monter">↑</button><?php endif; ?>
      <?php if ($n < count($docs) - 1): ?><button class="btn light sm" name="do" value="down" title="Descendre">↓</button><?php endif; ?>
    </span>
  </div>
  <?= field('title', 'Titre', $d['title']) ?>
  <?= field('description', 'Description courte (affichée sous le titre)', $d['description']) ?>
  <label class="field"><span>Remplacer le fichier (facultatif)</span><input type="file" name="file" accept=".pdf,.docx,application/pdf"></label>
  <div style="display:flex;gap:18px;flex-wrap:wrap;margin:6px 0 14px">
    <label><input type="checkbox" name="visible" value="1"<?= (int)$d['visible'] ? ' checked' : '' ?>> Visible par les clients</label>
    <label><input type="checkbox" name="in_emails" value="1"<?= (int)$d['in_emails'] ? ' checked' : '' ?>> Dans les e-mails (confirmation, rappel)</label>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button class="btn" name="do" value="save">Enregistrer</button>
    <button class="btn danger" name="do" value="delete" onclick="return confirm('Supprimer ce document ?')">Supprimer</button>
  </div>
</form>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <h2>Ajouter un document</h2>
  <?= field('title', 'Titre', '') ?>
  <?= field('description', 'Description courte', '') ?>
  <label class="field"><span>Fichier PDF (ou Word .docx), 15 Mo maximum</span><input type="file" name="file" accept=".pdf,.docx,application/pdf" required></label>
  <label style="display:block;margin:6px 0 14px"><input type="checkbox" name="in_emails" value="1" checked> Dans les e-mails (confirmation, rappel)</label>
  <button class="btn" name="do" value="add">Ajouter</button>
</form>
<?php admin_footer();
