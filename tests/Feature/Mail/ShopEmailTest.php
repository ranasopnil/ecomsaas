<?php

namespace Tests\Feature\Mail;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Livewire\Admin\MailSettingsForm;
use App\Mail\TestMail;
use App\Models\MailSetting;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscribeToPackage;
use App\Services\Mail\MailerBuilder;
use App\Services\Mail\TenantMailer;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ShopEmailTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Tenant::factory()->create(['currency' => 'BDT', 'name' => 'Dhaka Fashion']);
        app(SubscribeToPackage::class)->handle($this->store, Package::factory()->allowing(['products' => 50])->create());

        Entitlements::forget();
        Tenancy::set($this->store);

        $this->actingAs(User::factory()->create(['tenant_id' => $this->store->id, 'email' => 'owner@dhaka.example']));
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        Entitlements::forget();

        parent::tearDown();
    }

    /**
     * Stand in for the shop's mail server: either accept everything, or fail
     * with what a real server would say. Records what was asked of it.
     *
     * @return object{configs: array<int, array<string, mixed>>, sent: array<int, mixed>}
     */
    protected function fakeServer(?string $failsWith = null): object
    {
        $recorder = new class
        {
            public array $configs = [];

            public array $sent = [];
        };

        $this->swap(MailerBuilder::class, new class($recorder, $failsWith) extends MailerBuilder
        {
            public function __construct(protected object $recorder, protected ?string $failsWith) {}

            public function build(array $config, string $fromAddress, string $fromName): Mailer
            {
                $this->recorder->configs[] = $config + ['from' => $fromAddress];

                if ($this->failsWith !== null) {
                    throw new RuntimeException($this->failsWith);
                }

                // A real mailer with nowhere to send, so nothing leaves the box.
                $mailer = Mail::mailer('array');
                $this->recorder->sent[] = $config['host'];

                return $mailer;
            }
        });

        return $recorder;
    }

    protected function fillIn(): Testable
    {
        return Livewire::test(MailSettingsForm::class)
            ->set('host', 'smtp.example.com')
            ->set('port', '587')
            ->set('encryption', 'tls')
            ->set('username', 'orders@dhaka.example')
            ->set('password', 'app-password-xyz')
            ->set('from_address', 'orders@dhaka.example')
            ->set('from_name', 'Dhaka Fashion');
    }

    public function test_settings_are_saved_with_the_password_encrypted(): void
    {
        $this->fillIn()->call('save')->assertHasNoErrors()->assertDispatched('toast');

        $settings = MailSetting::firstOrFail();

        $this->assertSame('smtp.example.com', $settings->host);
        $this->assertSame('app-password-xyz', $settings->password);

        // On disk it is scrambled, not the plain word.
        $raw = DB::table('mail_settings')->value('password');
        $this->assertNotSame('app-password-xyz', $raw);
        $this->assertStringNotContainsString('app-password', $raw);
    }

    public function test_the_password_is_never_sent_back_to_the_screen(): void
    {
        $this->fillIn()->call('save');

        $component = Livewire::test(MailSettingsForm::class);

        $component->assertSet('password', '')->assertSet('hasPassword', true);
        $this->assertStringNotContainsString('app-password-xyz', $component->html());

        // Nor does it leak through the model's array or json form.
        $this->assertArrayNotHasKey('password', MailSetting::firstOrFail()->toArray());
    }

    public function test_leaving_the_password_blank_keeps_the_stored_one(): void
    {
        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)
            ->set('from_name', 'Dhaka Fashion Ltd')
            ->set('password', '')
            ->call('save')
            ->assertHasNoErrors();

        $settings = MailSetting::firstOrFail();

        $this->assertSame('Dhaka Fashion Ltd', $settings->from_name);
        $this->assertSame('app-password-xyz', $settings->password);
    }

    public function test_a_new_password_replaces_the_old_one_outright(): void
    {
        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)->set('password', 'a-newer-one')->call('save');

        $this->assertSame('a-newer-one', MailSetting::firstOrFail()->password);
    }

    public function test_a_passing_test_email_marks_the_settings_as_working(): void
    {
        $server = $this->fakeServer();

        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)
            ->set('test_to', 'owner@dhaka.example')
            ->call('sendTest')
            ->assertDispatched('toast');

        $settings = MailSetting::firstOrFail();

        $this->assertSame(MailSetting::STATUS_WORKING, $settings->status);
        $this->assertSame(['smtp.example.com'], $server->sent);
        $this->assertSame('orders@dhaka.example', $server->configs[0]['from']);
        $this->assertSame('app-password-xyz', $server->configs[0]['password'], 'The real password reaches the server, and nowhere else.');
    }

    public function test_a_failing_test_email_is_explained_in_plain_words(): void
    {
        $this->fakeServer(failsWith: 'Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed"');

        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)->set('test_to', 'owner@dhaka.example')->call('sendTest');

        $settings = MailSetting::firstOrFail();

        $this->assertSame(MailSetting::STATUS_FAILING, $settings->status);
        $this->assertStringContainsString('username or password', $settings->last_test_result);
        $this->assertStringNotContainsString('535', $settings->last_test_result);
    }

    public function test_an_unreachable_server_is_explained_too(): void
    {
        $this->fakeServer(failsWith: 'Connection could not be established with host "smtp.example.com:587": Connection refused');

        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)->set('test_to', 'owner@dhaka.example')->call('sendTest');

        $this->assertStringContainsString('Could not reach the server', MailSetting::firstOrFail()->last_test_result);
    }

    public function test_shop_email_only_uses_the_shops_server_once_proven(): void
    {
        $server = $this->fakeServer();
        Mail::fake();

        $this->fillIn()->set('is_enabled', true)->call('save');

        // Switched on but untested: still goes through the platform.
        app(TenantMailer::class)->send(new TestMail('Dhaka Fashion'), 'customer@example.com');
        Mail::assertSent(TestMail::class);
        $this->assertSame([], $server->sent);
    }

    public function test_once_proven_and_switched_on_shop_email_goes_through_the_shops_server(): void
    {
        $server = $this->fakeServer();

        $this->fillIn()->set('is_enabled', true)->call('save');
        MailSetting::firstOrFail()->forceFill(['status' => MailSetting::STATUS_WORKING])->save();

        app(TenantMailer::class)->send(new TestMail('Dhaka Fashion'), 'customer@example.com');

        $this->assertSame(['smtp.example.com'], $server->sent);
    }

    public function test_changing_the_server_means_proving_it_again(): void
    {
        $this->fillIn()->call('save');
        MailSetting::firstOrFail()->forceFill(['status' => MailSetting::STATUS_WORKING])->save();

        Livewire::test(MailSettingsForm::class)->set('host', 'smtp.other.example')->call('save');

        $this->assertSame(MailSetting::STATUS_UNTESTED, MailSetting::firstOrFail()->status);
    }

    public function test_changing_only_the_from_name_does_not_undo_the_proof(): void
    {
        $this->fillIn()->call('save');
        MailSetting::firstOrFail()->forceFill(['status' => MailSetting::STATUS_WORKING])->save();

        Livewire::test(MailSettingsForm::class)->set('from_name', 'Dhaka Fashion Ltd')->call('save');

        $this->assertSame(MailSetting::STATUS_WORKING, MailSetting::firstOrFail()->status);
    }

    public function test_removing_the_settings_sends_email_through_the_platform_again(): void
    {
        $this->fillIn()->call('save');

        Livewire::test(MailSettingsForm::class)->call('forget')->assertSet('host', '');

        $this->assertSame(0, MailSetting::count());
        $this->assertNull(app(TenantMailer::class)->settings());
    }

    public function test_one_shop_never_sees_or_uses_another_shops_email_settings(): void
    {
        $server = $this->fakeServer();

        $this->fillIn()->set('is_enabled', true)->call('save');
        MailSetting::firstOrFail()->forceFill(['status' => MailSetting::STATUS_WORKING])->save();

        $other = Tenant::factory()->create(['currency' => 'BDT']);
        app(SubscribeToPackage::class)->handle($other, Package::factory()->allowing(['products' => 10])->create());

        Mail::fake();

        Tenancy::run($other, function () {
            $this->assertSame(0, MailSetting::count());
            $this->assertNull(app(TenantMailer::class)->settings());

            app(TenantMailer::class)->send(new TestMail('Other'), 'someone@example.com');
        });

        $this->assertSame([], $server->sent, 'The other shop must not borrow this shop\'s server.');
    }

    public function test_a_hostname_that_is_not_a_hostname_is_refused(): void
    {
        $this->fillIn()->set('host', 'not a host name')->call('save')->assertHasErrors('host');
    }
}
