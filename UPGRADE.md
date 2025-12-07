# Upgrade Guide

## From v1.x to v2.0

### Overview

Version 2.0 introduces a prebuild workflow instead of hot reloading. This means email templates are now compiled ahead of time using `php artisan react-email:build` instead of being rendered on-demand when emails are sent.

### Breaking Changes

#### 1. Configuration Changes

**Old configuration:**
```php
return [
    'email_templates_path' => resource_path('views/react-email'),
    'blade_output_path' => resource_path('views/vendor/react-email'),
    'enable_hot_reload' => env('REACT_EMAIL_HOT_RELOAD', env('APP_DEBUG', false)),
];
```

**New configuration:**
```php
return [
    'path' => resource_path('react-email'),
    'build_path' => resource_path('views/react-email'),
];
```

**Migration steps:**
1. Update your `config/react-email.php` file
2. Move your React templates from `resources/views/react-email` to `resources/react-email`
3. Update environment variables if used:
   - `REACT_EMAIL_HOT_RELOAD` is no longer needed (remove it)
   - Use `REACT_EMAIL_PATH` instead of `LARAVEL_REACT_EMAIL_DIRECTORY`
   - Use `REACT_EMAIL_BUILD_PATH` for output path

#### 2. View References

**Old approach:**
```php
public function content(): Content
{
    return new Content(
        view: 'react-email::welcome-email',
    );
}
```

**New approach:**
```php
public function content(): Content
{
    return new Content(
        html: 'react-email.welcome-email',
        text: 'react-email.welcome-email-text',
    );
}
```

#### 3. Build Process Required

In v2.0, you **must** compile your templates before sending emails:

```bash
php artisan react-email:build
```

This should be part of your deployment process. Add it to your deployment scripts:

```bash
# After composer install and npm install
php artisan react-email:build
```

#### 4. ReactMailable Changes

The `ReactMailable` base class no longer handles hot reloading. It's now a simple abstract class that extends Laravel's `Mailable`.

**No changes required** to your existing Mailable classes, but they will no longer compile on-demand.

### New Features

#### 1. Development Server Command

Preview your emails in the browser:

```bash
php artisan react-email:dev
```

This starts the React Email dev server at `http://localhost:3000`

#### 2. Make Command

Generate both Mailable and React template at once:

```bash
php artisan make:react-email WelcomeEmail
```

Creates:
- `app/Mail/WelcomeEmail.php`
- `resources/react-email/welcome-email.tsx`

### Migration Checklist

- [ ] Update `config/react-email.php` with new configuration structure
- [ ] Move React templates from `resources/views/react-email` to `resources/react-email`
- [ ] Update Mailable classes to use `html` and `text` parameters instead of `view`
- [ ] Update view references from `react-email::template` to `react-email.template`
- [ ] Run `php artisan react-email:build` to compile templates
- [ ] Add `php artisan react-email:build` to your deployment process
- [ ] Remove `REACT_EMAIL_HOT_RELOAD` from `.env` files
- [ ] Test sending emails to ensure they work correctly

### Benefits of v2.0

1. **Better Performance**: Templates are pre-compiled, no rendering on send
2. **Faster Email Sending**: No Node.js process spawning during email send
3. **More Reliable**: Build-time errors instead of runtime errors
4. **Better DevX**: Live preview server for template development
5. **Laravel Standard**: Uses standard Blade views like other Laravel emails

