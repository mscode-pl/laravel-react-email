# Laravel React Email

Build Laravel email templates using [react-email](https://react.email) components.
Variables from your Mailable are injected at send-time via Laravel Blade — no static strings hardcoded into HTML.

[![Latest Version](https://img.shields.io/packagist/v/mscode-pl/laravel-react-email)](https://packagist.org/packages/mscode-pl/laravel-react-email)
[![License](https://img.shields.io/packagist/l/mscode-pl/laravel-react-email)](LICENSE)

---

## How it works

```
resources/react-email/welcome-email.tsx   ← you write this
         ↓  php artisan react-email:build
resources/views/react-email/welcome-email.blade.php   ← generated Blade template
         ↓  Mail::to($user)->send(new WelcomeEmail(...))
         email with real data injected by Blade at send-time
```

The build step compiles your TSX to static HTML and replaces placeholder props with Blade expressions:

| In template | In compiled Blade |
|---|---|
| `$$name$$` | `{{ $name }}` |
| `$$user.email$$` | `{{ $user['email'] }}` |
| `blade.number('price')` + `{price.toFixed(2)}` | `{{ $price }}` |
| `<BladeForEach items="items">` | `@foreach($items as $item)` |
| `<BladeIf condition="fee > 0">` | `@if($fee > 0)` |

---

## Installation

### 1. Composer

```bash
composer require mscode-pl/laravel-react-email
```

### 2. Publish config

```bash
php artisan vendor:publish --provider="MsCodePL\LaravelReactEmail\LaravelReactEmailServiceProvider" --tag="react-email-config"
```

### 3. npm dependencies

Install react-email and link the library from your vendor directory so TSX templates can import its helpers:

```bash
npm install react react-email @react-email/components tsx
```

Then add to your project's `package.json`:

```json
{
  "dependencies": {
    "@react-email/components": "^1.0.1",
    "@mscode-pl/laravel-react-email": "file:vendor/mscode-pl/laravel-react-email"
  }
}
```

Run `npm install` once more to create the symlink:

```bash
npm install
```

> **Why?** The package lives in `vendor/` (Composer only, not published to npm).
> The `file:` reference lets `tsx` resolve `import { blade } from '@mscode-pl/laravel-react-email'` correctly.

---

## Quick start

### Generate a Mailable + template

```bash
php artisan make:react-email WelcomeEmail
```

Creates:
- `app/Mail/WelcomeEmail.php`
- `resources/react-email/welcome-email.tsx`

### Write the template

`resources/react-email/welcome-email.tsx`:

```tsx
import React from 'react';
import { Html, Head, Body, Container, Text, Button } from '@react-email/components';
import { blade } from '@mscode-pl/laravel-react-email';

interface WelcomeEmailProps {
    userName?: string;
    activationUrl?: string;
}

export default function WelcomeEmail({
    userName    = '$$userName$$',
    activationUrl = '$$activationUrl$$',
}: WelcomeEmailProps) {
    return (
        <Html>
            <Head />
            <Body style={{ fontFamily: 'Arial, sans-serif' }}>
                <Container>
                    <Text>Welcome, $$userName$$!</Text>
                    <Text>Click below to activate your account:</Text>
                    <Button href={activationUrl}>Activate Account</Button>
                </Container>
            </Body>
        </Html>
    );
}
```

### Build

```bash
php artisan react-email:build
```

### Send

`app/Mail/WelcomeEmail.php`:

```php
use MsCodePL\LaravelReactEmail\ReactMailable;
use Illuminate\Mail\Mailables\{Content, Envelope};

class WelcomeEmail extends ReactMailable
{
    public function __construct(
        public string $userName,
        public string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome!');
    }

    public function content(): Content
    {
        return new Content(
            html: 'react-email.welcome-email',
            text: 'react-email.welcome-email-text',
        );
    }
}
```

```php
Mail::to($user)->send(
    new WelcomeEmail(
        userName: $user->name,
        activationUrl: route('activate', $user->activation_token),
    )
);
```

---

## Variables

### Simple string variables

Use `$$variableName$$` directly in JSX text. The build step converts them to `{{ $variableName }}`.

```tsx
export default function InvoiceEmail({
    invoiceNumber = '$$invoiceNumber$$',
    dueDate       = '$$dueDate$$',
}) {
    return (
        <Html>
            <Text>Invoice #$$invoiceNumber$$</Text>
            <Text>Due: $$dueDate$$</Text>
        </Html>
    );
}
```

Compiled Blade output:
```html
<p>Invoice #{{ $invoiceNumber }}</p>
<p>Due: {{ $dueDate }}</p>
```

### Nested object variables

Dot notation is converted to array access:

```tsx
export default function OrderEmail({
    user  = { name: '$$user.name$$', email: '$$user.email$$' },
    shop  = { name: '$$shop.name$$' },
}) {
    return (
        <Html>
            <Text>Hello $$user.name$$,</Text>
            <Text>Your order at $$shop.name$$ is confirmed.</Text>
            <Text>Confirmation sent to $$user.email$$</Text>
        </Html>
    );
}
```

Compiled Blade output:
```html
<p>Hello {{ $user['name'] }},</p>
<p>Your order at {{ $shop['name'] }} is confirmed.</p>
<p>Confirmation sent to {{ $user['email'] }}</p>
```

### Numeric variables

Use `blade.number('varName')` so methods like `.toFixed()` work at build time without crashing:

```tsx
import { blade } from '@mscode-pl/laravel-react-email';

export default function OrderEmail({
    subtotal = blade.number('subtotal'),
    total    = blade.number('total'),
}) {
    return (
        <Html>
            <Text>Subtotal: {subtotal.toFixed(2)} PLN</Text>
            <Text>Total: {total.toFixed(2)} PLN</Text>
        </Html>
    );
}
```

Compiled Blade output:
```html
<p>Subtotal: {{ $subtotal }} PLN</p>
<p>Total: {{ $total }} PLN</p>
```

> Blade handles the actual number formatting at send-time with `number_format($total, 2)` etc.

### Lists / arrays — `<BladeForEach>`

Use the `<BladeForEach>` component to loop over an array. Inside, write `$$item.property$$` placeholders:

```tsx
import { BladeForEach } from '@mscode-pl/laravel-react-email';

export default function OrderEmail() {
    return (
        <Html>
            <Text>Your items:</Text>
            <BladeForEach items="items">
                <Section>
                    <Text>$$item.name$$ × $$item.quantity$$ — $$item.price$$ PLN</Text>
                </Section>
            </BladeForEach>
        </Html>
    );
}
```

Compiled Blade output:
```blade
@foreach($items as $item)
    <div>
        <p>{{ $item['name'] }} × {{ $item['quantity'] }} — {{ $item['price'] }} PLN</p>
    </div>
@endforeach
```

For nested arrays (e.g. `order.items`):

```tsx
<BladeForEach items="order.items">
    <Text>$$item.name$$ — $$item.price$$ PLN</Text>
</BladeForEach>
```

Compiles to:
```blade
@foreach($order['items'] as $item)
    <p>{{ $item['name'] }} — {{ $item['price'] }} PLN</p>
@endforeach
```

### Conditional sections — `<BladeIf>`

Use `<BladeIf>` to wrap content that should only appear when a condition is true:

```tsx
import { BladeForEach, BladeIf, blade } from '@mscode-pl/laravel-react-email';

export default function OrderEmail({
    total      = blade.number('total'),
    serviceFee = blade.number('serviceFee'),
}) {
    return (
        <Html>
            <BladeForEach items="items">
                <Text>$$item.name$$ — $$item.price$$ PLN</Text>
            </BladeForEach>

            <BladeIf condition="serviceFee > 0">
                <Text>Service fee: {serviceFee.toFixed(2)} PLN</Text>
            </BladeIf>

            <Text>Total: {total.toFixed(2)} PLN</Text>
        </Html>
    );
}
```

Compiled Blade output:
```blade
@foreach($items as $item)
    <p>{{ $item['name'] }} — {{ $item['price'] }} PLN</p>
@endforeach

@if($serviceFee > 0)
    <p>Service fee: {{ $serviceFee }} PLN</p>
@endif

<p>Total: {{ $total }} PLN</p>
```

---

## blade() helper reference

| Helper | TypeScript type | Use when |
|---|---|---|
| `blade('name')` | `any` | generic placeholder |
| `blade.string('name')` | `string` | plain text, no method calls |
| `blade.number('name')` | `number` | `.toFixed()`, arithmetic |
| `blade.array<T>('name')` | `T[]` | `.map()`, `.filter()`, `.reduce()` |
| `blade.object<T>('name')` | `T` | nested property access |

---

## Commands

| Command | Description |
|---|---|
| `php artisan make:react-email <Name>` | Generate Mailable + TSX template |
| `php artisan react-email:build` | Compile all templates to Blade views |
| `php artisan react-email:dev` | Start live-preview server at `localhost:3000` |

---

## Configuration

`config/react-email.php`:

```php
return [
    // Source TSX templates
    'path' => env('REACT_EMAIL_PATH', resource_path('react-email')),

    // Compiled Blade output
    'build_path' => env('REACT_EMAIL_BUILD_PATH', resource_path('views/react-email')),
];
```

---

## Requirements

- PHP 8.1 – 8.5
- Laravel 10, 11 or 12
- Node.js 16+

---

## License

MIT — see [LICENSE](LICENSE).
