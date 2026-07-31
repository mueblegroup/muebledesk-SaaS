<?php

namespace App\Services;

class RichTextSanitizer
{
    private array $allowedTags = [
        'p', 'div', 'span', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'img',
    ];

    public function clean(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $html = mb_substr($html, 0, 10000);

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $this->cleanNode($document->documentElement);

        $output = '';
        foreach ($document->documentElement->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    private function cleanNode(\DOMNode $node): void
    {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            /** @var \DOMElement $element */
            $element = $node;
            $tag = strtolower($element->tagName);

            if (! in_array($tag, $this->allowedTags, true)) {
                $this->unwrapNode($element);
                return;
            }

            $this->cleanAttributes($element, $tag);
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanAttributes(\DOMElement $element, string $tag): void
    {
        $originalStyle = $element->getAttribute('style');
        $originalHref = $element->getAttribute('href');
        $originalSrc = $element->getAttribute('src');
        $originalAlt = $element->getAttribute('alt');

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if (in_array($tag, ['span', 'p', 'div'], true)) {
            $style = $this->safeColorStyle($originalStyle);
            if ($style) {
                $element->setAttribute('style', $style);
            }
        }

        if ($tag === 'a' && $this->safeUrl($originalHref, ['http', 'https', 'mailto', 'tel'])) {
            $element->setAttribute('href', $originalHref);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        if ($tag === 'img') {
            if ($this->safeUrl($originalSrc, ['https'])) {
                $element->setAttribute('src', $originalSrc);
                $element->setAttribute('alt', mb_substr($originalAlt ?: 'Item image', 0, 120));
                $element->setAttribute('style', 'max-width:160px;max-height:120px;display:block;margin-top:6px;');
            } else {
                $element->parentNode?->removeChild($element);
            }
        }
    }

    private function unwrapNode(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function safeColorStyle(string $style): ?string
    {
        if (preg_match('/color\s*:\s*(#[0-9a-fA-F]{3,6}|rgb\([0-9,\s]+\)|[a-zA-Z]+)\s*;?/i', $style, $matches)) {
            return 'color: '.$matches[1].';';
        }

        return null;
    }

    private function safeUrl(string $url, array $schemes): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $url !== '' && in_array($scheme, $schemes, true);
    }
}
