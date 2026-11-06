<?php

declare(strict_types=1);

namespace Larasan\Mcp\Support;

use App\Domain\Shared\Html\RichText;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Turns the Markdown a model writes into the Quill 2 HTML task descriptions are stored as.
 */
final readonly class Markdown
{
    public static function toTaskHtml(?string $markdown): ?string
    {
        if ($markdown === null || trim($markdown) === '') {
            return null;
        }

        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        // CommonMark breaks lines between blocks; the editor would read each break as text. Code keeps its own.
        $html = preg_replace('#(</?(?:p|ol|ul|li|h[1-6]|blockquote)>|</pre>)\n#', '$1', self::asQuillLists($html)) ?? $html;

        return RichText::sanitize(trim($html));
    }

    /**
     * Quill 2 keeps every list as `<ol>` and reads the list type from `data-list`.
     */
    private static function asQuillLists(string $html): string
    {
        if (! str_contains($html, '<ul>') && ! str_contains($html, '<ol>')) {
            return $html;
        }

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);

        // Ordered lists first: the bullet lists below become <ol> too, and must keep their own type.
        foreach (['ol' => 'ordered', 'ul' => 'bullet'] as $tag => $type) {
            foreach (iterator_to_array($document->getElementsByTagName($tag)) as $list) {
                foreach ($list->childNodes as $item) {
                    if ($item instanceof DOMElement && $item->tagName === 'li') {
                        $item->setAttribute('data-list', $type);
                    }
                }

                if ($tag === 'ul') {
                    $ordered = $document->createElement('ol');

                    while ($list->firstChild !== null) {
                        $ordered->appendChild($list->firstChild);
                    }

                    $list->parentNode?->replaceChild($ordered, $list);
                }
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return $html;
        }

        $result = '';

        foreach ($body->childNodes as $node) {
            $result .= $document->saveHTML($node);
        }

        return $result;
    }
}
