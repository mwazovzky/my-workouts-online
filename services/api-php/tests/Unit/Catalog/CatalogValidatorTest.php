<?php

namespace Tests\Unit\Catalog;

use App\Enums\CatalogRule;
use App\Enums\DifficultyUnit;
use App\Services\Catalog\CatalogLoader;
use App\Services\Catalog\CatalogValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\UsesSharedCatalog;
use Tests\TestCase;

class CatalogValidatorTest extends TestCase
{
    use UsesSharedCatalog;

    /**
     * Rules a shared fixture also breaks because its rule leaves a reference
     * dangling: equipment with an invalid unit has no unit, so its exercise's
     * equipment counts as unknown. The Go test lists the same.
     */
    private const CONSEQUENCES = [
        'equipment-unit-invalid' => [CatalogRule::ExerciseEquipmentUnknown],
    ];

    /**
     * Indexes into the fixture catalog, in load order.
     */
    private const EASY_RIDE = 0;

    private const BENCH_PRESS = 1;

    private const EASY_SPIN = 0;

    private const FULL_BODY = 1;

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        return (new CatalogLoader($this->validCatalogPath()))->load();
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<int, string>
     */
    private function validate(array $catalog): array
    {
        return (new CatalogValidator(['en', 'ru']))->validate($catalog);
    }

    /**
     * @param  array<string, mixed>  $catalog
     */
    private function assertViolation(string $expected, array $catalog): void
    {
        $violations = $this->validate($catalog);

        $matches = array_filter($violations, fn (string $violation) => str_contains($violation, $expected));

        $this->assertNotEmpty(
            $matches,
            "Expected a violation containing \"{$expected}\", got:\n".implode("\n", $violations),
        );
    }

    #[Test]
    public function a_valid_catalog_has_no_violations(): void
    {
        $this->assertSame([], $this->validate($this->catalog()));
    }

    #[Test]
    public function a_slug_is_required(): void
    {
        $catalog = $this->catalog();
        unset($catalog['equipment'][0]['slug']);

        $this->assertViolation('equipment #0: slug is required', $catalog);
    }

    #[Test]
    public function a_slug_must_be_lowercase_words_separated_by_hyphens(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][0]['slug'] = 'Chest_Muscles';

        $this->assertViolation('categories [Chest_Muscles]: slug must be lowercase', $catalog);
    }

    #[Test]
    public function a_slug_must_be_unique_within_its_type(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][1]['slug'] = 'chest';

        $this->assertViolation('categories [chest]: slug is used more than once', $catalog);
    }

    #[Test]
    public function the_same_slug_may_be_used_by_different_types(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['slug'] = 'full-body';

        $this->assertSame([], $this->validate($catalog));
    }

    #[Test]
    public function every_locale_needs_a_name(): void
    {
        $catalog = $this->catalog();
        unset($catalog['equipment'][0]['translations']['ru']);

        $this->assertViolation('equipment [barbell]: name (ru) is required', $catalog);
    }

    #[Test]
    public function described_types_need_a_description_in_every_locale(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['translations']['ru']['description'] = ' ';

        $this->assertViolation('exercises [bench-press]: description (ru) is required', $catalog);
    }

    #[Test]
    public function an_unsupported_locale_is_rejected(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][0]['translations']['de'] = ['name' => 'Brust'];

        $this->assertViolation('categories [chest]: locale "de" is not supported', $catalog);
    }

    #[Test]
    public function a_field_that_is_not_translatable_is_rejected(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][0]['translations']['en']['description'] = 'Chest muscles.';

        $this->assertViolation('categories [chest]: field "description" (en) is not translatable', $catalog);
    }

    #[Test]
    public function a_name_longer_than_the_limit_is_rejected(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][0]['translations']['en']['name'] = str_repeat('a', 256);

        $this->assertViolation('categories [chest]: name (en) is longer than 255 characters', $catalog);
    }

    #[Test]
    public function names_must_be_unique_within_a_type(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][1]['translations']['en']['name'] = ' chest ';

        $this->assertViolation('categories [legs]: name " chest " (en) is already used by categories [chest]', $catalog);
    }

    #[Test]
    public function a_previous_name_must_not_belong_to_another_record(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::EASY_RIDE]['previous_names'] = ['Bench Press'];

        $this->assertViolation('name "Bench Press" (en) is already used by exercises [easy-ride]', $catalog);
    }

    #[Test]
    public function the_difficulty_unit_must_be_an_enum_case(): void
    {
        $catalog = $this->catalog();
        $catalog['equipment'][0]['difficulty_unit'] = 'kilograms';

        $this->assertViolation('equipment [barbell]: difficulty_unit must be one of kilograms, pounds, plates, heart_rate_zone, none.', $catalog);
    }

    #[Test]
    public function the_effort_type_must_be_an_enum_case(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['effort_type'] = 'repetitions';

        $this->assertViolation('exercises [bench-press]: effort_type must be one of repetitions, duration.', $catalog);
    }

    #[Test]
    public function an_exercise_must_reference_existing_equipment(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['equipment'] = 'ez-bar';

        $this->assertViolation('exercises [bench-press]: equipment "ez-bar" does not exist', $catalog);
    }

    #[Test]
    public function an_exercise_needs_at_least_one_category(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['categories'] = [];

        $this->assertViolation('exercises [bench-press]: at least one category is required', $catalog);
    }

    #[Test]
    public function an_exercise_must_reference_existing_categories(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['categories'] = ['chest', 'triceps'];

        $this->assertViolation('exercises [bench-press]: category "triceps" does not exist', $catalog);
    }

    #[Test]
    public function an_exercise_must_not_repeat_a_category(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['categories'] = ['chest', 'chest'];

        $this->assertViolation('exercises [bench-press]: categories must not repeat', $catalog);
    }

    #[Test]
    public function the_rest_time_must_be_within_range(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['rest_time_seconds'] = 3601;

        $this->assertViolation('exercises [bench-press]: rest_time_seconds must be null or an integer from 0 to 3600', $catalog);
    }

    #[Test]
    public function a_repetition_based_exercise_needs_a_rest_time(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['rest_time_seconds'] = null;

        $this->assertViolation('exercises [bench-press]: rest_time_seconds is required for a repetition-based exercise', $catalog);
    }

    #[Test]
    public function a_duration_based_exercise_may_omit_the_rest_time(): void
    {
        $catalog = $this->catalog();

        $this->assertNull($catalog['exercises'][self::EASY_RIDE]['rest_time_seconds']);
        $this->assertSame([], $this->validate($catalog));
    }

    #[Test]
    public function every_equipment_item_must_be_used_by_an_exercise(): void
    {
        $catalog = $this->catalog();
        $catalog['equipment'][] = [
            'slug' => 'treadmill',
            'difficulty_unit' => DifficultyUnit::None,
            'translations' => ['en' => ['name' => 'Treadmill'], 'ru' => ['name' => 'Беговая дорожка']],
            'previous_names' => [],
        ];

        $this->assertSame(['equipment [treadmill]: no exercise uses it.'], $this->validate($catalog));
    }

    #[Test]
    public function every_category_must_be_used_by_an_exercise(): void
    {
        $catalog = $this->catalog();
        $catalog['categories'][] = [
            'slug' => 'forearms',
            'translations' => ['en' => ['name' => 'Forearms'], 'ru' => ['name' => 'Предплечья']],
            'previous_names' => [],
        ];

        $this->assertSame(['categories [forearms]: no exercise belongs to it.'], $this->validate($catalog));
    }

    #[Test]
    public function a_template_needs_at_least_one_activity(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'] = [];

        $this->assertViolation('workout_templates [full-body]: at least one activity is required', $catalog);
    }

    #[Test]
    public function a_template_must_reference_existing_exercises(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][0]['exercise'] = 'deadlift';

        $this->assertViolation('workout_templates [full-body] activity 1: exercise "deadlift" does not exist', $catalog);
    }

    #[Test]
    public function an_activity_needs_at_least_one_set(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][0]['sets'] = [];

        $this->assertViolation('workout_templates [full-body] activity 1: at least one set is required', $catalog);
    }

    #[Test]
    public function a_set_needs_a_positive_effort(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][0]['sets'][1]['effort'] = 0;

        $this->assertViolation('workout_templates [full-body] activity 1 set 2: effort must be a positive integer', $catalog);
    }

    #[Test]
    public function a_set_difficulty_must_not_be_negative(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][0]['sets'][0]['difficulty'] = -5;

        $this->assertViolation('activity 1 set 1: difficulty must be null or a non-negative number', $catalog);
    }

    #[Test]
    public function equipment_without_a_unit_takes_no_difficulty(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][2]['sets'][0]['difficulty'] = 10;

        $this->assertViolation('activity 3 set 1: difficulty must be null because the equipment has no difficulty unit', $catalog);
    }

    #[Test]
    public function a_template_must_not_prescribe_a_weight(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::FULL_BODY]['activities'][0]['sets'][0]['difficulty'] = 40;

        $this->assertViolation('activity 1 set 1: difficulty must be null because templates do not prescribe a weight', $catalog);
    }

    #[Test]
    public function a_heart_rate_zone_must_be_between_one_and_five(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::EASY_SPIN]['activities'][0]['sets'][0]['difficulty'] = 6;

        $this->assertViolation('workout_templates [easy-spin] activity 1 set 1: heart-rate zone must be an integer from 1 to 5', $catalog);
    }

    #[Test]
    public function a_heart_rate_zone_may_be_omitted(): void
    {
        $catalog = $this->catalog();
        $catalog['workout_templates'][self::EASY_SPIN]['activities'][0]['sets'][0]['difficulty'] = null;

        $this->assertSame([], $this->validate($catalog));
    }

    #[Test]
    public function a_program_needs_at_least_one_scheduled_workout(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['schedule'] = [];

        $this->assertViolation('programs [starter]: at least one scheduled workout is required', $catalog);
    }

    #[Test]
    public function a_program_weekday_must_be_an_enum_case(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['schedule'][0]['weekday'] = 'Monday';

        $this->assertViolation('programs [starter] schedule entry 1: weekday must be one of monday, tuesday, wednesday, thursday, friday, saturday, sunday.', $catalog);
    }

    #[Test]
    public function a_program_must_reference_existing_templates(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['schedule'][0]['template'] = 'upper-body';

        $this->assertViolation('programs [starter] schedule entry 1: workout template "upper-body" does not exist', $catalog);
    }

    #[Test]
    public function a_program_must_not_schedule_a_template_twice_on_one_weekday(): void
    {
        $catalog = $this->catalog();
        $catalog['programs'][0]['schedule'][1]['weekday'] = $catalog['programs'][0]['schedule'][0]['weekday'];

        $this->assertViolation('schedule entry 2: workout template "full-body" is already scheduled on monday', $catalog);
    }

    #[Test]
    public function a_retired_slug_must_not_belong_to_an_active_record(): void
    {
        $catalog = $this->catalog();
        $catalog['retired']['equipment'][] = ['slug' => 'barbell', 'names' => ['Rusty Barbell']];

        $this->assertViolation('retired equipment [barbell]: slug belongs to an active record', $catalog);
    }

    #[Test]
    public function a_retired_name_must_not_belong_to_an_active_record(): void
    {
        $catalog = $this->catalog();
        $catalog['retired']['exercises'][] = ['slug' => 'old-press', 'names' => ['Old Bench Press']];

        $this->assertViolation('retired exercises [old-press]: name "Old Bench Press" belongs to an active record', $catalog);
    }

    #[Test]
    public function a_retired_record_needs_names(): void
    {
        $catalog = $this->catalog();
        $catalog['retired']['exercises'][] = ['slug' => 'old-press', 'names' => []];

        $this->assertViolation('retired exercises [old-press]: names must list at least one English name', $catalog);
    }

    #[Test]
    public function a_retired_list_must_belong_to_a_catalog_type(): void
    {
        $catalog = $this->catalog();
        $catalog['retired']['workouts'] = [];

        $this->assertViolation('retired: "workouts" is not a catalog type', $catalog);
    }

    #[Test]
    public function it_reports_every_violation_at_once(): void
    {
        $catalog = $this->catalog();
        $catalog['exercises'][self::BENCH_PRESS]['equipment'] = 'ez-bar';
        $catalog['programs'][0]['schedule'][0]['template'] = 'upper-body';

        $this->assertCount(2, $this->validate($catalog));
    }

    #[Test]
    public function every_rule_has_a_shared_fixture_and_every_fixture_is_a_rule(): void
    {
        $fixtures = array_map('basename', glob($this->invalidCatalogsPath().'/*', GLOB_ONLYDIR) ?: []);
        $rules = array_column(CatalogRule::cases(), 'value');
        sort($fixtures);
        sort($rules);

        $this->assertSame($rules, $fixtures);
    }

    /**
     * @return array<string, array{CatalogRule}>
     */
    public static function rules(): array
    {
        return array_combine(
            array_column(CatalogRule::cases(), 'value'),
            array_map(fn (CatalogRule $rule) => [$rule], CatalogRule::cases()),
        );
    }

    /**
     * The fixture is the valid catalog with its files replaced by the ones
     * in catalog/testdata/invalid/<rule>/; it breaks that rule and no other.
     */
    #[Test]
    #[DataProvider('rules')]
    public function each_shared_fixture_breaks_its_rule(CatalogRule $rule): void
    {
        $path = $this->catalogCopy(overlay: $this->invalidCatalogsPath().'/'.$rule->value);
        $violations = (new CatalogValidator(['en', 'ru']))->check((new CatalogLoader($path))->load());

        $broken = array_values(array_unique(array_map(fn (array $violation) => $violation['rule']->value, $violations)));
        $expected = array_column([$rule, ...(self::CONSEQUENCES[$rule->value] ?? [])], 'value');
        sort($broken);
        sort($expected);

        $this->assertSame($expected, $broken, implode("\n", array_column($violations, 'message')));
    }
}
