<?php

beforeEach(function () {
    config(['memorylane.username' => 'ops', 'memorylane.password' => 's3cret-key']);
});

it('sends visitors to the login screen when credentials are set', function () {
    $this->get('/memorylane')->assertRedirect('/memorylane/login');
    $this->get('/memorylane/1')->assertRedirect('/memorylane/login');
    $this->get('/memorylane/login')->assertOk()->assertSee('Sign in')->assertDontSee('sampling');
});

it('stays locked and shows setup steps when credentials are not set', function () {
    config(['memorylane.username' => null, 'memorylane.password' => null]);

    $this->get('/memorylane')->assertRedirect('/memorylane/login');
    $this->get('/memorylane/login')->assertOk()->assertSee('No login is set up yet')->assertDontSee('name="password"', false);
    $this->post('/memorylane/login', ['username' => '', 'password' => ''])->assertNotFound();
});

it('rejects wrong credentials', function () {
    $this->post('/memorylane/login', ['username' => 'ops', 'password' => 'nope'])
        ->assertSessionHasErrors('login');

    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('lets the right credentials in, and logs out', function () {
    $this->post('/memorylane/login', ['username' => 'ops', 'password' => 's3cret-key'])
        ->assertRedirect('/memorylane');

    $this->get('/memorylane')->assertOk()->assertSee('Log out');

    $this->post('/memorylane/logout')->assertRedirect('/memorylane/login');
    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('logs everyone out when the password in .env changes', function () {
    $this->post('/memorylane/login', ['username' => 'ops', 'password' => 's3cret-key']);
    $this->get('/memorylane')->assertOk();

    config(['memorylane.password' => 'rotated-key']);

    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('throttles guessing after 5 tries a minute', function () {
    foreach (range(1, 5) as $try) {
        $this->post('/memorylane/login', ['username' => 'ops', 'password' => "guess-{$try}"]);
    }

    $this->post('/memorylane/login', ['username' => 'ops', 'password' => 's3cret-key'])->assertStatus(429);
});

it('asks for the login in local too', function () {
    app()['env'] = 'local';

    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('keeps local locked even with no credentials set', function () {
    app()['env'] = 'local';
    config(['memorylane.username' => null, 'memorylane.password' => null]);

    $this->get('/memorylane')->assertRedirect('/memorylane/login');
});

it('still lets the gate in without a login (host-granted)', function () {
    allowDashboard();

    $this->get('/memorylane')->assertOk()->assertDontSee('Log out');
});
