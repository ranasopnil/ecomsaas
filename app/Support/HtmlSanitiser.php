<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Cleans the HTML a shopkeeper writes in the description designer.
 *
 * Merchants never write code. Everything they type is treated as text and
 * layout only: anything that could run — scripts, event handlers, javascript:
 * links, embedded frames — is removed before it is ever stored, and again
 * nothing is executed when it is shown.
 */
class HtmlSanitiser
{
    /** @var array<string, array<int, string>> tag => attributes it may keep */
    protected const ALLOWED = [
        'p' => ['style'],
        'br' => [],
        'hr' => [],
        'strong' => [], 'b' => [],
        'em' => [], 'i' => [],
        'u' => [], 's' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'style'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
        'div' => ['style'], 'span' => ['style'],
        'small' => [], 'sup' => [], 'sub' => [],
    ];

    /** Style rules a merchant may set. Anything else is dropped. */
    protected const ALLOWED_STYLES = [
        'text-align', 'color', 'background-color', 'font-size',
        'font-weight', 'font-style', 'text-decoration', 'width', 'max-width',
        'margin', 'padding', 'border-radius',
    ];

    public function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitiser-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sanitiser-root');

        if ($root === null) {
            return null;
        }

        $this->stripDangerousElements($document);
        $this->cleanNode($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        return $output === '' ? null : $output;
    }

    protected function stripDangerousElements(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta', 'base'] as $tag) {
            foreach (iterator_to_array($xpath->query('//'.$tag) ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    protected function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // Comments and anything else that is not text or a tag.
                $child->parentNode?->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Keep what the shopkeeper wrote, throw away the tag around it.
                $this->cleanNode($child);
                $this->unwrap($child);

                continue;
            }

            $this->cleanAttributes($child, self::ALLOWED[$tag]);
            $this->cleanNode($child);
        }
    }

    /**
     * @param  array<int, string>  $allowedAttributes
     */
    protected function cleanAttributes(DOMElement $element, array $allowedAttributes): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;

            if (! in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            $cleaned = match ($name) {
                'href' => $this->cleanUrl($value, ['http', 'https', 'mailto', 'tel']),
                'src' => $this->cleanUrl($value, ['http', 'https'], allowInlineImage: true),
                'style' => $this->cleanStyle($value),
                'target' => $value === '_blank' ? '_blank' : null,
                'width', 'height' => ctype_digit($value) ? $value : null,
                default => $value,
            };

            if ($cleaned === null || $cleaned === '') {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            $element->setAttribute($name, $cleaned);
        }

        // A link opening in a new tab must not hand the new page control of ours.
        if (strtolower($element->tagName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        // A link with nowhere to go is just text.
        if (strtolower($element->tagName) === 'a' && ! $element->hasAttribute('href')) {
            $this->unwrap($element);
        }

        // A picture with no source is nothing at all.
        if (strtolower($element->tagName) === 'img' && ! $element->hasAttribute('src')) {
            $element->parentNode?->removeChild($element);
        }
    }

    /**
     * @param  array<int, string>  $schemes
     */
    protected function cleanUrl(string $url, array $schemes, bool $allowInlineImage = false): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Anything that hides a scheme behind whitespace or control characters.
        $flattened = strtolower(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');

        if ($allowInlineImage && str_starts_with($flattened, 'data:image/')) {
            return $url;
        }

        if (str_starts_with($flattened, 'javascript:')
            || str_starts_with($flattened, 'vbscript:')
            || str_starts_with($flattened, 'data:')) {
            return null;
        }

        // Same-page and same-shop links.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === '') {
            return $url;
        }

        return in_array($scheme, $schemes, true) ? $url : null;
    }

    protected function cleanStyle(string $style): ?string
    {
        $kept = [];

        foreach (explode(';', $style) as $rule) {
            if (! str_contains($rule, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $rule, 2));
            $property = strtolower($property);
            $value = trim($value);

            if (! in_array($property, self::ALLOWED_STYLES, true)) {
                continue;
            }

            // No urls, no expressions, no escapes hiding a scheme.
            if (preg_match('/url\s*\(|expression|javascript|@import|\\/i', $value)) {
                continue;
            }

            $kept[] = $property.': '.$value;
        }

        return $kept === [] ? null : implode('; ', $kept);
    }

    protected function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
