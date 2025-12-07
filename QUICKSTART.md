# Quick Start Guide

Get up and running with Laravel React Email in 5 minutes!

## Installation

```bash
# 1. Install the package
composer require mscode-pl/laravel-react-email

# 2. Install Node.js dependencies
npm install react react-email @react-email/components
# or with pnpm
pnpm add react react-email @react-email/components
```

## Create Your First Email

```bash
# Generate Mailable + React template
php artisan make:react-email WelcomeEmail
```

This creates:
- ✅ `app/Mail/WelcomeEmail.php`
- ✅ `resources/react-email/welcome-email.tsx`

## Edit the Template

Open `resources/react-email/welcome-email.tsx` and customize:

```tsx
import * as React from 'react';
import { Html, Head, Body, Container, Text, Button } from "@react-email/components";

export default function WelcomeEmail({ userName = 'User' }) {
  return (
    <Html>
      <Head />
      <Body>
        <Container>
          <Text>Hello, $$userName$$!</Text>
          <Button href="https://example.com">Get Started</Button>
        </Container>
      </Body>
    </Html>
  );
}
```

**Note:** Use `$$variableName$$` for Laravel variables!

## Preview Your Email

```bash
php artisan react-email:dev
```

Open http://localhost:3000 in your browser to see live preview.

## Build Templates

```bash
php artisan react-email:build
```

This compiles your React templates to Blade views.

## Send the Email

```php
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

Mail::to('user@example.com')->send(
    new WelcomeEmail(userName: 'John Doe')
);
```

## That's It! 🎉

Your email will be sent using the compiled Blade template.

---

## Development Workflow

```bash
# 1. Create email
php artisan make:react-email MyEmail

# 2. Edit with live preview
php artisan react-email:dev

# 3. Build for production
php artisan react-email:build

# 4. Send!
Mail::to($user)->send(new MyEmail(...));
```

## Production Deployment

Add to your deployment script:

```bash
php artisan react-email:build
```

Example `deploy.sh`:

```bash
#!/bin/bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan react-email:build  # ← Add this!
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Common Issues

### Templates not compiling?

Make sure Node.js and dependencies are installed:

```bash
node --version  # Should be 16+
npm list react react-email @react-email/components
```

### Variables not showing?

Use the `$$variableName$$` syntax in your React template:

```tsx
// ✅ Correct
<Text>Hello $$userName$$</Text>

// ❌ Wrong
<Text>Hello {userName}</Text>
```

### Email not sending?

Make sure you ran the build command:

```bash
php artisan react-email:build
```

## Next Steps

- 📖 Read the [full README](README.md) for detailed documentation
- 💡 Check out [EXAMPLES.md](EXAMPLES.md) for more templates
- 🔄 Migrating from v1.x? See [UPGRADE.md](UPGRADE.md)

## Need Help?

- Report issues: https://github.com/mscode-pl/laravel-react-email/issues
- Email: mateusz@mscode.pl

