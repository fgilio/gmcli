<?php

it('shows help with gmcli header', function () {
    $this->artisan('default', ['args' => ['help']])
        ->expectsOutputToContain('gmcli - Gmail CLI')
        ->assertSuccessful();
});

it('shows help usage section', function () {
    $this->artisan('default', ['args' => ['help']])
        ->expectsOutputToContain('accounts <action>')
        ->assertSuccessful();
});

it('shows help account commands', function () {
    $this->artisan('default', ['args' => ['help']])
        ->expectsOutputToContain('accounts credentials')
        ->assertSuccessful();
});

it('shows help gmail commands', function () {
    $this->artisan('default', ['args' => ['help']])
        ->expectsOutputToContain('search <query>')
        ->assertSuccessful();
});

it('shows error for unknown command', function () {
    $this->artisan('default', ['args' => ['unknown']])
        ->expectsOutputToContain('Unknown command')
        ->assertFailed();
});

it('shows error for unknown accounts action', function () {
    $this->artisan('default', ['args' => ['accounts', 'unknown']])
        ->expectsOutputToContain('Unknown accounts action')
        ->assertFailed();
});

it('routes to accounts list command', function () {
    $this->artisan('default', ['args' => ['accounts', 'list']])
        ->expectsOutputToContain('No credentials configured')
        ->assertSuccessful();
});
