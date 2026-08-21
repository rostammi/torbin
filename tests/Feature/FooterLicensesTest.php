<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterLicensesTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_contains_verifiable_enamad_and_samandehi_badges_with_fallbacks(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://trustseal.enamad.ir/?id=522515&amp;Code=1b3swSGBmJiCpB9APi3D7QOz5GOSJKC2', false)
            ->assertSee('https://trustseal.enamad.ir/logo.aspx?id=522515&amp;Code=1b3swSGBmJiCpB9APi3D7QOz5GOSJKC2', false)
            ->assertSee('https://logo.samandehi.ir/Verify.aspx?id=371533&amp;p=xlaojyoerfthdshwxlaoxlao', false)
            ->assertSee('https://logo.samandehi.ir/logo.aspx?id=371533&amp;p=qftiyndtnbpdujynqftiqfti', false)
            ->assertSee('class="license-placeholder"', false)
            ->assertSee('onerror="this.hidden=true"', false)
            ->assertSee('نماد اعتماد الکترونیکی')
            ->assertSee('نشان ساماندهی');
    }
}
