<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Non-regression guards for shared TV+A OS interface primitives.
 * Integration behaviour (Tab/Escape/mobile layout) also requires browser QA.
 */
class UiDesignSystemTest extends TestCase
{
    public function test_shared_buttons_use_brand_colours_and_touch_sized_targets(): void
    {
        $primary = Blade::render('<x-primary-button>Enregistrer</x-primary-button>');
        $secondary = Blade::render('<x-secondary-button>Annuler</x-secondary-button>');
        $danger = Blade::render('<x-danger-button>Supprimer</x-danger-button>');

        $this->assertStringContainsString('bg-primary', $primary);
        $this->assertStringContainsString('bg-white', $secondary);
        $this->assertStringContainsString('bg-error', $danger);

        foreach ([$primary, $secondary, $danger] as $html) {
            $this->assertStringContainsString('min-h-11', $html);
            $this->assertStringContainsString('focus-visible:outline', $html);
            $this->assertStringNotContainsString('bg-gray-800', $html);
        }
    }

    public function test_shared_form_components_expose_required_invalid_and_error_states(): void
    {
        $field = Blade::render('<x-text-input id="titre" name="titre" :invalid="true" />');
        $label = Blade::render('<x-input-label for="titre" required value="Titre" />');
        $error = Blade::render('<x-input-error :messages="[\'Champ obligatoire\']" />');

        $this->assertStringContainsString('aria-invalid="true"', $field);
        $this->assertStringContainsString('min-h-11', $field);
        $this->assertStringContainsString('for="titre"', $label);
        $this->assertStringContainsString('(obligatoire)', $label);
        $this->assertStringContainsString('role="alert"', $error);
    }

    public function test_shared_modal_has_a_label_focus_trap_and_focus_restoration(): void
    {
        $html = Blade::render(
            '<x-modal name="edition-sujet" label="Modifier un sujet"><button type="button">Fermer</button></x-modal>',
        );

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('Modifier un sujet', $html);
        $this->assertStringContainsString('trapTab(', $html);
        $this->assertStringContainsString('previousFocus', $html);
        $this->assertStringContainsString('close-modal.window', $html);
    }

    public function test_dropdown_exposes_menu_state_and_keyboard_navigation(): void
    {
        $html = Blade::render(
            '<x-dropdown><x-slot name="trigger"><button type="button">Actions</button></x-slot>'.
            '<x-slot name="content"><x-dropdown-link href="/test">Ouvrir</x-dropdown-link></x-slot></x-dropdown>',
        );

        $this->assertStringContainsString('role="menu"', $html);
        $this->assertStringContainsString('role="menuitem"', $html);
        $this->assertStringContainsString('aria-expanded', $html);
        $this->assertStringContainsString('keydown.arrow-down', $html);
    }

    public function test_empty_state_and_table_shell_have_accessible_structure(): void
    {
        $empty = Blade::render('<x-ui.empty-state title="Aucune édition" description="Créez une édition." />');
        $table = Blade::render('<x-ui.table-shell label="Liste des éditions"><table><tr><td>Un</td></tr></table></x-ui.table-shell>');
        $badge = Blade::render('<x-ui.status-badge variant="success">Validé</x-ui.status-badge>');

        $this->assertStringContainsString('Aucune édition', $empty);
        $this->assertStringContainsString('ui-empty-state', $empty);
        $this->assertStringContainsString('aria-label="Liste des éditions"', $table);
        $this->assertStringContainsString('tabindex="0"', $table);
        $this->assertStringContainsString('text-success', $badge);
    }

    public function test_app_shell_has_accessible_navigation_and_emission_wizard_tabs(): void
    {
        $layout = File::get(resource_path('views/layouts/app.blade.php'));
        $header = File::get(resource_path('views/_partials/page-header.blade.php'));
        $sidebar = File::get(resource_path('views/_partials/sidebar.blade.php'));
        $wizard = File::get(resource_path('views/_partials/form-emission.blade.php'));
        $wizardScript = File::get(resource_path('js/emission-form.js'));

        $this->assertStringContainsString('href="#app-main"', $layout);
        $this->assertStringContainsString('id="app-main"', $layout);
        $this->assertStringContainsString('aria-controls="app-sidebar"', $header);
        $this->assertStringContainsString('aria-controls="tv-sidebar-conducteurs"', $sidebar);
        $this->assertStringContainsString('role="tabpanel"', $wizard);
        $this->assertStringContainsString("event.key === 'ArrowRight'", $wizardScript);
    }
}
