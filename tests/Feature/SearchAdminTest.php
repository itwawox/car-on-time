<?php

namespace Tests\Feature;

use App\Filament\Resources\SearchQueries\SearchQueryResource;
use App\Models\Brand;
use App\Models\Car;
use App\Models\SearchQuery;
use App\Models\SearchSynonym;
use App\Models\Setting;
use App\Models\User;
use App\Support\Search\SearchLogger;
use App\Support\Search\SearchSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchAdminTest extends TestCase
{
    use RefreshDatabase;

    private Car $largus;

    protected function setUp(): void
    {
        parent::setUp();

        $lada = Brand::query()->create(['name' => 'Lada', 'slug' => 'lada']);
        $kia = Brand::query()->create(['name' => 'Kia', 'slug' => 'kia']);
        $this->largus = Car::query()->create(['brand_id' => $lada->id, 'name' => 'Lada Largus', 'slug' => 'largus', 'gearbox' => 'mt', 'seats' => 7, 'status' => 'published']);
        Car::query()->create(['brand_id' => $kia->id, 'name' => 'Kia Rio', 'slug' => 'rio', 'gearbox' => 'at', 'seats' => 5, 'status' => 'published']);
    }

    private function names(string $query): array
    {
        return Car::query()->whereIn('id', array_column(Car::search($query)->raw()['results'], 'id'))->pluck('name')->all();
    }

    public function test_dictionaries_are_seeded_into_database(): void
    {
        $this->assertNotEmpty(Setting::get('search_intent')['gearbox_at']);
        $this->assertSame(SearchSettings::DEFAULTS['search_ui']['placeholder'], Setting::get('search_ui')['placeholder']);
        $this->assertTrue(SearchSynonym::query()->where('term', 'largus')->exists());
    }

    public function test_synonym_added_in_admin_works_immediately(): void
    {
        $this->assertSame([], $this->names('ласточка'));

        SearchSynonym::query()->where('term', 'largus')->first()->update(['synonyms' => ['ларгус', 'ласточка']]);

        $this->assertSame(['Lada Largus'], $this->names('ласточка'));
    }

    public function test_brand_alias_from_admin_is_used(): void
    {
        $this->assertSame([], $this->names('копейка'));

        Brand::query()->where('slug', 'lada')->update(['search_aliases' => 'копейка']);
        Brand::query()->where('slug', 'lada')->first()->touch();

        $this->assertSame(['Lada Largus'], $this->names('копейка'));
    }

    public function test_intent_words_and_texts_are_editable(): void
    {
        $intent = SearchSettings::intent();
        $intent['gearbox_at'][] = 'автоматик';
        Setting::put('search_intent', $intent, SearchSettings::GROUP);

        $ui = SearchSettings::ui();
        $ui['chip_at'] = 'АКПП';
        Setting::put('search_ui', $ui, SearchSettings::GROUP);

        $this->getJson('/poisk/podskazki?q='.urlencode('автоматик'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('chips.0.label', 'АКПП');
    }

    public function test_search_texts_come_from_settings(): void
    {
        $ui = SearchSettings::ui();
        $ui['page_not_found'] = 'Увы, пусто';
        Setting::put('search_ui', $ui, SearchSettings::GROUP);

        $this->get('/poisk?q=qwzx')->assertOk()->assertSee('Увы, пусто');
    }

    public function test_queries_are_logged_and_short_ones_skipped(): void
    {
        $this->getJson('/poisk/podskazki?q=qwzx&final=1')->assertOk();
        $this->getJson('/poisk/podskazki?q=qwz')->assertOk(); // без final — посетитель ещё печатает
        $this->get('/poisk?q=Kia')->assertOk();
        $this->getJson('/poisk/podskazki?q=к&final=1')->assertOk();

        $this->assertSame(0, SearchQuery::query()->where('query', 'qwzx')->value('last_results'));
        $this->assertSame(1, SearchQuery::query()->where('query', 'kia')->value('last_results'));
        $this->assertSame(2, SearchQuery::query()->count());
    }

    public function test_same_visitor_is_counted_once_different_visitors_add_up(): void
    {
        SearchLogger::record('Qwzx ', 0, 'visitor-a');
        SearchLogger::record('qwzx', 0, 'visitor-a');
        SearchLogger::record('qwzx', 0, 'visitor-b');

        $row = SearchQuery::query()->where('query', 'qwzx')->first();
        $this->assertSame(2, $row->hits);
        $this->assertSame(2, $row->zero_results_count);
    }

    public function test_zero_result_query_can_become_a_synonym(): void
    {
        SearchQueryResource::attachSynonym('car:'.$this->largus->id, 'семейник');

        $this->assertSame(['Lada Largus'], $this->names('семейник'));
    }

    public function test_admin_pages_render(): void
    {
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        SearchQuery::query()->create(['query' => 'qwzx', 'hits' => 3, 'last_results' => 0, 'last_searched_at' => now()]);

        $this->get('/admin/search/settings')->assertOk()->assertSee('Слова-фильтры');
        $this->get('/admin/search/preview?q=kia')->assertOk()->assertSee('Kia Rio');
        $this->get('/admin/search/synonyms')->assertOk()->assertSee('largus');
        $this->get('/admin/search/queries')->assertOk()->assertSee('qwzx')->assertSee('Что ищут на сайте')->assertDontSee('Что Ищут На Сайте');
        $this->get('/admin/car-classes/create')->assertOk()->assertSee('Синонимы для поиска');
    }
}
