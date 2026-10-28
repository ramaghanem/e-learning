<?php

namespace App\Models;

use App\Models\Lesson;
use App\Models\QuizQuestionOption;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lesson_id', 'type', 'question', 'points', 'order'])]
class QuizQuestion extends Model
{
    public const TYPE_MCQ = 'mcq';

    public const TYPES = [
        self::TYPE_MCQ => 'Multiple Choice (MCQ)',
    ];

    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 6;

    /**
     * Tags the question editor may produce, with the attributes each one keeps.
     */
    private const ALLOWED_TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'code' => [], 'ol' => [], 'ul' => [], 'li' => [],
        'a' => ['href'],
    ];

    /**
     * Tags removed together with everything inside them.
     * Any other unknown tag is unwrapped: its text stays, the tag goes.
     */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'img', 'picture',
        'svg', 'math', 'video', 'audio', 'canvas', 'template', 'form', 'input',
        'button', 'textarea', 'select', 'head', 'title', 'meta', 'link', 'base',
    ];

    /**
     * Keeps only the simple formatting of the rich text editor
     * (bold, italic, lists, links, ...). Scripts, images and attributes are removed.
     */
    public static function cleanHtml(?string $html): string
    {
        $html = str_replace('&nbsp;', ' ', trim((string) $html));

        if ($html === '') {
            return '';
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return '';
        }

        static::cleanChildren($root);

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim(str_replace("\u{00A0}", ' ', $clean));
    }

    /**
     * True when the HTML has no visible text, e.g. an empty editor ("<p><br></p>").
     */
    public static function isBlankHtml(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }

    private static function cleanChildren(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText && ! $child instanceof \DOMCdataSection) {
                continue;
            }

            if (! $child instanceof \DOMElement) {
                // Comments, CDATA, processing instructions, ...
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            static::cleanChildren($child);

            if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (! in_array(strtolower($attribute->name), self::ALLOWED_TAGS[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a') {
                // Only web and email links, opened in a new tab.
                if (! preg_match('~^(https?://|mailto:)~i', trim($child->getAttribute('href')))) {
                    $child->removeAttribute('href');
                } else {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    }

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'order' => 'integer',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class)->orderBy('order');
    }
}
