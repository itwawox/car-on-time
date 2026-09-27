<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Support\DocsSearch;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

/**
 * Страница админки, которая показывает папку Markdown-файлов как разделы с меню слева.
 * Номер в имени файла задаёт порядок, первая строка «# …» — заголовок раздела. Только для владельца.
 */
abstract class MarkdownDocsPage extends Page
{
    use RestrictedToArea;

    protected static string|\UnitEnum|null $navigationGroup = 'Документация';

    protected string $view = 'filament.pages.developer-docs';

    #[Url]
    public string $section = '';

    /** Поиск сразу по курсу и документации для разработчиков. */
    #[Url]
    public string $q = '';

    /** Папка с разделами относительно resources/, например «docs/developers». */
    abstract protected static function folder(): string;

    /**
     * Разделы по порядку номеров в именах файлов.
     *
     * @return Collection<string, array{slug: string, title: string, path: string}>
     */
    public static function sections(): Collection
    {
        return collect(File::files(resource_path(static::folder())))
            ->filter(fn ($file) => $file->getExtension() === 'md')
            ->sortBy(fn ($file) => $file->getFilename())
            ->mapWithKeys(function ($file) {
                $slug = Str::of($file->getFilenameWithoutExtension())->replaceMatches('/^\d+-/', '')->toString();
                $firstLine = strtok((string) File::get($file->getPathname()), "\n");

                return [$slug => [
                    'slug' => $slug,
                    'title' => trim(ltrim((string) $firstLine, '# ')),
                    'path' => $file->getPathname(),
                ]];
            });
    }

    /** @return array{slug: string, title: string, path: string} */
    public function current(): array
    {
        $sections = static::sections();

        return $sections[$this->section] ?? $sections->first();
    }

    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown(File::get($this->current()['path']), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /** @return array{prev: ?array, next: ?array} */
    public function neighbours(): array
    {
        $slugs = static::sections()->keys()->values();
        $index = $slugs->search($this->current()['slug']);
        $sections = static::sections();

        return [
            'prev' => $index > 0 ? $sections[$slugs[$index - 1]] : null,
            'next' => $index < $slugs->count() - 1 ? $sections[$slugs[$index + 1]] : null,
        ];
    }

    /** @return list<array{book: string, title: string, heading: ?string, url: string, snippet: HtmlString}> */
    public function searchResults(): array
    {
        return DocsSearch::search($this->q, [CourseDocs::class, DeveloperDocs::class]);
    }

    protected static function accessArea(): string
    {
        return 'admin';
    }
}
