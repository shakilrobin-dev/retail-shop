<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Livewire\Stock\StockAdjustmentForm;
use App\Livewire\Stock\StockTransferForm;
use App\Livewire\Stock\StockTransferShow;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_damaged_stock(): void
    {
        $user = $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Cable'], pieceBarcode: 'CBL');
        app(StockService::class)->move($user->branch, $product, 10, StockMovementType::Opening);

        Livewire::test(StockAdjustmentForm::class)
            ->set('productSearch', 'CBL')->call('scan')
            ->set('lines.0.value', '-3')
            ->set('reason', 'damaged')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7.0, app(StockService::class)->quantity($user->branch, $product));
        $this->assertDatabaseHas('stock_adjustments', ['reason' => 'damaged']);
    }

    public function test_stock_count_saves_the_difference(): void
    {
        $user = $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Cover'], pieceBarcode: 'CVR');
        app(StockService::class)->move($user->branch, $product, 10, StockMovementType::Opening);

        Livewire::test(StockAdjustmentForm::class)
            ->set('mode', 'count')
            ->set('productSearch', 'CVR')->call('scan')
            ->set('productSearch', 'CVR')->call('scan')
            ->assertSet('lines.0.value', '2')
            ->set('lines.0.value', '8')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(8.0, app(StockService::class)->quantity($user->branch, $product));
        $this->assertDatabaseHas('stock_adjustment_items', ['quantity' => -2]);
    }

    public function test_transfer_send_and_receive_with_shortage(): void
    {
        $main = Branch::factory()->create(['code' => 'B1']);
        $second = Branch::factory()->create(['code' => 'B2']);
        $admin = $this->actingAsRole('admin', $main);
        $product = $this->makeProduct(['name' => 'Charger'], pieceBarcode: 'CHG');
        app(StockService::class)->move($main, $product, 20, StockMovementType::Opening);

        Livewire::test(StockTransferForm::class)
            ->set('to_branch_id', $second->id)
            ->set('productSearch', 'CHG')->call('scan')
            ->set('lines.0.quantity', '5')
            ->call('save')
            ->assertHasNoErrors();

        $transfer = StockTransfer::query()->with('items')->sole();
        $this->assertSame(15.0, app(StockService::class)->quantity($main, $product));
        $this->assertSame(0.0, app(StockService::class)->quantity($second, $product));

        $receiver = $this->actingAsRole('manager', $second);
        $this->actingAs($receiver);

        Livewire::test(StockTransferShow::class, ['transfer' => $transfer])
            ->set('received.'.$transfer->items->first()->id, '4')
            ->call('receive')
            ->assertHasNoErrors();

        $this->assertSame(TransferStatus::Received, $transfer->fresh()->status);
        $this->assertSame(4.0, app(StockService::class)->quantity($second, $product));
        $this->assertSame('4.000', $transfer->items->first()->fresh()->quantity_received);
    }

    public function test_cancelled_transfer_returns_stock(): void
    {
        $main = Branch::factory()->create(['code' => 'B1']);
        $second = Branch::factory()->create(['code' => 'B2']);
        $this->actingAsRole('admin', $main);
        $product = $this->makeProduct(['name' => 'Charger'], pieceBarcode: 'CHG');
        app(StockService::class)->move($main, $product, 20, StockMovementType::Opening);

        Livewire::test(StockTransferForm::class)
            ->set('to_branch_id', $second->id)
            ->set('productSearch', 'CHG')->call('scan')
            ->set('lines.0.quantity', '5')
            ->call('save');

        Livewire::test(StockTransferShow::class, ['transfer' => StockTransfer::query()->sole()])->call('cancel');

        $this->assertSame(20.0, app(StockService::class)->quantity($main, $product));
    }

    public function test_stock_pages_render(): void
    {
        $user = $this->actingAsRole('admin');
        $product = $this->makeProduct(['name' => 'Earbuds']);
        app(StockService::class)->move($user->branch, $product, 3, StockMovementType::Opening);

        $this->get(route('stock.index'))->assertOk()->assertSee('Earbuds');
        $this->get(route('stock.movements', ['productId' => $product->id]))->assertOk()->assertSee(StockMovementType::Opening->label());
        $this->get(route('stock.adjust'))->assertOk();
        $this->get(route('stock.transfers.index'))->assertOk();
        $this->get(route('stock.transfers.create'))->assertOk();
    }
}
