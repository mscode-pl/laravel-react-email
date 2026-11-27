# CHANGELOG

All notable changes to `laravel-react-email` will be documented in this file.

## [Unreleased]

### Added

- Support for Laravel 13
- `as` prop on `BladeForEach` and `BladeForElse` to name the loop variable in nested loops
- `react-email:build` accepts template names to build only those templates
- `react-email:dev --port` option
- `.jsx`, `.ts` and `.js` templates are built, not only `.tsx`
- Changes to shared files in template subdirectories (layouts, components) trigger a rebuild

### Changed

- Updated `@react-email/render` to 2.x, `@react-email/components` to 1.0.12, `react-email` to 6.x and React to 19.3
- `react-dom` is now a declared dependency, so it always matches the installed `react` version
- Node.js 20.19+ is required (needed by `@react-email/render` 2.x)
- `ReactMailable` delegates the stale-view check to `ReactEmailBuilder` and updates the build manifest

### Fixed

- `react-email:build` exits with a non-zero code when a template fails to build
- `make:react-email` returned exit code 1 on success
- `make:react-email Orders/ShippedEmail` created `orders/-shipped-email.tsx` with an invalid function name; the template is now `shipped-email.tsx`, matching the view the mailable resolves
- `BladeIf` conditions with string literals (`plan === 'premium'`) produced invalid PHP
- Nested `BladeForEach`, `BladeIf` and `BladeForElse` elements were left unconverted in the output
- `BladeComponent` with props was left unconverted in the output
- Blade directives placed right after text (`@endif@foreach`) were not compiled by Blade
- `console.log` in a template broke the build output
- `react-email:dev` crashed on Windows, where TTY mode is not available
- CI ran on the wrong branches and never executed the render tests

## [2.0.2] - 2025-12-07

### Added

- Support for nested variables using dot notation (e.g., `$$user.email$$` → `{{ $user['email'] }}`)
- Windows path support in the React Email renderer
- Comprehensive examples of nested variables in documentation

### Changed

- Improved variable detection to handle both simple and nested variables
- Refactored variable conversion logic to eliminate code duplication
- Updated all template stubs to use default React imports instead of namespace imports

### Fixed

- Fixed ESM import issues on Windows by converting paths to `file://` URLs
- Fixed TypeScript errors by using default imports with `esModuleInterop`

## [2.0.1] - 2025-12-07

### Added

- Support for Laravel 12.x
- Support for PHP 8.4, 8.5

## [2.0.0] - 2025-12-07

### Breaking Changes

- **Prebuild workflow**: Templates must now be compiled with `php artisan react-email:build` before sending emails
- **Configuration changes**: Renamed config keys from `email_templates_path`/`blade_output_path` to `path`/`build_path`
- **Removed hot reload**: The `enable_hot_reload` config option has been removed
- **View references**: Changed from `react-email::template` to `react-email.template` notation
- **Content method**: Now requires both `html` and `text` parameters instead of just `view`

### Added

- `php artisan react-email:dev` command to start the React Email preview server
- `php artisan make:react-email` command to generate both Mailable and React template
- Better TypeScript support in template stubs
- Improved example templates with proper styling
- `UPGRADE.md` guide for migrating from v1.x
- `EXAMPLES.md` with practical usage examples
- Support for `$$variable$$` syntax in React templates that compiles to Blade `{{ $variable }}`

### Changed

- `ReactMailable` is now a simple abstract class without build logic
- Templates are stored in `resources/react-email` (instead of `resources/views/react-email`)
- Compiled templates go to `resources/views/react-email` (instead of `resources/views/vendor/react-email`)
- Improved error handling in build command
- Better progress reporting during template compilation

### Fixed

- Constructor errors in ReactMailable
- Service provider configuration issues
- Incorrect view namespacing

### Performance

- Much faster email sending (no Node.js process during send)
- Build-time validation catches errors before production
- Pre-compiled templates reduce server load

## [1.0.0] - Initial Release

### Added

- Basic React Email integration with Laravel
- Hot reload support for development
- Automatic template rendering on email send
- Support for React Email components

