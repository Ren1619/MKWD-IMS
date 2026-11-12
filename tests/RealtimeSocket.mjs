import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const port = process.env.REVERB_PORT;
const key = process.env.REVERB_APP_KEY;
const secret = process.env.REVERB_APP_SECRET;
const channel = 'private-records.inventory';
let socket;
for (let attempt = 0; attempt < 60; attempt++) {
    try {
        await fetch(`http://127.0.0.1:${port}/up`);
        break;
    } catch {
        await delay(100);
    }
}

await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error('WebSocket broadcast timed out')), 15000);
    socket = new WebSocket(`ws://127.0.0.1:${port}/app/${key}?protocol=7&client=js&version=8.4.0&flash=false`);
    socket.addEventListener('error', () => reject(new Error('WebSocket connection failed')));
    socket.addEventListener('message', ({ data }) => {
        try {
            const event = JSON.parse(data);
            const payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            if (event.event === 'pusher:connection_established') {
                const signature = createHmac('sha256', secret).update(`${payload.socket_id}:${channel}`).digest('hex');
                socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: `${key}:${signature}` } }));
            } else if (event.event === 'pusher_internal:subscription_succeeded') {
                const result = spawnSync('php', ['-r',
                    "require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); app(App\\Listeners\\BroadcastRecordChanges::class)->handle(new App\\Events\\RecordsChanged('inventory'));",
                ], { env: process.env, encoding: 'utf8', timeout: 10000 });
                assert.equal(result.status, 0, result.stderr || result.stdout);
            } else if (event.event === 'records.changed') {
                assert.equal(event.channel, channel);
                assert.deepEqual(payload, { scope: 'inventory' });
                clearTimeout(timeout);
                resolve();
            } else if (event.event === 'pusher:error') {
                throw new Error(JSON.stringify(payload));
            }
        } catch (error) {
            clearTimeout(timeout);
            reject(error);
        }
    });
}).finally(() => socket?.close());
process.stdout.write('private broadcast received');
