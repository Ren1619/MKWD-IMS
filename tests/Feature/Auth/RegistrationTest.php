<?php

test('public registration is disabled because accounts are admin managed', function () {
    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Unapproved User',
        'email' => 'unapproved@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', [
        'email' => 'unapproved@example.test',
    ]);
});
