<?php
defined('AULA') || exit;

/** Cursos, apartados (sections), recursos e inscripciones. */
class Courses
{
    // ─── Cursos ───────────────────────────────────────────────────

    public static function find(int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM courses WHERE id = ?', [$id]) : null;
    }

    public static function findByCode(string $code): ?array
    {
        $code = mb_strtoupper(trim($code));
        return $code === '' ? null : Db::one('SELECT * FROM courses WHERE enrol_code = ?', [$code]);
    }

    public static function all(): array
    {
        return Db::all('SELECT * FROM courses ORDER BY sort, title');
    }

    public static function forUser(array $u): array
    {
        if (is_teacher($u)) {
            return self::all();
        }
        return Db::all(
            'SELECT c.* FROM courses c JOIN enrolments e ON e.course_id = c.id WHERE e.user_id = ? AND c.visible = 1 ORDER BY c.sort, c.title',
            [$u['id']]
        );
    }

    public static function isEnrolled(int $userId, int $courseId): bool
    {
        return (bool)Db::val('SELECT COUNT(*) FROM enrolments WHERE user_id = ? AND course_id = ?', [$userId, $courseId]);
    }

    public static function canView(array $course, ?array $u): bool
    {
        if (!$u) {
            return false;
        }
        if (is_teacher($u)) {
            return true;
        }
        return (int)$course['visible'] === 1 && self::isEnrolled((int)$u['id'], (int)$course['id']);
    }

    public static function enrol(int $userId, int $courseId): bool
    {
        if (self::isEnrolled($userId, $courseId)) {
            return false;
        }
        Db::insert('enrolments', ['course_id' => $courseId, 'user_id' => $userId, 'created_at' => time()]);
        do_action('user_enrolled', $userId, $courseId);
        return true;
    }

    public static function unenrol(int $userId, int $courseId): void
    {
        Db::delete('enrolments', 'user_id = ? AND course_id = ?', [$userId, $courseId]);
        do_action('user_unenrolled', $userId, $courseId);
    }

    public static function students(int $courseId): array
    {
        return Db::all(
            "SELECT u.*, e.created_at AS enrolled_at FROM users u JOIN enrolments e ON e.user_id = u.id
             WHERE e.course_id = ? ORDER BY u.name",
            [$courseId]
        );
    }

    public static function newCode(string $title): string
    {
        $ascii = function_exists('iconv') ? (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) : $title;
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ascii));
        $base = substr($base, 0, 6) ?: 'CURSO';
        do {
            $code = $base . '-' . random_code(4);
        } while (self::findByCode($code));
        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        $code = mb_strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9-]{4,40}$/', $code)) {
            throw new UserError('El código solo puede tener letras sin acentos, números y guiones (de 4 a 40 caracteres).');
        }
        return $code;
    }

    // ─── Portada ──────────────────────────────────────────────────

    /** Diseños de serie para cursos sin imagen: clave => [nombre, frase decorativa]. */
    const COVERS = [
        'marino' => ['Azul marino', 'Bonjour'],
        'coral' => ['Coral', 'Bienvenue'],
        'tricolor' => ['Tricolor', 'Allons-y !'],
        'lavanda' => ['Lavanda', 'On y va'],
        'mar' => ['Mediterráneo', 'Bon voyage'],
    ];

    const COVER_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Diseño de serie del curso (si no se eligió, uno fijo según su id). */
    public static function coverStyle(array $c): string
    {
        $style = (string)($c['cover_style'] ?? '');
        if (isset(self::COVERS[$style])) {
            return $style;
        }
        $keys = array_keys(self::COVERS);
        return $keys[(int)$c['id'] % count($keys)];
    }

    public static function coverFile(array $c): ?array
    {
        return Files::find((int)($c['cover_file_id'] ?? 0));
    }

    /** HTML de la portada: la imagen subida o el diseño de serie. */
    public static function cover(array $c, string $class = ''): string
    {
        $f = self::coverFile($c);
        if ($f) {
            return '<div class="cover cover-image ' . e($class) . '"><img src="' . e(Files::url($f)) . '" alt="" loading="lazy"></div>';
        }
        $style = self::coverStyle($c);
        return '<div class="cover cover-' . e($style) . ' ' . e($class) . '" aria-hidden="true"><span class="cover-word">' . e(self::COVERS[$style][1]) . '</span></div>';
    }

    /** Guarda o quita la imagen de portada (después de guardar el curso). */
    public static function saveCover(int $courseId, ?array $incoming, bool $remove): void
    {
        $c = self::find($courseId);
        $old = (int)$c['cover_file_id'];
        $new = $old;
        if ($incoming) {
            $new = Files::store(Files::shrinkImage($incoming, 1800), 'course', $courseId);
        } elseif ($remove) {
            $new = 0;
        }
        if ($new !== $old) {
            Db::update('courses', ['cover_file_id' => $new], 'id = ?', [$courseId]);
            if ($old) {
                Files::delete($old);
            }
        }
    }

    // ─── Apartados ────────────────────────────────────────────────

    public static function section(int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM sections WHERE id = ?', [$id]) : null;
    }

    public static function sections(int $courseId, bool $includeHidden): array
    {
        return Db::all(
            'SELECT * FROM sections WHERE course_id = ?' . ($includeHidden ? '' : ' AND visible = 1') . ' ORDER BY sort, id',
            [$courseId]
        );
    }

    // ─── Recursos ─────────────────────────────────────────────────

    private static function decode(array $r): array
    {
        $r['data'] = json_decode((string)($r['data'] ?: '{}'), true) ?: [];
        return $r;
    }

    public static function resource(int $id): ?array
    {
        $r = $id ? Db::one('SELECT * FROM resources WHERE id = ?', [$id]) : null;
        return $r ? self::decode($r) : null;
    }

    /** Recursos del curso (visibles en apartados visibles si $includeHidden es false). */
    public static function resources(int $courseId, bool $includeHidden): array
    {
        $sql = 'SELECT r.* FROM resources r JOIN sections s ON s.id = r.section_id WHERE r.course_id = ?';
        if (!$includeHidden) {
            $sql .= ' AND r.visible = 1 AND s.visible = 1';
        }
        return array_map([self::class, 'decode'], Db::all($sql . ' ORDER BY s.sort, s.id, r.sort, r.id', [$courseId]));
    }

    public static function type(array $resource): ResourceType
    {
        return ResourceTypes::get($resource['type']) ?? new MissingResourceType($resource['type']);
    }

    public static function canViewResource(array $res, array $u): bool
    {
        $course = self::find((int)$res['course_id']);
        if (!$course || !self::canView($course, $u)) {
            return false;
        }
        if (is_teacher($u)) {
            return true;
        }
        $section = self::section((int)$res['section_id']);
        return (int)$res['visible'] === 1 && $section && (int)$section['visible'] === 1;
    }

    public static function recordView(array $res, int $userId): void
    {
        $now = time();
        $row = Db::one('SELECT id FROM resource_views WHERE resource_id = ? AND user_id = ?', [$res['id'], $userId]);
        if ($row) {
            Db::q('UPDATE resource_views SET last_at = ?, views = views + 1 WHERE id = ?', [$now, $row['id']]);
        } else {
            Db::insert('resource_views', ['resource_id' => $res['id'], 'user_id' => $userId, 'first_at' => $now, 'last_at' => $now, 'views' => 1]);
        }
    }

    /** resource_id => fila de resource_views del alumno en el curso. */
    public static function views(int $courseId, int $userId): array
    {
        $rows = Db::all(
            'SELECT v.* FROM resource_views v JOIN resources r ON r.id = v.resource_id WHERE r.course_id = ? AND v.user_id = ?',
            [$courseId, $userId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['resource_id']] = $row;
        }
        return $out;
    }

    /** [hechos, total] de los recursos visibles del curso para un alumno. */
    public static function progress(int $courseId, int $userId): array
    {
        $resources = self::resources($courseId, false);
        $views = self::views($courseId, $userId);
        $done = 0;
        foreach ($resources as $r) {
            $st = self::type($r)->status($r, $userId, $views[(int)$r['id']] ?? null);
            if ($st && !empty($st['done'])) {
                $done++;
            }
        }
        return [$done, count($resources)];
    }

    public static function deleteResource(array $res): void
    {
        self::type($res)->delete($res);
        Files::deleteContext('resource', (int)$res['id']);
        Db::delete('resource_views', 'resource_id = ?', [$res['id']]);
        Db::delete('resources', 'id = ?', [$res['id']]);
        do_action('resource_deleted', $res);
    }

    public static function deleteSection(int $sectionId): void
    {
        foreach (Db::all('SELECT id FROM resources WHERE section_id = ?', [$sectionId]) as $row) {
            self::deleteResource(self::resource((int)$row['id']));
        }
        Db::delete('sections', 'id = ?', [$sectionId]);
    }

    public static function delete(int $courseId): void
    {
        foreach (self::sections($courseId, true) as $s) {
            self::deleteSection((int)$s['id']);
        }
        Files::deleteContext('course', $courseId);
        Db::delete('enrolments', 'course_id = ?', [$courseId]);
        Db::delete('courses', 'id = ?', [$courseId]);
        do_action('course_deleted', $courseId);
    }

    /** Duplica un curso (apartados y recursos, sin alumnos). Devuelve el id nuevo. */
    public static function duplicate(int $courseId): int
    {
        $c = self::find($courseId);
        $now = time();
        $newId = Db::insert('courses', [
            'title' => mb_substr($c['title'] . ' (copia)', 0, 200),
            'summary' => $c['summary'],
            'enrol_code' => self::newCode($c['title']),
            'enrol_open' => 0,
            'visible' => 0,
            'sort' => (int)$c['sort'] + 1,
            'cover_style' => (string)$c['cover_style'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ((int)$c['cover_file_id']) {
            Db::update('courses', ['cover_file_id' => Files::copy((int)$c['cover_file_id'], 'course', $newId)], 'id = ?', [$newId]);
        }
        foreach (self::sections($courseId, true) as $s) {
            self::duplicateSection((int)$s['id'], $newId, $s['title'], (int)$s['sort']);
        }
        do_action('course_duplicated', $courseId, $newId);
        return $newId;
    }

    /** Copia un apartado con sus contenidos (y archivos) en $courseId. Devuelve el id nuevo. */
    public static function duplicateSection(int $sectionId, int $courseId, string $title, int $sort): int
    {
        $s = self::section($sectionId);
        $now = time();
        $newSection = Db::insert('sections', [
            'course_id' => $courseId, 'title' => mb_substr($title, 0, 200), 'summary' => $s['summary'], 'sort' => $sort, 'visible' => $s['visible'],
        ]);
        foreach (Db::all('SELECT id FROM resources WHERE section_id = ? ORDER BY sort, id', [$sectionId]) as $row) {
            $r = self::resource((int)$row['id']);
            $copyId = Db::insert('resources', [
                'course_id' => $courseId, 'section_id' => $newSection, 'type' => $r['type'], 'title' => $r['title'],
                'description' => $r['description'], 'data' => '{}', 'sort' => $r['sort'], 'visible' => $r['visible'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
            // Copia los archivos y actualiza las referencias file_id del JSON.
            $map = [];
            foreach (Files::forContext('resource', (int)$r['id']) as $f) {
                $map[(int)$f['id']] = Files::copy((int)$f['id'], 'resource', $copyId);
            }
            $data = $r['data'];
            if (isset($data['file_id']) && isset($map[(int)$data['file_id']])) {
                $data['file_id'] = $map[(int)$data['file_id']];
            }
            $copy = self::resource($copyId);
            $r['data'] = $data;
            $data = self::type($r)->duplicate($r, $copy);
            Db::update('resources', ['data' => json_encode($data, JSON_UNESCAPED_UNICODE)], 'id = ?', [$copyId]);
        }
        return $newSection;
    }

    /** Vuelve a numerar el orden (10, 20, 30…) de las filas de un grupo. */
    public static function renumber(string $table, string $scopeCol, int $scopeId): void
    {
        $ids = Db::col("SELECT id FROM $table WHERE $scopeCol = ? ORDER BY sort, id", [$scopeId]);
        foreach ($ids as $i => $rid) {
            Db::q("UPDATE $table SET sort = ? WHERE id = ?", [($i + 1) * 10, (int)$rid]);
        }
    }

    /** Mueve una fila arriba (-1) o abajo (+1) dentro de su grupo. */
    public static function move(string $table, int $id, string $scopeCol, int $dir): void
    {
        $row = Db::one("SELECT * FROM $table WHERE id = ?", [$id]);
        if (!$row) {
            return;
        }
        $ids = Db::col("SELECT id FROM $table WHERE $scopeCol = ? ORDER BY sort, id", [$row[$scopeCol]]);
        $ids = array_map('intval', $ids);
        $pos = array_search($id, $ids, true);
        $swap = $pos + $dir;
        if ($pos === false || $swap < 0 || $swap >= count($ids)) {
            return;
        }
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
        foreach ($ids as $i => $rid) {
            Db::q("UPDATE $table SET sort = ? WHERE id = ?", [($i + 1) * 10, $rid]);
        }
    }

    public static function nextSort(string $table, string $scopeCol, int $scopeId): int
    {
        return (int)Db::val("SELECT COALESCE(MAX(sort), 0) FROM $table WHERE $scopeCol = ?", [$scopeId]) + 10;
    }
}
