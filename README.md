# Laravel React Email

Build Laravel email templates using React components and [react-email](https://react.email).

[![Latest Version](https://img.shields.io/packagist/v/mscode-pl/laravel-react-email)](https://packagist.org/packages/mscode-pl/laravel-react-email)
[![License](https://img.shields.io/packagist/l/mscode-pl/laravel-react-email)](LICENSE)

## 📚 Documentation

- **[Quick Start Guide](QUICKSTART.md)** - Get started in 5 minutes
- **[Examples](EXAMPLES.md)** - Practical email templates
- **[Upgrade Guide](UPGRADE.md)** - Migrate from v1.x to v2.0
- **[Changelog](CHANGELOG.md)** - Version history

## Installation

Install the package via composer:

```bash
composer require mscode-pl/laravel-react-email
```

## Setup

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --provider="MsCodePL\LaravelReactEmail\LaravelReactEmailServiceProvider" --tag="react-email-config"
```

Install the required npm dependencies in your Laravel project:

```bash
npm install react react-email @react-email/components
# or with pnpm
pnpm add react react-email @react-email/components
```

## Configuration

The configuration file is located at `config/react-email.php`. You can customize the following options:

```php
return [
    // Path to React email templates (source .tsx files)
    'path' => resource_path('react-email'),
    
    // Path to built HTML output (Blade templates)
    'build_path' => resource_path('views/react-email'),
];
```

You can also set these paths via environment variables:

```env
REACT_EMAIL_PATH=resources/react-email
REACT_EMAIL_BUILD_PATH=resources/views/react-email
```

## Usage

### Creating a new email

Use the artisan command to generate both a Mailable class and a React email template:

```bash
php artisan make:react-email WelcomeEmail
```

This creates:
- `app/Mail/WelcomeEmail.php` - The Laravel Mailable class
- `resources/react-email/welcome-email.tsx` - The React email template

### Building your React email template

Edit the generated React template at `resources/react-email/welcome-email.tsx`:

```tsx
import * as React from 'react';
import { Html, Head, Body, Container, Text, Button } from "@react-email/components";

interface WelcomeEmailProps {
  userName?: string;
  activationUrl?: string;
}

export default function WelcomeEmail({ userName = 'User', activationUrl = '#' }: WelcomeEmailProps) {
  return (
    <Html>
      <Head />
      <Body style={{ fontFamily: 'Arial, sans-serif' }}>
        <Container>
          <Text>Welcome, $$userName$$!</Text>
          <Text>Click the button below to activate your account:</Text>
          <Button href="$$activationUrl$$">Activate Account</Button>
        </Container>
      </Body>
    </Html>
  );
}
```

**Note:** Use `$$variableName$$` syntax for variables that will be replaced with Blade variables.

### Compiling templates

Before sending emails, compile your React templates to Blade views:

```bash
php artisan react-email:build
```

This command compiles all `.tsx` files in your `react-email` directory to Blade templates.

### Using in your Mailable

The generated Mailable class already references the compiled views:

```php
namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use MsCodePL\LaravelReactEmail\ReactMailable;

class WelcomeEmail extends ReactMailable
{
    public function __construct(
        public string $userName,
        public string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Our Platform',
        );
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

### Sending emails

Send emails as usual with Laravel:

```php
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

Mail::to($user)->send(
    new WelcomeEmail(
        userName: $user->name,
        activationUrl: route('activate', ['token' => $user->activation_token])
    )
);
```

## Development

### Preview server

Start the React Email dev server to preview your templates in the browser:

```bash
php artisan react-email:dev
```

This starts a development server (usually at `http://localhost:3000`) where you can preview and test your email templates with hot reload.

### Automatic compilation

For development, you may want to automatically rebuild templates when they change. You can use a file watcher or add a build step to your workflow:

```bash
# Rebuild templates
php artisan react-email:build
```

## Commands

- `php artisan make:react-email <Name>` - Create a new Mailable and React template
- `php artisan react-email:build` - Compile all React templates to Blade views
- `php artisan react-email:dev` - Start the React Email preview server

## How it works

1. **Development**: Create React email templates using `@react-email/components`
2. **Compilation**: Run `react-email:build` to compile React templates to static HTML Blade views
3. **Usage**: Laravel Mailables reference the compiled Blade views
4. **Variables**: Use `$$variableName$$` in React which gets converted to `{{ $variableName }}` in Blade

## Requirements

- PHP 8.1 or higher
- Laravel 10+
- Node.js 16 or higher
- React 18+

## License

MIT License. See [LICENSE](LICENSE) for more information.

