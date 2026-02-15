/**
 * @mscode-pl/laravel-react-email
 *
 * Public API for React email template authors.
 *
 * Import in your .tsx templates:
 *   import { BladeForEach, BladeIf, blade } from '@mscode-pl/laravel-react-email';
 */

export { BladeForEach, BladeIf } from './blade-components';
export type { BladeForEachProps, BladeIfProps } from './blade-components';

export { blade, createSmartProxy, createItemProxy } from './helpers';
