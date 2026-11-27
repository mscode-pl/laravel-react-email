<?php

/**
 * Blade rendering: numeric proxies, BladeForEach, BladeIf, blade.array().map()
 * and plain $$var$$ strings.
 *
 * Tests that need Node.js are skipped when node_modules is not installed.
 */

use MsCodePL\LaravelReactEmail\Services\ReactEmailBuilder;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function buildTestTemplate(string $name, string $tsxContent): array
{
    $builder      = app(ReactEmailBuilder::class);
    $templatesDir = config('react-email.path');

    File::put("{$templatesDir}/{$name}.tsx", $tsxContent);

    return $builder->buildTemplate($name);
}

function readBuiltHtml(array $paths): string
{
    return File::get($paths[0]);
}

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->templatesPath = config('react-email.path');
    $this->buildPath     = config('react-email.build_path');

    File::makeDirectory($this->templatesPath, 0755, true, true);
    File::makeDirectory($this->buildPath, 0755, true, true);
});

afterEach(function () {
    File::deleteDirectory($this->buildPath);
    File::deleteDirectory($this->templatesPath);
});

// ---------------------------------------------------------------------------
// Backward compatibility
// ---------------------------------------------------------------------------

it('converts plain $$var$$ string placeholders to Blade variables', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';

export default function BackwardCompatEmail({ firstName = 'User' }) {
  return (
    <Html>
      <Text>Hello, $$firstName$$!</Text>
      <Text>Email: $$user.email$$</Text>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('backward-compat', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain('{{ $firstName }}')
        ->toContain("{{ \$user['email'] }}");
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Numeric proxy
// ---------------------------------------------------------------------------

it('handles numeric placeholder with .toFixed() without crashing', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';

export default function NumericEmail({ price = '$$price$$' as any as number }) {
  return (
    <Html>
      <Text>Price: {price.toFixed(2)} zł</Text>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('numeric-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)->toContain('{{ $price }}');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('handles blade.number() helper with .toFixed()', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { blade } from '../../scripts/index';

export default function BladeNumberEmail({
    total = blade.number('total'),
    fee   = blade.number('fee'),
}) {
  return (
    <Html>
      <Text>Total: {total.toFixed(2)} zł</Text>
      <Text>Fee: {fee.toFixed(2)} zł</Text>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('blade-number-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain('{{ $total }}')
        ->toContain('{{ $fee }}');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// BladeForEach component
// ---------------------------------------------------------------------------

it('converts BladeForEach component to @foreach Blade directive', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeForEach } from '../../scripts/index';

export default function ForeachEmail() {
  return (
    <Html>
      <BladeForEach items="items">
        <Text>$$item.name$$: $$item.price$$ zł</Text>
      </BladeForEach>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('foreach-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain('@foreach($items as $item)')
        ->toContain('@endforeach')
        ->toContain("{{ \$item['name'] }}")
        ->toContain("{{ \$item['price'] }}");
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('converts nested BladeForEach (order.items) to correct @foreach', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeForEach } from '../../scripts/index';

export default function NestedForeachEmail() {
  return (
    <Html>
      <BladeForEach items="order.items">
        <Text>$$item.name$$</Text>
      </BladeForEach>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('nested-foreach-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain("@foreach(\$order['items'] as \$item)")
        ->toContain('@endforeach');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// BladeIf component
// ---------------------------------------------------------------------------

it('converts BladeIf component to @if Blade directive', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeIf } from '../../scripts/index';

export default function IfEmail() {
  return (
    <Html>
      <BladeIf condition="serviceFee > 0">
        <Text>Fee: $$serviceFee$$ zł</Text>
      </BladeIf>
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('if-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain('@if($serviceFee > 0)')
        ->toContain('@endif')
        ->toContain('{{ $serviceFee }}');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Auto-proxy .map()
// ---------------------------------------------------------------------------

it('converts blade.array().map() to @foreach Blade directive', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { blade } from '../../scripts/index';

export default function AutoForeachEmail({
    items = blade.array('items'),
}) {
  return (
    <Html>
      {items.map((item: any) => (
        <Text key={String(item.id)}>{item.name}: {item.price.toFixed(2)} zł</Text>
      ))}
    </Html>
  );
}
TSX;

    $paths = buildTestTemplate('auto-foreach-email', $tsx);
    $html  = readBuiltHtml($paths);

    expect($html)
        ->toContain('@foreach($items as $item)')
        ->toContain('@endforeach')
        ->toContain("{{ \$item['name'] }}")
        ->toContain("{{ \$item['price'] }}");
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Combined order email
// ---------------------------------------------------------------------------

it('builds order-email.tsx with all features combined', function () {
    $builder = app(ReactEmailBuilder::class);

    // Use the pre-existing test template from tests/emails/order-email.tsx
    $srcPath = __DIR__ . '/../emails/order-email.tsx';
    $dstDir  = config('react-email.path');

    File::copy($srcPath, "{$dstDir}/order-email.tsx");

    $paths = $builder->buildTemplate('order-email');
    $html  = File::get($paths[0]);

    // Backward-compat placeholders
    expect($html)
        ->toContain('{{ $customerName }}')
        ->toContain('{{ $orderNumber }}');

    // Numeric proxies
    expect($html)
        ->toContain('{{ $subtotal }}')
        ->toContain('{{ $total }}');

    // BladeForEach (explicit component)
    expect($html)
        ->toContain('@foreach($items as $item)')
        ->toContain('@endforeach');

    // BladeIf
    expect($html)
        ->toContain('@if($serviceFee > 0)')
        ->toContain('@endif');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Compiled output renders through Blade
// ---------------------------------------------------------------------------

it('compiles nested BladeForEach loops with custom loop variables', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeForEach } from '../../scripts/index';

export default function NestedLoopsEmail() {
  return (
    <Html>
      <BladeForEach items="orders" as="order">
        <Text>Order $$order.number$$</Text>
        <BladeForEach items="order.lines" as="line">
          <Text>Line $$line.name$$</Text>
        </BladeForEach>
      </BladeForEach>
    </Html>
  );
}
TSX;

    $html = readBuiltHtml(buildTestTemplate('nested-loops', $tsx));

    expect($html)
        ->toContain('@foreach($orders as $order)')
        ->toContain("@foreach(\$order['lines'] as \$line)")
        ->not->toContain('<blade-foreach');

    $rendered = view('react-email.nested-loops', ['orders' => [
        ['number' => 'A-1', 'lines' => [['name' => 'Mug'], ['name' => 'Tea']]],
        ['number' => 'A-2', 'lines' => [['name' => 'Spoon']]],
    ]])->render();

    expect($rendered)
        ->toContain('Order A-1')
        ->toContain('Line Mug')
        ->toContain('Line Tea')
        ->toContain('Order A-2')
        ->toContain('Line Spoon');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('keeps string literals intact in BladeIf conditions', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeIf, BladeElseIf, BladeElse } from '../../scripts/index';

export default function PlanEmail() {
  return (
    <Html>
      <BladeIf condition="plan === 'premium' && count(seats) > 1">
        <Text>Premium team</Text>
        <BladeElseIf condition="plan === 'premium'">
          <Text>Premium solo</Text>
        </BladeElseIf>
        <BladeElse>
          <Text>Free plan</Text>
        </BladeElse>
      </BladeIf>
    </Html>
  );
}
TSX;

    $html = readBuiltHtml(buildTestTemplate('plan-email', $tsx));

    expect($html)
        ->toContain("@if(\$plan === 'premium' && count(\$seats) > 1)")
        ->toContain("@elseif(\$plan === 'premium')")
        ->toContain('@else');

    expect(view('react-email.plan-email', ['plan' => 'premium', 'seats' => [1, 2]])->render())
        ->toContain('Premium team')->not->toContain('Premium solo')->not->toContain('Free plan');

    expect(view('react-email.plan-email', ['plan' => 'premium', 'seats' => [1]])->render())
        ->toContain('Premium solo')->not->toContain('Premium team');

    expect(view('react-email.plan-email', ['plan' => 'free', 'seats' => []])->render())
        ->toContain('Free plan')->not->toContain('Premium');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('puts every directive on its own line so Blade compiles it', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';
import { BladeIf, BladeForEach } from '../../scripts/index';

export default function AdjacentEmail() {
  return (
    <Html>
      <BladeIf condition="showItems"><Text>Items:</Text></BladeIf>
      <BladeForEach items="items"><Text>$$item.name$$</Text></BladeForEach>
    </Html>
  );
}
TSX;

    buildTestTemplate('adjacent-email', $tsx);

    $data = ['showItems' => true, 'items' => [['name' => 'Mug']]];

    expect(view('react-email.adjacent-email', $data)->render())
        ->toContain('Items:')->toContain('Mug')->not->toContain('@foreach');

    expect(view('react-email.adjacent-email-text', $data)->render())
        ->toContain('Items:')->toContain('Mug')->not->toContain('@');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('converts BladeComponent props to bound and literal attributes', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html } from '@react-email/components';
import { BladeComponent, BladeSlot, blade } from '../../scripts/index';

export default function ComponentEmail() {
  return (
    <Html>
      <BladeComponent name="mail::button" url={blade.string('url')} color="primary" label={'Say "hi"'}>
        <BladeSlot name="title">Confirm</BladeSlot>
        Click here
      </BladeComponent>
    </Html>
  );
}
TSX;

    $html = readBuiltHtml(buildTestTemplate('component-email', $tsx));

    expect($html)
        ->toContain('<x-mail::button :url="$url" color="primary" label=\'Say "hi"\'>')
        ->toContain('<x-slot:title>Confirm</x-slot:title>')
        ->toContain('</x-mail::button>')
        ->not->toContain('blade-component');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');

it('ignores console output from templates when reading renderer results', function () {
    $tsx = <<<'TSX'
import React from 'react';
import { Html, Text } from '@react-email/components';

console.log('debug output from template');

export default function NoisyEmail() {
  console.log('rendering');
  return (
    <Html>
      <Text>Hello $$name$$</Text>
    </Html>
  );
}
TSX;

    expect(readBuiltHtml(buildTestTemplate('noisy-email', $tsx)))->toContain('{{ $name }}');
})->skip(nodeDependenciesMissing(), 'Requires Node.js and dependencies installed');
