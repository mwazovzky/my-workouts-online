<?php

namespace App\Enums;

/**
 * A catalog validation rule, as `CatalogValidator` reports it. The values are
 * the Go validator's rule ids (`catalogsync.Rule`), and each one names the
 * fixture under catalog/testdata/invalid/ that breaks exactly that rule, so
 * the two validators are tested against the same files.
 */
enum CatalogRule: string
{
    case RecordNotMapping = 'record-not-mapping';
    case SlugRequired = 'slug-required';
    case SlugFormat = 'slug-format';
    case SlugDuplicate = 'slug-duplicate';
    case TranslationsRequired = 'translations-required';
    case LocaleUnsupported = 'locale-unsupported';
    case FieldNotTranslatable = 'field-not-translatable';
    case FieldRequired = 'field-required';
    case FieldTooLong = 'field-too-long';
    case PreviousNamesNotList = 'previous-names-not-list';
    case PreviousNamesBlank = 'previous-names-blank';
    case NameDuplicate = 'name-duplicate';
    case EquipmentUnitInvalid = 'equipment-unit-invalid';
    case ExerciseEquipmentUnknown = 'exercise-equipment-unknown';
    case ExerciseEffortTypeInvalid = 'exercise-effort-type-invalid';
    case ExerciseRestTimeRange = 'exercise-rest-time-range';
    case ExerciseRestTimeRequired = 'exercise-rest-time-required';
    case ExerciseCategoriesRequired = 'exercise-categories-required';
    case ExerciseCategoryUnknown = 'exercise-category-unknown';
    case ExerciseCategoryRepeated = 'exercise-category-repeated';
    case EquipmentUnused = 'equipment-unused';
    case CategoryUnused = 'category-unused';
    case TemplateActivitiesRequired = 'template-activities-required';
    case ActivityExerciseUnknown = 'activity-exercise-unknown';
    case ActivitySetsRequired = 'activity-sets-required';
    case SetEffortInvalid = 'set-effort-invalid';
    case SetDifficultyInvalid = 'set-difficulty-invalid';
    case SetDifficultyWithoutUnit = 'set-difficulty-without-unit';
    case SetDifficultyWeight = 'set-difficulty-weight';
    case SetHeartRateZoneRange = 'set-heart-rate-zone-range';
    case ProgramScheduleRequired = 'program-schedule-required';
    case ScheduleWeekdayInvalid = 'schedule-weekday-invalid';
    case ScheduleTemplateUnknown = 'schedule-template-unknown';
    case ScheduleDuplicate = 'schedule-duplicate';
    case RetiredTypeUnknown = 'retired-type-unknown';
    case RetiredRecordNotMapping = 'retired-record-not-mapping';
    case RetiredSlugActive = 'retired-slug-active';
    case RetiredNamesRequired = 'retired-names-required';
    case RetiredNamesBlank = 'retired-names-blank';
    case RetiredNameActive = 'retired-name-active';
}
