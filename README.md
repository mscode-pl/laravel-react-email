# Laravel React Email

Build modern email templates using React components with Laravel integration.

## Setup

### Requirements
- Laravel 11+
- Node.js 16+
- React Email library

### Install Package

```bash
composer require mscode-pl/laravel-react-email
pnpm install vendor/mscode-pl/laravel-react-email
```

### Add script to package.json
```json
"scripts": {
  "email:dev": "email dev --dir resources/emails"
}
```

### Configuration

Configure your email templates directory in `.env`:

```env
LARAVEL_REACT_EMAIL_DIRECTORY="resources/emails"
```

Set up React Email following the [official guide](https://react.email/docs/getting-started/automatic-setup).

## Usage

### Creating Email Templates

Build your email component as a React element:

```tsx
import { Html, Body, Text, Container } from '@react-email/components';

export default function WelcomeEmail({ userName, activationLink }) {
  return (
    <Html>
      <Body>
        <Container>
          <Text>Welcome, {userName}!</Text>
          <Text>
            <a href={activationLink}>Activate your account</a>
          </Text>
        </Container>
      </Body>
    </Html>
  );
}
```

### Creating Mailable Classes

Generate and extend the mailable:

```bash
php artisan make:mail WelcomeUser
```

```php
namespace App\Mail;

use MsCodePL\LaravelReactEmail\ReactMailable;
use Illuminate\Mail\Mailables\{Envelope, Content};

class WelcomeUser extends ReactMailable
{
    public function __construct(
        private string $userName,
        private string $activationLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Our Platform');
    }

    public function content(): Content
    {
        return new Content(
            view: 'welcome-email',
            with: [
                'userName' => $this->userName,
                'activationLink' => $this->activationLink,
            ],
        );
    }
}
```

### Sending Emails

```php
Mail::send(new WelcomeUser('John', 'https://example.com/activate'));
```

## License

MIT License - see [LICENSE](/LICENSE) file

