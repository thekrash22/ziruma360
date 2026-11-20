<?php

namespace Tests\Feature;

use App\Enums\ComplexModule;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexModule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulesTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complex = ResidentialComplex::factory()->create();
    }

    public function test_new_complex_gets_all_modules_seeded(): void
    {
        $this->assertCount(6, $this->complex->modules);
    }

    public function test_core_modules_are_enabled_by_default(): void
    {
        $this->complex->modules->each(function ($module) {
            if ($module->module_key->isCore()) {
                $this->assertTrue($module->enabled);
            }
        });
    }

    public function test_non_core_modules_are_disabled_by_default(): void
    {
        $this->complex->modules->each(function ($module) {
            if (! $module->module_key->isCore()) {
                $this->assertFalse($module->enabled);
            }
        });
    }

    public function test_core_modules_have_default_settings(): void
    {
        foreach ([ComplexModule::CORE_STRUCTURE, ComplexModule::CORE_PEOPLE] as $module) {
            $moduleRecord = $this->complex->getModule($module);
            $this->assertNotNull($moduleRecord->settings);
            $this->assertIsArray($moduleRecord->settings);
        }
    }

    public function test_has_module_returns_true_for_enabled_module(): void
    {
        $this->complex->modules()
            ->byModule(ComplexModule::CORE_STRUCTURE->value)
            ->update(['enabled' => true]);

        $this->assertTrue($this->complex->hasModule(ComplexModule::CORE_STRUCTURE));
    }

    public function test_has_module_returns_false_for_disabled_module(): void
    {
        $this->complex->modules()
            ->byModule(ComplexModule::SHORT_TERM_RENTAL->value)
            ->update(['enabled' => false]);

        $this->assertFalse($this->complex->hasModule(ComplexModule::SHORT_TERM_RENTAL));
    }

    public function test_enable_module_enables_and_sets_settings(): void
    {
        $settings = ['approval_required' => false, 'max_stay_days' => 14];
        $module = $this->complex->enableModule(ComplexModule::SHORT_TERM_RENTAL, $settings);

        $this->assertTrue($module->enabled);
        $this->assertFalse($module->settings['approval_required']);
        $this->assertEquals(14, $module->settings['max_stay_days']);
    }

    public function test_disable_module_disables_the_module(): void
    {
        $this->complex->enableModule(ComplexModule::SHORT_TERM_RENTAL);
        $this->complex->disableModule(ComplexModule::SHORT_TERM_RENTAL);

        $this->assertFalse($this->complex->hasModule(ComplexModule::SHORT_TERM_RENTAL));
    }

    public function test_get_module_returns_the_module_instance(): void
    {
        $module = $this->complex->enableModule(ComplexModule::SHORT_TERM_RENTAL);

        $this->assertEquals($module->id, $this->complex->getModule(ComplexModule::SHORT_TERM_RENTAL)->id);
    }

    public function test_get_setting_returns_default_when_not_set(): void
    {
        $module = $this->complex->modules()->first();

        $this->assertEquals('default', $module->getSetting('nonexistent', 'default'));
    }

    public function test_set_setting_updates_the_setting(): void
    {
        $module = $this->complex->modules()->first();
        $module->setSetting('custom_key', 'custom_value');

        $module->refresh();
        $this->assertEquals('custom_value', $module->getSetting('custom_key'));
    }

    public function test_enabled_scope_returns_only_enabled_modules(): void
    {
        $this->complex->enableModule(ComplexModule::SHORT_TERM_RENTAL);

        $enabledModules = $this->complex->modules()->enabled()->get();

        $this->assertGreaterThanOrEqual(3, $enabledModules->count());
        $enabledModules->each(fn ($m) => $this->assertTrue($m->enabled));
    }

    public function test_by_module_scope_filters_correctly(): void
    {
        $module = $this->complex->modules()->byModule(ComplexModule::CORE_STRUCTURE)->first();

        $this->assertEquals(ComplexModule::CORE_STRUCTURE, $module->module_key);
    }

    public function test_modules_belong_to_correct_complex(): void
    {
        $complex2 = ResidentialComplex::factory()->create();

        $module1 = $this->complex->enableModule(ComplexModule::SHORT_TERM_RENTAL);
        $module2 = $complex2->enableModule(ComplexModule::SHORT_TERM_RENTAL);

        $this->assertEquals(
            $module1->id,
            $this->complex->modules()->byModule(ComplexModule::SHORT_TERM_RENTAL)->first()->id
        );
    }

    public function test_cannot_have_duplicate_module_keys_per_complex(): void
    {
        $this->expectException(QueryException::class);

        $this->complex->modules()
            ->byModule(ComplexModule::SHORT_TERM_RENTAL->value)
            ->update(['enabled' => true]);

        ResidentialComplexModule::create([
            'residential_complex_id' => $this->complex->id,
            'module_key' => ComplexModule::SHORT_TERM_RENTAL->value,
            'enabled' => true,
        ]);
    }
}
