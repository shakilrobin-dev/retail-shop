<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\RepairStatus;
use App\Enums\StockMovementType;
use App\Livewire\Repairs\RepairForm;
use App\Livewire\Repairs\RepairIndex;
use App\Livewire\Repairs\RepairShow;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\RepairJob;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RepairTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create(['code' => 'B1']);
        PaymentMethod::factory()->create(['type' => PaymentMethodType::Cash]);
    }

    private function createJob(float $advance = 0): RepairJob
    {
        Livewire::test(RepairForm::class)
            ->set('phone', '01712345678')
            ->set('customerName', 'Karim')
            ->set('device_brand', 'Samsung')
            ->set('device_model', 'A15')
            ->set('imei_serial', '356789012345678')
            ->set('device_password', '1234')
            ->set('accessories', ['charger', 'sim'])
            ->set('problem', 'Display broken')
            ->set('advance', (string) $advance)
            ->call('save')
            ->assertHasNoErrors();

        return RepairJob::query()->latest('id')->firstOrFail();
    }

    public function test_cashier_creates_job_with_new_customer_and_advance(): void
    {
        $this->actingAsRole('cashier', $this->branch);

        $job = $this->createJob(500);

        $this->assertSame('B1-JOB-000001', $job->job_no);
        $this->assertSame(RepairStatus::Received, $job->status);
        $this->assertSame('1234', $job->device_password, 'Password is readable after decrypt.');
        $this->assertNotSame('1234', $job->getRawOriginal('device_password'), 'Password is stored encrypted.');
        $this->assertSame(['charger', 'sim'], $job->accessories);
        $this->assertSame('500.00', $job->paid_amount);
        $this->assertSame('Karim', Customer::query()->sole()->name);
        $this->assertCount(1, $job->statusHistories);
    }

    public function test_known_phone_fills_customer_name(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        Customer::factory()->create(['name' => 'Rahim', 'phone' => '01799999999']);

        Livewire::test(RepairForm::class)
            ->set('phone', '01799999999')
            ->assertSet('customerName', 'Rahim');
    }

    public function test_full_flow_part_from_stock_charges_and_delivery(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $display = $this->makeProduct(['name' => 'A15 Display'], piecePrice: 2500, pieceBarcode: 'DISP-A15');
        app(StockService::class)->move($this->branch, $display, 3, StockMovementType::Opening);
        $job = $this->createJob(500);

        Livewire::test(RepairShow::class, ['job' => $job])
            ->call('setStatus', 'repairing')
            ->set('productSearch', 'DISP-A15')
            ->call('scan')
            ->set('service_charge', '300')
            ->set('discount', '100')
            ->call('saveCharges')
            ->call('setStatus', 'ready')
            ->call('openDelivery')
            ->assertSet('deliveryPayments.0.amount', '2200')
            ->call('deliver')
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertSame(RepairStatus::Delivered, $job->status);
        $this->assertSame('2700.00', $job->total);
        $this->assertSame('2700.00', $job->paid_amount);
        $this->assertSame(today()->addDays(7)->toDateString(), $job->warranty_until->toDateString());
        $this->assertSame(2.0, app(StockService::class)->quantity($this->branch, $display));
    }

    public function test_delivery_requires_full_payment(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $job = $this->createJob();

        Livewire::test(RepairShow::class, ['job' => $job])
            ->set('service_charge', '500')
            ->call('saveCharges')
            ->call('openDelivery')
            ->set('deliveryPayments.0.amount', '200')
            ->call('deliver')
            ->assertHasErrors('payments');

        $this->assertSame(RepairStatus::Received, $job->fresh()->status);
    }

    public function test_removing_part_puts_it_back_in_stock(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $battery = $this->makeProduct(['name' => 'Battery'], piecePrice: 800, pieceBarcode: 'BAT');
        app(StockService::class)->move($this->branch, $battery, 2, StockMovementType::Opening);
        $job = $this->createJob();

        $component = Livewire::test(RepairShow::class, ['job' => $job])
            ->set('productSearch', 'BAT')->call('scan');

        $this->assertSame(1.0, app(StockService::class)->quantity($this->branch, $battery));

        $component->call('removePart', $job->parts()->sole()->id);

        $this->assertSame(2.0, app(StockService::class)->quantity($this->branch, $battery));
        $this->assertSame('0.00', $job->fresh()->total);
    }

    public function test_using_part_without_stock_shows_error(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $this->makeProduct(['name' => 'Charging port'], piecePrice: 300, pieceBarcode: 'PORT');
        $job = $this->createJob();

        Livewire::test(RepairShow::class, ['job' => $job])
            ->set('productSearch', 'PORT')->call('scan')
            ->assertHasErrors('stock');

        $this->assertSame(0, $job->parts()->count());
    }

    public function test_cancel_refunds_advance_minus_fee(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $job = $this->createJob(500);

        Livewire::test(RepairShow::class, ['job' => $job])
            ->set('cancel_reason', 'Not repairable')
            ->set('cancel_fee', '100')
            ->call('cancelJob')
            ->assertHasNoErrors();

        $job->refresh();
        $this->assertSame(RepairStatus::Cancelled, $job->status);
        $this->assertSame('100.00', $job->paid_amount);
        $this->assertDatabaseHas('payments', ['payable_id' => $job->id, 'direction' => 'out', 'amount' => 400]);
    }

    public function test_warranty_claim_creates_linked_free_job(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $job = $this->createJob();
        Livewire::test(RepairShow::class, ['job' => $job])->call('openDelivery')->call('deliver');

        Livewire::test(RepairShow::class, ['job' => $job->fresh()])
            ->set('warranty_problem', 'Display flickering again')
            ->call('createWarrantyClaim')
            ->assertHasNoErrors();

        $claim = RepairJob::query()->where('warranty_of_job_id', $job->id)->sole();
        $this->assertSame('B1-JOB-000002', $claim->job_no);
        $this->assertSame($job->customer_id, $claim->customer_id);
    }

    public function test_repair_list_search_and_print_pages(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $job = $this->createJob(200);

        Livewire::test(RepairIndex::class)->set('search', '356789012345678')->assertSee($job->job_no);
        Livewire::test(RepairIndex::class)->set('view', 'list')->set('search', '356789012345678')->assertSee($job->job_no);

        $this->get(route('repairs.slip', $job))->assertOk()->assertSee($job->job_no)->assertSee('Display broken');
        $this->get(route('repairs.bill', $job))->assertOk()->assertSee('Karim');
        $this->get(route('repairs.show', $job))->assertOk();
        $this->get(route('repairs.index'))->assertOk();
        $this->get(route('customers.show', $job->customer))->assertOk()->assertSee($job->job_no);
    }

    public function test_dragging_card_on_board_changes_status(): void
    {
        $this->actingAsRole('cashier', $this->branch);
        $job = $this->createJob();

        Livewire::test(RepairIndex::class)
            ->assertSee($job->job_no)
            ->call('moveTo', $job->id, 'repairing');

        $this->assertSame(RepairStatus::Repairing, $job->fresh()->status);
    }
}
