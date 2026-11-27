<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->templatesPath = config('react-email.path');
    $this->buildPath = config('react-email.build_path');

    File::ensureDirectoryExists($this->templatesPath);
});

afterEach(function () {
    File::deleteDirectory($this->buildPath);
    File::deleteDirectory($this->templatesPath);
});

function validTemplate(): string
{
    return <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';

export default function ValidEmail() {
  return <Html><Text>Hello $$name$$</Text></Html>;
}
TSX;
}

it('succeeds when the template directory is empty', function () {
    $this->artisan('react-email:build')
        ->expectsOutputToContain('Nothing to build')
        ->assertSuccessful();
});

it('fails for a template name that does not exist', function () {
    $this->artisan('react-email:build', ['templates' => ['missing']])
        ->expectsOutputToContain('Template not found')
        ->assertFailed();
});

it('builds templates and skips unchanged ones on the next run', function () {
    File::put("{$this->templatesPath}/welcome.tsx", validTemplate());

    $this->artisan('react-email:build')->assertSuccessful();

    expect(File::exists("{$this->buildPath}/welcome.blade.php"))->toBeTrue()
        ->and(File::exists("{$this->buildPath}/welcome-text.blade.php"))->toBeTrue();

    $this->artisan('react-email:build')
        ->expectsOutputToContain('Skipped (unchanged)')
        ->assertSuccessful();

    $this->artisan('react-email:build', ['--force' => true])
        ->expectsOutputToContain('Successfully built templates')
        ->assertSuccessful();
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('rebuilds templates when shared components change', function () {
    File::ensureDirectoryExists("{$this->templatesPath}/components");
    File::put("{$this->templatesPath}/components/greeting.tsx", <<<'TSX'
import React from 'react';
import { Text } from '@react-email/components';

export const Greeting = () => <Text>Hi there</Text>;
TSX);
    File::put("{$this->templatesPath}/shared.tsx", <<<'TSX'
import React from 'react';
import { Html } from '@react-email/components';
import { Greeting } from './components/greeting';

export default function SharedEmail() {
  return <Html><Greeting /></Html>;
}
TSX);

    $this->artisan('react-email:build')->assertSuccessful();

    File::put(
        "{$this->templatesPath}/components/greeting.tsx",
        str_replace('Hi there', 'Welcome back', File::get("{$this->templatesPath}/components/greeting.tsx")),
    );

    $this->artisan('react-email:build')->assertSuccessful();

    expect(File::get("{$this->buildPath}/shared.blade.php"))->toContain('Welcome back');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('builds .jsx templates', function () {
    File::put("{$this->templatesPath}/plain.jsx", <<<'JSX'
import React from 'react';
import { Html, Text } from '@react-email/components';

export default function PlainEmail() {
  return <Html><Text>Hello $$name$$</Text></Html>;
}
JSX);

    $this->artisan('react-email:build', ['templates' => ['plain']])->assertSuccessful();

    expect(File::get("{$this->buildPath}/plain.blade.php"))->toContain('{{ $name }}');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('fails when a template cannot be built but still builds the others', function () {
    File::put("{$this->templatesPath}/valid.tsx", validTemplate());
    File::put("{$this->templatesPath}/broken.tsx", "export default 'not a component';\n");

    $this->artisan('react-email:build')
        ->expectsOutputToContain('Failed to build broken')
        ->assertFailed();

    expect(File::exists("{$this->buildPath}/valid.blade.php"))->toBeTrue();
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('removes compiled files of deleted templates with --clean', function () {
    File::put("{$this->templatesPath}/temporary.tsx", validTemplate());

    $this->artisan('react-email:build')->assertSuccessful();

    File::delete("{$this->templatesPath}/temporary.tsx");

    $this->artisan('react-email:build', ['--clean' => true])
        ->expectsOutputToContain('temporary')
        ->assertSuccessful();

    expect(File::exists("{$this->buildPath}/temporary.blade.php"))->toBeFalse();
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');
