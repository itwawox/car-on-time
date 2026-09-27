<?php

namespace App\Support;

use App\Filament\Pages\MarkdownDocsPage;
use App\Support\Search\TextNormalizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;

/**
 * Поиск по курсу и документации для разработчиков.
 *
 * Прощает окончания («тесты» найдёт «тестов»), опечатки, транслит («ларастан» → Larastan)
 * и не ту раскладку («дфкфыефт» → larastan). Разделы режутся по подзаголовкам «##»,
 * чтобы в выдаче было видно, где именно нашлось.
 */
final class DocsSearch
{
    private const TITLE_WEIGHT = 5;

    private const HEADING_WEIGHT = 3;

    /** @var array<string, string> */
    private static array $keys = [];

    /**
     * @param  list<class-string<MarkdownDocsPage>>  $pages
     * @return list<array{book: string, title: string, heading: ?string, url: string, snippet: HtmlString}>
     */
    public static function search(string $query, array $pages, int $limit = 15): array
    {
        $stems = self::stems($query);
        if ($stems === []) {
            return [];
        }

        $chunks = self::chunks($pages);
        $results = self::rank($chunks, $stems);

        if ($results === []) {
            $swapped = self::stems(TextNormalizer::swapLayout($query));
            if ($swapped !== $stems) {
                $results = self::rank($chunks, $swapped);
            }
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * Основы слов запроса в виде фонетических ключей: хвост длинного слова отрезаем, чтобы найти другие окончания.
     *
     * @return list<string>
     */
    private static function stems(string $query): array
    {
        $stems = [];
        foreach (self::words($query) as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            $key = self::key($token);
            $length = strlen($key);
            $stems[] = substr($key, 0, match (true) {
                $length <= 4 => $length,
                $length <= 6 => $length - 1,
                default => $length - 2,
            });
        }

        return array_values(array_unique($stems));
    }

    /**
     * Куски разделов: вступление и каждый подзаголовок «##»/«###» отдельно.
     *
     * @param  list<class-string<MarkdownDocsPage>>  $pages
     * @return list<array{book: string, title: string, heading: ?string, url: string, text: string, titleWords: list<string>, headingWords: list<string>, bodyWords: list<string>}>
     */
    private static function chunks(array $pages): array
    {
        $chunks = [];
        foreach ($pages as $page) {
            foreach ($page::sections() as $slug => $section) {
                $lines = preg_split('/\R/u', File::get($section['path'])) ?: [];
                array_shift($lines);
                $parts = [[null, []]];
                foreach ($lines as $line) {
                    if (preg_match('/^#{2,3}\s+(.+)$/u', $line, $m)) {
                        $parts[] = [trim($m[1]), []];

                        continue;
                    }
                    $parts[array_key_last($parts)][1][] = $line;
                }

                foreach ($parts as [$heading, $body]) {
                    $text = implode("\n", $body);
                    if (trim($text) === '' && $heading === null) {
                        continue;
                    }
                    $chunks[] = [
                        'book' => (string) $page::getNavigationLabel(),
                        'title' => $section['title'],
                        'heading' => $heading,
                        'url' => $page::getUrl(['section' => $slug]),
                        'text' => $text,
                        'titleWords' => self::words($section['title']),
                        'headingWords' => self::words($heading),
                        'bodyWords' => self::words($text),
                    ];
                }
            }
        }

        return $chunks;
    }

    /**
     * Кусок попадает в выдачу, только если в нём нашлись все слова запроса. Совпадение в заголовке весит больше.
     *
     * @param  list<array{book: string, title: string, heading: ?string, url: string, text: string, titleWords: list<string>, headingWords: list<string>, bodyWords: list<string>}>  $chunks
     * @param  list<string>  $stems
     * @return list<array{book: string, title: string, heading: ?string, url: string, snippet: HtmlString}>
     */
    private static function rank(array $chunks, array $stems): array
    {
        $found = [];
        foreach ($chunks as $chunk) {
            $score = 0;
            $matched = [];
            foreach ($stems as $stem) {
                $inTitle = self::matching($stem, $chunk['titleWords']);
                $inHeading = self::matching($stem, $chunk['headingWords']);
                $inBody = self::matching($stem, $chunk['bodyWords']);
                if ($inTitle === [] && $inHeading === [] && $inBody === []) {
                    continue 2;
                }
                $score += count($inTitle) * self::TITLE_WEIGHT + count($inHeading) * self::HEADING_WEIGHT + min(count($inBody), 10);
                $matched = [...$matched, ...$inTitle, ...$inHeading, ...$inBody];
            }

            $found[] = [
                'score' => $score,
                'book' => $chunk['book'],
                'title' => $chunk['title'],
                'heading' => $chunk['heading'],
                'url' => $chunk['url'],
                'snippet' => self::snippet($chunk['text'], array_values(array_unique($matched))),
            ];
        }

        usort($found, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $item): array => array_diff_key($item, ['score' => true]), $found);
    }

    /**
     * Слова текста, подходящие под основу: совпадает начало или отличается одной буквой.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private static function matching(string $stem, array $words): array
    {
        $length = strlen($stem);
        $hits = [];
        foreach ($words as $word) {
            $key = self::key($word);
            if (str_starts_with($key, $stem) || ($length >= 5 && TextNormalizer::distance($stem, substr($key, 0, $length), 1) <= 1)) {
                $hits[] = $word;
            }
        }

        return $hits;
    }

    /**
     * Слова текста. «phpstan-baseline.neon» и «migrate:fresh» делим на части, чтобы находилась каждая.
     *
     * @return list<string>
     */
    private static function words(?string $text): array
    {
        return TextNormalizer::tokens(preg_replace('/[-_.:\/]+/', ' ', (string) $text));
    }

    private static function key(string $word): string
    {
        return self::$keys[$word] ??= TextNormalizer::key($word);
    }

    /**
     * Строчка вокруг первого найденного слова, найденные слова подсвечены.
     *
     * @param  list<string>  $words
     */
    private static function snippet(string $markdown, array $words): HtmlString
    {
        $plain = preg_replace(['/```[a-z]*/', '/\[([^\]]*)\]\([^)]*\)/', '/[`*_>|#]+/', '/\s+/u'], ['', '$1', '', ' '], $markdown);
        $plain = trim((string) $plain);
        if ($plain === '') {
            return new HtmlString('');
        }

        $normalized = str_replace('ё', 'е', mb_strtolower($plain));
        $position = collect($words)
            ->map(fn (string $word) => mb_strpos($normalized, $word))
            ->filter(fn ($at) => $at !== false)
            ->min() ?? 0;

        $start = max(0, $position - 60);
        $piece = mb_substr($plain, $start, 200);
        $piece = ($start > 0 ? '…' : '').$piece.($start + 200 < mb_strlen($plain) ? '…' : '');

        $html = e($piece);
        if ($words !== []) {
            $pattern = collect($words)
                ->sortByDesc(fn (string $word) => mb_strlen($word))
                ->map(fn (string $word) => str_replace('е', '[её]', preg_quote($word, '/')))
                ->implode('|');
            $html = (string) preg_replace('/(?<![\p{L}\p{N}])('.$pattern.')/iu', '<mark>$1</mark>', $html);
        }

        return new HtmlString($html);
    }
}
