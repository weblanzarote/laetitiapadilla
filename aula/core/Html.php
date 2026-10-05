<?php
defined('AULA') || exit;

/**
 * Limpia el HTML que viene del editor de textos: solo deja etiquetas y
 * atributos de una lista permitida (nada de scripts, estilos, eventos...).
 */
class Html
{
    const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'sub' => [], 'sup' => [], 'span' => [], 'hr' => [], 'blockquote' => [], 'pre' => [], 'code' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'target'], 'img' => ['src', 'alt'], 'iframe' => ['src'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    const DROP = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math', 'template'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '' || preg_match('~^(<p>(<br\s*/?>|\s|&nbsp;)*</p>)+$~i', $html)) {
            return '';
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        self::walk($body);
        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child);
            if (!isset(self::ALLOWED[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::cleanAttributes($child, $tag);
            if ($tag === 'iframe' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
            }
            if ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = false;
            if ($name === 'class') {
                $classes = array_filter(preg_split('/\s+/', $value), fn($c) => preg_match('/^ql-[a-z0-9-]+$/', $c));
                if ($classes) {
                    $el->setAttribute('class', implode(' ', $classes));
                    continue;
                }
            } elseif (in_array($name, $allowed, true)) {
                $keep = true;
                if ($name === 'href') {
                    $keep = (bool)preg_match('~^(https?://|mailto:|tel:|#|/)~i', $value);
                } elseif ($name === 'src' && $tag === 'img') {
                    $keep = (bool)preg_match('~^(https://|/)~i', $value);
                } elseif ($name === 'src' && $tag === 'iframe') {
                    $keep = (bool)preg_match('~^https://(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/)~i', $value);
                } elseif ($name === 'target') {
                    $keep = $value === '_blank';
                }
            }
            if (!$keep) {
                $el->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'iframe') {
            $el->setAttribute('allowfullscreen', 'allowfullscreen');
            $el->setAttribute('frameborder', '0');
        }
    }
}
