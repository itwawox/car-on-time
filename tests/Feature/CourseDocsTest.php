<?php

use App\Filament\Pages\CourseDocs;
use App\Models\User;
use Illuminate\Support\Facades\File;

// Курс в админке: уроки читает только владелец, справочник тестов не отстаёт от папки tests

it('lets the owner read every lesson', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/course')->assertOk()->assertSee('Как пользоваться курсом');

    foreach (CourseDocs::sections() as $slug => $lesson) {
        expect($lesson['title'])->not->toBe('');
        $this->get('/admin/course?section='.$slug)->assertOk()->assertSee('<h1>'.e($lesson['title']).'</h1>', false);
    }
});

it('hides the course from managers', function () {
    $this->actingAs(User::factory()->role('manager')->create());

    $this->get('/admin/course')->assertForbidden();
});

it('describes every test file in the lesson about our tests', function () {
    $lesson = File::get(resource_path('docs/course/06-vse-nashi-testy.md'));

    $testFiles = collect(File::allFiles(base_path('tests')))
        ->map(fn ($file) => $file->getFilename())
        ->filter(fn (string $name) => str_ends_with($name, 'Test.php') && $name !== 'TestCase.php');

    expect($testFiles)->not->toBeEmpty();

    foreach ($testFiles as $name) {
        expect($lesson)->toContain('`'.$name.'`');
    }
});
