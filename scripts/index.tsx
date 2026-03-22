/**
 * @mscode-pl/laravel-react-email
 *
 * Public API for React email template authors.
 *
 * Import in your .tsx templates:
 *   import { BladeForEach, BladeIf, blade } from '@mscode-pl/laravel-react-email';
 */

export {
    BladeForEach,
    BladeForElse,
    BladeEmpty,
    BladeIf,
    BladeElseIf,
    BladeElse,
    BladeComponent,
    BladeSlot,
} from './blade-components';
export type {
    BladeForEachProps,
    BladeForElseProps,
    BladeIfProps,
    BladeElseIfProps,
    BladeComponentProps,
    BladeSlotProps,
} from './blade-components';

export { blade, createSmartProxy, createItemProxy } from './helpers';
