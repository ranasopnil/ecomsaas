<?php

namespace Tests\Unit;

use App\Support\HtmlSanitiser;
use PHPUnit\Framework\TestCase;

class HtmlSanitiserTest extends TestCase
{
    protected HtmlSanitiser $sanitiser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitiser = new HtmlSanitiser;
    }

    public function test_ordinary_formatting_is_kept(): void
    {
        $html = '<h2>Cotton Panjabi</h2><p><strong>Soft</strong> and <em>light</em>.</p><ul><li>Machine washable</li></ul>';

        $this->assertSame($html, $this->sanitiser->clean($html));
    }

    public function test_a_script_is_removed_entirely(): void
    {
        $clean = $this->sanitiser->clean('<p>Hello</p><script>alert("stealing your session")</script>');

        $this->assertSame('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('alert', (string) $clean);
    }

    public function test_click_handlers_are_stripped_but_the_words_stay(): void
    {
        $clean = $this->sanitiser->clean('<p onclick="steal()">Buy now</p>');

        $this->assertSame('<p>Buy now</p>', $clean);
    }

    public function test_a_javascript_link_becomes_plain_text(): void
    {
        $clean = $this->sanitiser->clean('<a href="javascript:alert(1)">Tap here</a>');

        $this->assertSame('Tap here', $clean);
    }

    public function test_a_javascript_link_hidden_with_spacing_is_still_caught(): void
    {
        $clean = $this->sanitiser->clean('<a href="java&#09;script:alert(1)">Tap</a>');

        $this->assertStringNotContainsString('script:', (string) $clean);
    }

    public function test_a_real_link_survives_and_is_made_safe_in_a_new_tab(): void
    {
        $clean = $this->sanitiser->clean('<a href="https://example.com" target="_blank">Our size guide</a>');

        $this->assertStringContainsString('href="https://example.com"', (string) $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', (string) $clean);
    }

    public function test_frames_and_forms_cannot_be_smuggled_in(): void
    {
        $clean = $this->sanitiser->clean(
            '<p>Real text</p><iframe src="https://evil.example"></iframe><form action="/steal"><input name="card"></form>'
        );

        $this->assertSame('<p>Real text</p>', $clean);
    }

    public function test_pictures_are_allowed_but_not_from_a_script_source(): void
    {
        $good = $this->sanitiser->clean('<img src="/storage/shops/1/photo.jpg" alt="Panjabi">');
        $this->assertStringContainsString('<img src="/storage/shops/1/photo.jpg" alt="Panjabi">', (string) $good);

        $bad = $this->sanitiser->clean('<img src="javascript:alert(1)">');
        $this->assertNull($bad);
    }

    public function test_layout_styles_are_kept_and_dangerous_ones_dropped(): void
    {
        $clean = $this->sanitiser->clean('<p style="text-align: center; background: url(javascript:alert(1)); color: #b30000">Sale</p>');

        $this->assertStringContainsString('text-align: center', (string) $clean);
        $this->assertStringContainsString('color: #b30000', (string) $clean);
        $this->assertStringNotContainsString('url(', (string) $clean);
    }

    public function test_empty_input_stays_empty(): void
    {
        $this->assertNull($this->sanitiser->clean(''));
        $this->assertNull($this->sanitiser->clean(null));
        $this->assertNull($this->sanitiser->clean('   '));
    }

    public function test_bangla_and_arabic_text_survive_untouched(): void
    {
        $html = '<p>সুতির পাঞ্জাবি</p><p>قميص قطني</p>';

        $this->assertSame($html, $this->sanitiser->clean($html));
    }

    public function test_a_table_of_measurements_is_kept(): void
    {
        $html = '<table><tbody><tr><th>Size</th><td>Chest</td></tr><tr><td>M</td><td>40"</td></tr></tbody></table>';

        $this->assertStringContainsString('<table>', (string) $this->sanitiser->clean($html));
        $this->assertStringContainsString('Chest', (string) $this->sanitiser->clean($html));
    }
}
