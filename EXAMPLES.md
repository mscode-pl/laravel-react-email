# Examples

This document provides practical examples of using Laravel React Email.

## Basic Email Template

### 1. Create the Email

```bash
php artisan make:react-email WelcomeEmail
```

### 2. Edit the React Template

File: `resources/react-email/welcome-email.tsx`

```tsx
import * as React from 'react';
import { 
  Html, 
  Head, 
  Body, 
  Container, 
  Heading, 
  Text, 
  Button 
} from "@react-email/components";

interface WelcomeEmailProps {
  userName?: string;
  loginUrl?: string;
}

export default function WelcomeEmail({ 
  userName = 'User', 
  loginUrl = '#' 
}: WelcomeEmailProps) {
  return (
    <Html lang="en">
      <Head />
      <Body style={bodyStyle}>
        <Container style={containerStyle}>
          <Heading style={headingStyle}>
            Welcome, $$userName$$!
          </Heading>
          <Text style={textStyle}>
            Thank you for joining our platform. We're excited to have you on board!
          </Text>
          <Button href="$$loginUrl$$" style={buttonStyle}>
            Login to Your Account
          </Button>
        </Container>
      </Body>
    </Html>
  );
}

const bodyStyle = {
  backgroundColor: '#f6f9fc',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
};

const containerStyle = {
  margin: '0 auto',
  padding: '20px 0 48px',
  width: '580px',
};

const headingStyle = {
  fontSize: '32px',
  lineHeight: '1.3',
  fontWeight: '700',
  color: '#484848',
};

const textStyle = {
  fontSize: '16px',
  lineHeight: '26px',
  color: '#484848',
};

const buttonStyle = {
  backgroundColor: '#5469d4',
  borderRadius: '4px',
  color: '#fff',
  fontSize: '16px',
  fontWeight: 'bold',
  textDecoration: 'none',
  textAlign: 'center' as const,
  display: 'block',
  padding: '12px 20px',
};
```

### 3. Update the Mailable

File: `app/Mail/WelcomeEmail.php`

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use MsCodePL\LaravelReactEmail\ReactMailable;

class WelcomeEmail extends ReactMailable
{
    public function __construct(
        public string $userName,
        public string $loginUrl,
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

### 4. Build the Template

```bash
php artisan react-email:build
```

### 5. Send the Email

```php
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

Mail::to($user->email)->send(
    new WelcomeEmail(
        userName: $user->name,
        loginUrl: route('login')
    )
);
```

## Password Reset Email

### React Template

File: `resources/react-email/reset-password.tsx`

```tsx
import * as React from 'react';
import { 
  Html, 
  Head, 
  Body, 
  Container, 
  Heading, 
  Text, 
  Button,
  Section,
  Hr
} from "@react-email/components";

interface ResetPasswordProps {
  userName?: string;
  resetUrl?: string;
  expiresIn?: string;
}

export default function ResetPassword({ 
  userName = 'User', 
  resetUrl = '#',
  expiresIn = '60 minutes'
}: ResetPasswordProps) {
  return (
    <Html lang="en">
      <Head />
      <Body style={bodyStyle}>
        <Container style={containerStyle}>
          <Heading style={headingStyle}>Password Reset Request</Heading>
          
          <Text style={textStyle}>
            Hi $$userName$$,
          </Text>
          
          <Text style={textStyle}>
            We received a request to reset your password. Click the button below to create a new password:
          </Text>
          
          <Section style={buttonContainerStyle}>
            <Button href="$$resetUrl$$" style={buttonStyle}>
              Reset Password
            </Button>
          </Section>
          
          <Text style={textStyle}>
            This link will expire in $$expiresIn$$.
          </Text>
          
          <Hr style={hrStyle} />
          
          <Text style={footerStyle}>
            If you didn't request this password reset, you can safely ignore this email.
          </Text>
        </Container>
      </Body>
    </Html>
  );
}

const bodyStyle = {
  backgroundColor: '#ffffff',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
};

const containerStyle = {
  margin: '0 auto',
  padding: '20px 0 48px',
  width: '580px',
};

const headingStyle = {
  fontSize: '24px',
  lineHeight: '1.3',
  fontWeight: '700',
  color: '#1f2937',
  marginBottom: '20px',
};

const textStyle = {
  fontSize: '16px',
  lineHeight: '26px',
  color: '#374151',
  marginBottom: '10px',
};

const buttonContainerStyle = {
  textAlign: 'center' as const,
  margin: '30px 0',
};

const buttonStyle = {
  backgroundColor: '#dc2626',
  borderRadius: '6px',
  color: '#fff',
  fontSize: '16px',
  fontWeight: 'bold',
  textDecoration: 'none',
  textAlign: 'center' as const,
  display: 'inline-block',
  padding: '12px 32px',
};

const hrStyle = {
  borderColor: '#e5e7eb',
  margin: '26px 0',
};

const footerStyle = {
  color: '#6b7280',
  fontSize: '14px',
  lineHeight: '24px',
};
```

### Mailable

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use MsCodePL\LaravelReactEmail\ReactMailable;

class ResetPassword extends ReactMailable
{
    public function __construct(
        public string $userName,
        public string $resetUrl,
        public string $expiresIn = '60 minutes',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'react-email.reset-password',
            text: 'react-email.reset-password-text',
        );
    }
}
```

## Order Confirmation Email

### React Template

File: `resources/react-email/order-confirmation.tsx`

```tsx
import * as React from 'react';
import { 
  Html, 
  Head, 
  Body, 
  Container, 
  Heading, 
  Text, 
  Section,
  Row,
  Column,
  Hr
} from "@react-email/components";

interface OrderConfirmationProps {
  orderNumber?: string;
  customerName?: string;
  orderTotal?: string;
  orderDate?: string;
}

export default function OrderConfirmation({ 
  orderNumber = '12345',
  customerName = 'Customer',
  orderTotal = '$0.00',
  orderDate = 'Today'
}: OrderConfirmationProps) {
  return (
    <Html lang="en">
      <Head />
      <Body style={bodyStyle}>
        <Container style={containerStyle}>
          <Heading style={headingStyle}>
            Order Confirmation
          </Heading>
          
          <Text style={textStyle}>
            Hi $$customerName$$,
          </Text>
          
          <Text style={textStyle}>
            Thank you for your order! We've received your order and will send you a shipping notification once it's on its way.
          </Text>
          
          <Section style={orderDetailsStyle}>
            <Heading as="h2" style={subHeadingStyle}>
              Order Details
            </Heading>
            
            <Row>
              <Column>
                <Text style={labelStyle}>Order Number:</Text>
              </Column>
              <Column>
                <Text style={valueStyle}>$$orderNumber$$</Text>
              </Column>
            </Row>
            
            <Row>
              <Column>
                <Text style={labelStyle}>Order Date:</Text>
              </Column>
              <Column>
                <Text style={valueStyle}>$$orderDate$$</Text>
              </Column>
            </Row>
            
            <Hr style={hrStyle} />
            
            <Row>
              <Column>
                <Text style={totalLabelStyle}>Total:</Text>
              </Column>
              <Column>
                <Text style={totalValueStyle}>$$orderTotal$$</Text>
              </Column>
            </Row>
          </Section>
          
          <Text style={footerStyle}>
            Questions? Contact us at support@example.com
          </Text>
        </Container>
      </Body>
    </Html>
  );
}

const bodyStyle = {
  backgroundColor: '#f6f9fc',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
};

const containerStyle = {
  margin: '0 auto',
  padding: '20px 0 48px',
  width: '580px',
  backgroundColor: '#ffffff',
  borderRadius: '8px',
};

const headingStyle = {
  fontSize: '28px',
  lineHeight: '1.3',
  fontWeight: '700',
  color: '#1f2937',
  textAlign: 'center' as const,
  marginBottom: '30px',
};

const subHeadingStyle = {
  fontSize: '20px',
  fontWeight: '600',
  color: '#1f2937',
  marginBottom: '15px',
};

const textStyle = {
  fontSize: '16px',
  lineHeight: '26px',
  color: '#374151',
  marginBottom: '15px',
};

const orderDetailsStyle = {
  padding: '20px',
  backgroundColor: '#f9fafb',
  borderRadius: '6px',
  margin: '20px 0',
};

const labelStyle = {
  fontSize: '14px',
  color: '#6b7280',
  marginBottom: '5px',
};

const valueStyle = {
  fontSize: '14px',
  color: '#1f2937',
  fontWeight: '500',
  marginBottom: '5px',
};

const hrStyle = {
  borderColor: '#e5e7eb',
  margin: '15px 0',
};

const totalLabelStyle = {
  fontSize: '16px',
  color: '#1f2937',
  fontWeight: '700',
};

const totalValueStyle = {
  fontSize: '16px',
  color: '#059669',
  fontWeight: '700',
};

const footerStyle = {
  fontSize: '14px',
  color: '#6b7280',
  textAlign: 'center' as const,
  marginTop: '30px',
};
```

## Development Workflow

### 1. Start the Dev Server

```bash
php artisan react-email:dev
```

Open http://localhost:3000 in your browser to preview your templates.

### 2. Create and Edit Templates

Create new templates while the dev server is running to see live updates.

### 3. Build for Production

Before deploying or testing email sending:

```bash
php artisan react-email:build
```

### 4. Add to Deployment Script

Add to your `deploy.sh` or CI/CD pipeline:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan react-email:build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Tips

### Using Props for Default Values

Always provide default values in your TypeScript props:

```tsx
interface EmailProps {
  userName?: string;  // Optional
  email?: string;     // Optional
}

export default function Email({ 
  userName = 'User',   // Default value
  email = 'user@example.com' 
}: EmailProps) {
  // Your email template
}
```

### Variable Syntax

Use `$$variableName$$` in your JSX where you want Laravel variables:

```tsx
<Text>Hello $$userName$$!</Text>
```

This will be compiled to Blade:

```blade
<p>Hello {{ $userName }}!</p>
```

### Styling

Keep styles inline for better email client compatibility:

```tsx
<Text style={{ 
  fontSize: '16px', 
  color: '#333',
  marginBottom: '10px' 
}}>
  Your content
</Text>
```

### Testing

Test your emails locally:

```php
use Illuminate\Support\Facades\Mail;

Mail::to('test@example.com')->send(
    new WelcomeEmail(
        userName: 'Test User',
        loginUrl: 'https://example.com/login'
    )
);
```

