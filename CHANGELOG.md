# CHANGELOG

All notable changes to `laravel-react-email` will be documented in this file.

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

