<?php

use App\Events\RecordsChanged;
use App\Events\RecordsInvalidated;
use App\Listeners\BroadcastRecordChanges;
use App\Listeners\TrackRecordChanges;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('record changes are emitted only after commit and discarded on rollback', function () {
    config(['realtime.enabled' => true]);
    Event::fake([RecordsChanged::class]);
    DB::transaction(function (): void {
        SupplyRequest::factory()->create();
        Event::assertNotDispatched(RecordsChanged::class);
    });
    Event::assertDispatched(RecordsChanged::class, fn (RecordsChanged $event): bool => $event->scope === 'inventory');

    Event::fake([RecordsChanged::class]);
    DB::beginTransaction();
    SupplyRequest::factory()->create();
    DB::rollBack();
    Event::assertNotDispatched(RecordsChanged::class);
});

test('create update delete and restore all invalidate affected records', function (string $operation) {
    $warehouse = Warehouse::factory()->create();
    if ($operation === 'restore') {
        $warehouse->delete();
    }
    config(['realtime.enabled' => true]);
    Event::fake([RecordsChanged::class]);
    DB::transaction(function () use ($warehouse, $operation): void {
        match ($operation) {
            'create' => Warehouse::factory()->create(),
            'update' => $warehouse->update(['name' => 'Updated stockroom']),
            'delete' => $warehouse->delete(),
            'restore' => $warehouse->restore(),
        };
    });
    Event::assertDispatched(RecordsChanged::class, fn (RecordsChanged $event): bool => $event->scope === 'inventory');
})->with(['create', 'update', 'delete', 'restore']);

test('all operational models are covered without broadcasting model contents', function () {
    config(['realtime.enabled' => true]);
    foreach (File::files(app_path('Models')) as $file) {
        $name = $file->getFilenameWithoutExtension();
        if ($name === 'PropertyDocumentSequence') {
            continue;
        }
        $class = 'App\\Models\\'.$name;
        $model = new $class;
        $model->setAttribute($model->getKeyName(), 7);
        Event::fake([RecordsChanged::class]);
        DB::transaction(fn () => app(TrackRecordChanges::class)->handle('eloquent.updated: '.$class, [$model]));
        Event::assertDispatched(RecordsChanged::class);
        Event::assertDispatched(RecordsChanged::class, function (RecordsChanged $event): bool {
            expect(array_keys(get_object_vars($event)))->toBe(['scope', 'userId']);

            return true;
        });
    }
});

test('queued signals coalesce by scope and account until processing begins', function () {
    Queue::fake();
    DB::transaction(function (): void {
        RecordsChanged::dispatch('inventory');
        RecordsChanged::dispatch('inventory');
        RecordsChanged::dispatch('account', 1);
        RecordsChanged::dispatch('account', 2);
        Queue::assertNothingPushed();
    });
    Queue::assertPushed(CallQueuedListener::class, 3);
});

test('broadcasts contain only a scope and use private channels', function () {
    Event::fake([RecordsInvalidated::class]);
    app(BroadcastRecordChanges::class)->handle(new RecordsChanged('inventory'));
    Event::assertDispatched(RecordsInvalidated::class, function (RecordsInvalidated $event): bool {
        expect($event->broadcastWith())->toBe(['scope' => 'inventory'])
            ->and($event->broadcastOn()[0]->name)->toBe('private-records.inventory')
            ->and($event->broadcastAs())->toBe('records.changed');

        return true;
    });
    expect((new RecordsInvalidated('account', 7))->broadcastOn()[0]->name)->toBe('private-records.account.7');
});

test('broadcast authorization limits administrative and personal subscriptions', function () {
    $employee = User::factory()->employee()->create();
    $admin = User::factory()->superAdmin()->create();
    $inactive = User::factory()->employee()->create(['is_active' => false]);
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
    require base_path('routes/channels.php');
    $url = action([BroadcastController::class, 'authenticate']);
    $payload = ['socket_id' => '123.456', 'channel_name' => 'private-records.inventory'];
    $this->postJson($url, $payload)->assertForbidden();
    $this->actingAs($employee)->postJson($url, $payload)->assertSuccessful()->assertJsonStructure(['auth']);
    $this->postJson($url, [...$payload, 'channel_name' => 'private-records.administration'])->assertForbidden();
    $this->postJson($url, [...$payload, 'channel_name' => 'private-records.account.'.$admin->id])->assertForbidden();
    $this->postJson($url, [...$payload, 'channel_name' => 'private-records.account.'.$employee->id])->assertSuccessful();
    $this->actingAs($admin)->postJson($url, [...$payload, 'channel_name' => 'private-records.administration'])->assertSuccessful();
    $this->actingAs($inactive)->postJson($url, $payload)->assertForbidden();
});

test('shared websocket configuration excludes credentials and guest pages do not connect', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('realtime', null));
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->get(route('inventory.requests.index'))->assertInertia(fn (Assert $page) => $page
        ->has('realtime.enabled')->missing('realtime.secret')->missing('realtime.app_id'));
});

test('the realtime refresh controller groups events and protects drafts and navigation', function () {
    $result = Process::path(base_path())->timeout(60)->run(['node', base_path('tests/RealtimeRefresh.mjs')]);
    expect($result->successful())->toBeTrue($result->errorOutput());
});

test('a private websocket subscriber receives a real Laravel broadcast', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr($address, strrpos($address, ':') + 1);
    $environment = [
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'BROADCAST_CONNECTION' => 'reverb',
        'REVERB_APP_ID' => 'realtime-test', 'REVERB_APP_KEY' => 'realtime-test-key', 'REVERB_APP_SECRET' => 'realtime-test-secret',
        'REVERB_HOST' => '127.0.0.1', 'REVERB_PORT' => (string) $port, 'REVERB_SCHEME' => 'http', 'REVERB_ALLOWED_ORIGINS' => '*',
    ];
    $server = Process::path(base_path())->env($environment)->forever()
        ->start(['php', 'artisan', 'reverb:start', '--host=127.0.0.1', '--port='.$port, '--no-interaction']);
    try {
        $result = Process::path(base_path())->env($environment)->timeout(30)
            ->run(['node', base_path('tests/RealtimeSocket.mjs')]);
        expect($result->successful())->toBeTrue($result->errorOutput().' '.$server->errorOutput())
            ->and($result->output())->toContain('private broadcast received');
    } finally {
        $server->stop(1);
    }
});
