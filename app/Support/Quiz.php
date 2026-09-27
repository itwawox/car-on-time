<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Вопросы подбора. Тексты вопросов и вариантов переопределяются в «Настройки сайта → Подбор: квиз»
 * (ключи quiz_q_{вопрос}, quiz_o_{вопрос}_{вариант}), логика — в QuizMatcher.
 */
class Quiz
{
    /** @return list<array{name: string, title: string, hint: string, multiple: bool, options: list<array{value: string, label: string, hint: string, icon: string}>}> */
    public static function steps(): array
    {
        $budgets = self::budgets();

        $steps = [
            ['who', 'Кто едет?', 'Подберём по числу мест и багажнику', false, [
                ['solo', 'Один или вдвоём', 'Любая машина — выбираем по вкусу', 'user'],
                ['family', 'Семья с детьми', 'Просторный салон, кресла бесплатно', 'users'],
                ['group', 'Компания 6–8 человек', 'Минивэны и 7-местные кроссоверы', 'seats'],
                ['business', 'Деловая поездка', 'Бизнес-класс, тихий салон', 'briefcase'],
            ]],
            ['trip', 'Куда поедете?', 'Можно выбрать несколько', true, [
                ['city', 'Город и пляжи', 'Короткие поездки, парковки', 'city'],
                ['coast', 'Южный берег и серпантины', 'Подъёмы, повороты, пробки', 'route'],
                ['mountains', 'Горы и грунтовые подъезды', 'Клиренс и полный привод', 'mountain'],
                ['long', 'Много езды по Крыму', 'Экономичный расход, комфорт в пути', 'gauge'],
            ]],
            ['priority', 'Что важнее всего?', 'Главный критерий выбора', false, [
                ['save', 'Сэкономить', 'Дешевле аренда и бензин', 'wallet'],
                ['balance', 'Золотая середина', 'Разумная цена и удобство', 'check'],
                ['comfort', 'Комфорт и статус', 'Бизнес-класс и мощность', 'star'],
                ['fun', 'Эмоции от поездки', 'Кабриолеты, купе, мощные машины', 'bolt'],
            ]],
            ['gearbox', 'Какая коробка?', 'На серпантинах и в пробках удобнее автомат', false, [
                ['at', 'Только автомат', 'Проще в горах и в городе', 'gearbox'],
                ['any', 'Не важно', 'Покажем лучшие варианты', 'check'],
                ['mt', 'Лучше механика', 'Обычно дешевле', 'gearbox'],
            ]],
            ['luggage', 'Сколько багажа?', 'Подберём по объёму багажника', false, [
                ['light', 'Налегке', 'Рюкзаки и сумки', 'bag'],
                ['suitcases', '1–2 чемодана', 'Обычный отпуск', 'bag'],
                ['lots', 'Много вещей', 'Коляска, 3+ чемодана, снаряжение', 'bag'],
            ]],
            ['budget', 'Бюджет в сутки?', 'Цены машин из нашего каталога', false, [
                ['b1', 'до '.self::money($budgets[0]), 'Эконом-варианты', 'wallet'],
                ['b2', 'до '.self::money($budgets[1]), 'Большинство машин', 'wallet'],
                ['b3', 'до '.self::money($budgets[2]), 'Включая бизнес-класс', 'wallet'],
                ['any', 'Не важно', 'Подберём лучшее', 'star'],
            ]],
        ];

        return array_map(fn ($s) => [
            'name' => $s[0],
            'title' => (string) (Setting::get('quiz_q_'.$s[0]) ?: $s[1]),
            'hint' => $s[2],
            'multiple' => $s[3],
            'options' => array_map(fn ($o) => [
                'value' => $o[0],
                'label' => (string) (Setting::get('quiz_o_'.$s[0].'_'.$o[0]) ?: $o[1]),
                'hint' => $o[2],
                'icon' => $o[3],
            ], $s[4]),
        ], $steps);
    }

    /**
     * Пороги бюджета из реальных цен каталога (≈30%, 60% и 85% машин), округлённые вверх до 500 ₽.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function budgets(): array
    {
        $prices = collect(Fleet::all())->pluck('price')->filter()->sort()->values();
        if ($prices->isEmpty()) {
            return [2000, 3500, 6000];
        }
        $at = fn (float $q) => (int) (ceil($prices[min($prices->count() - 1, (int) floor($prices->count() * $q))] / 500) * 500);

        return [$at(0.3), $at(0.6), $at(0.85)];
    }

    public static function money(int $n): string
    {
        return number_format($n, 0, ',', ' ').' ₽';
    }

    /** Ответы из запроса с проверкой допустимых значений. @return array<string, string|list<string>> */
    public static function answers(array $input): array
    {
        $answers = [];
        foreach (self::steps() as $step) {
            $allowed = array_column($step['options'], 'value');
            $value = $input[$step['name']] ?? null;
            if ($step['multiple']) {
                $answers[$step['name']] = array_values(array_intersect((array) $value, $allowed));
            } elseif (is_string($value) && in_array($value, $allowed, true)) {
                $answers[$step['name']] = $value;
            }
        }

        return $answers;
    }

    /** «семья · горы, серпантины · комфорт · автомат» — для плашки «Продолжить подбор». */
    public static function summary(array $answers): string
    {
        $parts = [];
        foreach (self::steps() as $step) {
            $values = (array) ($answers[$step['name']] ?? []);
            $labels = array_column(array_filter($step['options'], fn ($o) => in_array($o['value'], $values, true) && $o['value'] !== 'any'), 'label');
            if ($labels) {
                $parts[] = mb_strtolower(implode(', ', $labels));
            }
        }

        return implode(' · ', $parts);
    }
}
