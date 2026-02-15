import React from 'react';

// React 19 uses Symbol.for('react.transitional.element') as the $typeof marker
// for valid React elements. Defining it once here avoids recreating the Symbol
// on every proxy property access.
const REACT_ELEMENT_TYPE = Symbol.for('react.transitional.element');

/**
 * Creates a smart Proxy for a Blade placeholder variable.
 *
 * The proxy is transparent to React rendering — it serialises to $$varName$$
 * in HTML output (which the post-processor converts to {{ $varName }}), while
 * safely handling numeric methods (.toFixed etc.) and array methods (.map etc.)
 * without crashing during build-time SSR.
 *
 * @internal Used by the renderer and by blade() helpers.
 */
export function createSmartProxy(varName: string): any {
    const placeholder = `$$${varName}$$`;

    const handler: ProxyHandler<Record<string, unknown>> = {
        get(_target, prop) {
            // --- React element interface ---
            // Makes the proxy renderable as a JSX child ({proxy} in JSX).
            // React 19 checks $typeof to decide whether a value is a valid element;
            // we return a Fragment element whose children is the placeholder string.
            if (prop === '$$typeof')     return REACT_ELEMENT_TYPE;
            if (prop === 'type')        return React.Fragment;
            if (prop === 'key')         return null;
            if (prop === 'ref')         return null;
            if (prop === '_owner')      return null;
            if (prop === '_store')      return {};
            if (prop === '_debugInfo')  return null;
            if (prop === '_debugStack') return null;
            if (prop === '_debugTask')  return null;
            if (prop === 'props')       return { children: placeholder };

            // --- Primitive coercion ---
            // hint='number'  → 0  (arithmetic like price * qty won't NaN)
            // hint='string' / 'default' → placeholder  (JSX text, string concat)
            if (prop === Symbol.toPrimitive) {
                return (hint: string) => (hint === 'number' ? 0 : placeholder);
            }

            const propStr = String(prop);

            // --- Number formatting methods ---
            // Return the placeholder string; Blade handles formatting at runtime.
            if (['toFixed', 'toPrecision', 'toExponential', 'toLocaleString'].includes(propStr)) {
                return () => placeholder;
            }

            // --- Standard coercions ---
            if (propStr === 'toString') return () => placeholder;
            if (propStr === 'valueOf')  return () => 0;

            // --- String: .split() → split the placeholder string normally ---
            // Needed for patterns like message.split('\n').map(...)
            if (propStr === 'split') {
                return (sep: string) => placeholder.split(sep);
            }

            // --- Array: .map() ---
            // Calls the callback once with an item-proxy, then wraps the result
            // in a <blade-foreach-auto> custom element for post-processing.
            if (propStr === 'map') {
                return (callback: (item: any, index: number, arr: any[]) => any) => {
                    const itemProxy = createItemProxy(varName);
                    const result = callback(itemProxy, 0, [itemProxy]);
                    return React.createElement(
                        'blade-foreach-auto',
                        { 'data-items': varName },
                        result,
                    );
                };
            }

            // --- Array: .filter() → same proxy (no-op for build) ---
            if (propStr === 'filter') return () => createSmartProxy(varName);

            // --- Array: .forEach() → run callback once ---
            if (propStr === 'forEach') {
                return (callback: (item: any) => void) => {
                    callback(createItemProxy(varName));
                };
            }

            // --- Array: .reduce() → numeric proxy for the result ---
            if (propStr === 'reduce') {
                return (_fn: unknown, _initial: unknown) =>
                    createSmartProxy(`${varName}_total`);
            }

            // --- Array: .length → 1 so loops execute once ---
            if (propStr === 'length') return 1;

            // --- Array: Symbol.iterator → yield one item proxy ---
            if (prop === Symbol.iterator) {
                return function* () {
                    yield createItemProxy(varName);
                };
            }

            // --- Nested property access → nested proxy ---
            // e.g. order.total, user.address.city
            const skipProps = new Set(['__esModule', '__proto__', 'constructor', 'then', 'catch', 'finally']);
            if (typeof prop === 'string' && !skipProps.has(propStr)) {
                return createSmartProxy(`${varName}.${propStr}`);
            }

            return undefined;
        },
    };

    return new Proxy({} as Record<string, unknown>, handler);
}

/**
 * Creates a proxy representing one element inside a foreach loop.
 *
 * Property access on the item returns nested SmartProxies using the
 * __ITEM__ marker that the post-processor recognises.
 *
 * @internal
 */
export function createItemProxy(arrayVarName: string): any {
    const itemPlaceholder = `$$${arrayVarName}__ITEM__$$`;

    const handler: ProxyHandler<Record<string, unknown>> = {
        get(_target, prop) {
            // --- React element interface (same as createSmartProxy) ---
            if (prop === '$$typeof')     return REACT_ELEMENT_TYPE;
            if (prop === 'type')        return React.Fragment;
            if (prop === 'key')         return null;
            if (prop === 'ref')         return null;
            if (prop === '_owner')      return null;
            if (prop === '_store')      return {};
            if (prop === '_debugInfo')  return null;
            if (prop === '_debugStack') return null;
            if (prop === '_debugTask')  return null;
            if (prop === 'props')       return { children: itemPlaceholder };

            if (prop === Symbol.toPrimitive) {
                return (hint: string) => (hint === 'number' ? 0 : itemPlaceholder);
            }

            const propStr = String(prop);
            if (propStr === 'toString') return () => itemPlaceholder;
            if (propStr === 'valueOf')  return () => 0;

            // Nested arrays: item.subItems.map(...)
            if (propStr === 'map') {
                return (callback: (item: any, index: number, arr: any[]) => any) => {
                    const subItemProxy = createItemProxy(`${arrayVarName}__ITEM__`);
                    const result = callback(subItemProxy, 0, [subItemProxy]);
                    return React.createElement(
                        'blade-foreach-auto',
                        { 'data-items': `${arrayVarName}__ITEM__` },
                        result,
                    );
                };
            }

            const skipProps = new Set(['__esModule', '__proto__', 'constructor', 'then', 'catch', 'finally']);
            if (typeof prop === 'string' && !skipProps.has(propStr)) {
                return createSmartProxy(`${arrayVarName}__ITEM__.${propStr}`);
            }

            return undefined;
        },
    };

    return new Proxy({} as Record<string, unknown>, handler);
}

// ---------------------------------------------------------------------------
// Public blade() helper
// ---------------------------------------------------------------------------

/**
 * Helper object for typing Blade placeholder props without `// @ts-ignore`.
 *
 * @example
 * ```tsx
 * import { blade } from '@mscode-pl/laravel-react-email';
 *
 * interface Props {
 *   price?: number;
 *   items?: OrderItem[];
 * }
 *
 * export default function OrderEmail({
 *   price = blade.number('price'),
 *   items = blade.array<OrderItem>('items'),
 * }: Props) { ... }
 * ```
 */
export const blade = Object.assign(
    /** Returns a smart proxy typed as `any` — use for generic placeholders. */
    (varName: string): any => createSmartProxy(varName),
    {
        /**
         * Returns a smart proxy typed as `number`.
         * Handles .toFixed(), .toPrecision() etc. without crashing at build time.
         */
        number: (varName: string): number =>
            createSmartProxy(varName) as unknown as number,

        /**
         * Returns a smart proxy typed as `T[]`.
         * Handles .map(), .filter(), .reduce() etc. without crashing at build time.
         */
        array: <T = any>(varName: string): T[] =>
            createSmartProxy(varName) as unknown as T[],

        /**
         * Returns a smart proxy typed as `T`.
         * Property access returns nested smart proxies.
         */
        object: <T = object>(varName: string): T =>
            createSmartProxy(varName) as unknown as T,

        /**
         * Returns a plain `$$varName$$` placeholder string typed as `string`.
         * Use when you only need string interpolation, not method calls.
         */
        string: (varName: string): string => `$$${varName}$$` as unknown as string,
    },
);
