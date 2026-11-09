<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Models;

/**
 * Review text only ever holds paragraphs, line breaks and links (the editor's link-only toolbar), so anything else
 * is removed on the server too: the browser-side sanitiser is not a defence against a hand-made POST.
 */
class ReviewHtml
{
    private const DROPPED_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template'];

    public static function sanitize(string $html): string
    {
        $blank = \trim($html) === '';
        if ($blank) return '';

        $doc = new \DOMDocument();
        \libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        \libxml_clear_errors();

        $root = $doc->getElementById('root');
        if ($root === null) return \htmlspecialchars(\strip_tags($html));

        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child)
        {
            $out .= $doc->saveHTML($child);
        }

        return \trim($out);
    }

    private static function clean(\DOMNode $parent): void
    {
        foreach (\iterator_to_array($parent->childNodes) as $node)
        {
            $isElement = $node instanceof \DOMElement;
            if (!$isElement) continue;

            $tag = \strtolower($node->tagName);
            if (\in_array($tag, self::DROPPED_WITH_CONTENT, true))
            {
                $parent->removeChild($node);
                continue;
            }

            self::clean($node);

            if ($tag === 'p' || $tag === 'br')
            {
                self::stripAttributes($node, []);
                continue;
            }

            if ($tag === 'a')
            {
                $href = \trim($node->getAttribute('href'));
                $safe = \preg_match('#^(https?://|/(?!/))#i', $href) === 1;
                self::stripAttributes($node, []);
                if ($safe)
                {
                    $node->setAttribute('href', $href);
                    $node->setAttribute('rel', 'noopener');
                    continue;
                }
            }

            while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
            $parent->removeChild($node);
        }
    }

    private static function stripAttributes(\DOMElement $element, array $keep): void
    {
        foreach (\iterator_to_array($element->attributes) as $attribute)
        {
            if (!\in_array($attribute->name, $keep, true)) $element->removeAttribute($attribute->name);
        }
    }
}
