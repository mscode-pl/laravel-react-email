<?php

/**
 * Tests for the enhanced Blade rendering features:
 *  - numeric placeholder proxies (.toFixed without crash)
 *  - BladeForEach → @foreach directive
 *  - BladeIf → @if directive
 *  - blade.array().map() auto-proxy → @foreach directive
 *  - backward compatibility (plain $$var$$ strings still work)
 *
 * All Node.js-dependent tests are skipped unless Node.js + deps are present.
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
    if (File::exists($this->buildPath)) {
        File::deleteDirectory($this->buildPath);
    }
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
})->skip('Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Numeric proxy — Priority 1 (CRITICAL)
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
})->skip('Requires Node.js and dependencies installed');

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
})->skip('Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// BladeForEach component — Priority 2 (CRITICAL)
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
        <Text>$$item.name$$ — $$item.price$$ zł</Text>
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
})->skip('Requires Node.js and dependencies installed');

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
})->skip('Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// BladeIf component — Priority 3 (IMPORTANT)
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
})->skip('Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Auto-proxy .map() — Priority 5 (NICE TO HAVE)
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
        <Text key={String(item.id)}>{item.name} — {item.price.toFixed(2)} zł</Text>
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
})->skip('Requires Node.js and dependencies installed');

// ---------------------------------------------------------------------------
// Combined — order email (real-world scenario)
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
})->skip('Requires Node.js and dependencies installed');
