<?php
defined('AULA') || exit;

/**
 * Tipo de recurso de un curso (PDF, audio, SCORM...).
 * Cada extensión puede registrar tipos nuevos con ResourceTypes::register().
 *
 * Los datos propios del tipo se guardan como JSON en resources.data.
 */
abstract class ResourceType
{
    /** Identificador corto (pdf, audio, scorm...). */
    abstract public function id(): string;

    /** Nombre visible ("Documento PDF"). */
    abstract public function label(): string;

    /** Icono de Font Awesome sin el prefijo fa- ("file-pdf"). */
    public function icon(): string
    {
        return 'file';
    }

    /** Frase corta que se ve al elegir el tipo. */
    public function help(): string
    {
        return '';
    }

    /** Campos HTML propios del formulario de edición. */
    public function form(array $resource): string
    {
        return '';
    }

    /**
     * Procesa el formulario y devuelve los datos a guardar.
     * $resource ya existe en la base de datos (tiene id).
     * $isNew indica si se está creando. Lanza UserError si algo no es válido.
     */
    public function save(array $resource, bool $isNew): array
    {
        return $resource['data'];
    }

    /** HTML principal de la página del recurso. */
    public function render(array $resource, array $course): string
    {
        return '';
    }

    /** Si devuelve una URL, al abrir el recurso se redirige allí (enlaces, descargas). */
    public function redirect(array $resource): ?string
    {
        return null;
    }

    /** Si el recurso se abre en una pestaña nueva desde el índice del curso. */
    public function opensNewTab(array $resource): bool
    {
        return false;
    }

    /** Texto breve bajo el título en el índice del curso (tamaño, duración...). */
    public function meta(array $resource): string
    {
        return '';
    }

    /**
     * Estado del alumno en este recurso para el índice del curso:
     * null (sin información) o ['done' => bool, 'label' => '...'].
     */
    public function status(array $resource, int $userId, ?array $view): ?array
    {
        return $view ? ['done' => true, 'label' => 'Visto'] : null;
    }

    /** Bloque extra para el profesorado en la página del recurso (seguimiento...). */
    public function teacherPanel(array $resource, array $course): string
    {
        return '';
    }

    /** Al duplicar un curso: devuelve los datos para la copia. */
    public function duplicate(array $resource, array $copy): array
    {
        return $resource['data'];
    }

    /** Al borrar el recurso (los archivos de contexto 'resource' se borran solos). */
    public function delete(array $resource): void
    {
    }

    /** Si la página del recurso debe usar todo el ancho. */
    public function wide(): bool
    {
        return false;
    }
}

class ResourceTypes
{
    private static array $types = [];

    public static function register(ResourceType $type): void
    {
        self::$types[$type->id()] = $type;
    }

    public static function get(string $id): ?ResourceType
    {
        return self::$types[$id] ?? null;
    }

    /** @return ResourceType[] */
    public static function all(): array
    {
        return apply_filters('resource_types', self::$types);
    }
}

/**
 * Tipo de reserva para recursos cuya extensión está desactivada.
 */
class MissingResourceType extends ResourceType
{
    private string $missing;

    public function __construct(string $missing)
    {
        $this->missing = $missing;
    }

    public function id(): string
    {
        return $this->missing;
    }

    public function label(): string
    {
        return 'No disponible';
    }

    public function icon(): string
    {
        return 'circle-question';
    }

    public function render(array $resource, array $course): string
    {
        return '<div class="alert alert-warn">Este contenido no está disponible ahora mismo.</div>';
    }
}
