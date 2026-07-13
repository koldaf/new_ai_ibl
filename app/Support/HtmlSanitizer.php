<?php

namespace App\Support;

use DOMDocument;
use DOMElement;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><u><s><sub><sup><a><ul><ol><li><blockquote><h1><h2><h3><h4><code><pre><table><thead><tbody><tr><th><td><img>';

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $clean = strip_tags($html, self::ALLOWED_TAGS);

        if ($clean === '') {
            return '';
        }

        $wrapped = '<div id="root">' . $clean . '</div>';

        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $root = $dom->getElementById('root');

        if (!$root) {
            return trim($clean);
        }

        self::sanitizeElementTree($root);

        $output = '';
        foreach ($root->childNodes as $childNode) {
            $output .= $dom->saveHTML($childNode);
        }

        return trim($output);
    }

    public static function sanitizeNullable(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = self::sanitize($html);

        return $clean !== '' ? $clean : null;
    }

    private static function sanitizeElementTree(DOMElement $element): void
    {
        $href = '';
        $target = '';
        $src = '';
        $alt = '';
        $title = '';
        $width = '';
        $height = '';
        $colspan = '';
        $rowspan = '';

        if ($element->tagName === 'a') {
            $href = trim((string) $element->getAttribute('href'));
            $target = trim((string) $element->getAttribute('target'));
        } elseif ($element->tagName === 'img') {
            $src = trim((string) $element->getAttribute('src'));
            $alt = trim((string) $element->getAttribute('alt'));
            $title = trim((string) $element->getAttribute('title'));
            $width = trim((string) $element->getAttribute('width'));
            $height = trim((string) $element->getAttribute('height'));
        } elseif ($element->tagName === 'td' || $element->tagName === 'th') {
            $colspan = trim((string) $element->getAttribute('colspan'));
            $rowspan = trim((string) $element->getAttribute('rowspan'));
        }

        if ($element->hasAttributes()) {
            $toRemove = [];
            foreach ($element->attributes as $attribute) {
                $toRemove[] = $attribute->name;
            }

            foreach ($toRemove as $name) {
                $element->removeAttribute($name);
            }
        }

        if ($element->tagName === 'a') {
            if (self::isSafeHref($href)) {
                $element->setAttribute('href', $href);
            }

            if ($target === '_blank') {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        } elseif ($element->tagName === 'img') {
            if (!self::isSafeImageSrc($src)) {
                $element->parentNode?->removeChild($element);
                return;
            }

            $element->setAttribute('src', $src);

            if ($alt !== '') {
                $element->setAttribute('alt', $alt);
            }

            if ($title !== '') {
                $element->setAttribute('title', $title);
            }

            if (preg_match('/^\d{1,4}$/', $width)) {
                $element->setAttribute('width', $width);
            }

            if (preg_match('/^\d{1,4}$/', $height)) {
                $element->setAttribute('height', $height);
            }
        } elseif ($element->tagName === 'td' || $element->tagName === 'th') {
            if (preg_match('/^[1-9]\d?$/', $colspan)) {
                $element->setAttribute('colspan', $colspan);
            }

            if (preg_match('/^[1-9]\d?$/', $rowspan)) {
                $element->setAttribute('rowspan', $rowspan);
            }
        }

        foreach ($element->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                self::sanitizeElementTree($childNode);
            }
        }
    }

    private static function isSafeHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return true;
        }

        return (bool) preg_match('/^(https?:|mailto:)/i', $href);
    }

    private static function isSafeImageSrc(string $src): bool
    {
        if ($src === '') {
            return false;
        }

        if (str_starts_with($src, '/')) {
            return true;
        }

        if ((bool) preg_match('/^https?:\/\//i', $src)) {
            return true;
        }

        return (bool) preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $src);
    }
}
