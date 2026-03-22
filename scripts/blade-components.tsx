import React from 'react';

/**
 * Props for the BladeForEach component.
 */
export interface BladeForEachProps {
    /**
     * The Blade variable name pointing to the array to iterate.
     * Examples: "items", "order.items"
     */
    items: string;
    children: React.ReactNode;
}

/**
 * Renders children inside a Blade @foreach directive.
 *
 * Use $$item.property$$ placeholder strings in children — they become
 * {{ $item['property'] }} in the compiled Blade output.
 *
 * @example
 * ```tsx
 * <BladeForEach items="order.items">
 *   <Text>$$item.name$$ × $$item.quantity$$ — $$item.price$$ zł</Text>
 * </BladeForEach>
 * ```
 *
 * Compiles to:
 * ```blade
 * @foreach($order['items'] as $item)
 *   <p>{{ $item['name'] }} × {{ $item['quantity'] }} — {{ $item['price'] }} zł</p>
 * @endforeach
 * ```
 */
export function BladeForEach({ items, children }: BladeForEachProps): React.ReactElement {
    return React.createElement('blade-foreach', { 'data-items': items }, children);
}

/**
 * Props for the BladeForElse component.
 */
export interface BladeForElseProps {
    /**
     * The Blade variable name pointing to the array to iterate.
     * Examples: "items", "order.items"
     */
    items: string;
    children: React.ReactNode;
}

/**
 * Renders children inside a Blade `@forelse` directive.
 *
 * Wrap the empty-state content in a `<BladeEmpty>` child component.
 * Use `$$item.property$$` placeholder strings for loop variables.
 *
 * @example
 * ```tsx
 * <BladeForElse items="notifications">
 *   <Text>$$item.message$$</Text>
 *   <BladeEmpty>
 *     <Text>You have no notifications.</Text>
 *   </BladeEmpty>
 * </BladeForElse>
 * ```
 *
 * Compiles to:
 * ```blade
 * @forelse($notifications as $item)
 *   <p>{{ $item['message'] }}</p>
 * @empty
 *   <p>You have no notifications.</p>
 * @endforelse
 * ```
 */
export function BladeForElse({ items, children }: BladeForElseProps): React.ReactElement {
    return React.createElement('blade-forelse', { 'data-items': items }, children);
}

/**
 * Renders the empty-state content inside a `<BladeForElse>` component.
 *
 * Must be the last child of `<BladeForElse>`.
 */
export function BladeEmpty({ children }: { children: React.ReactNode }): React.ReactElement {
    return React.createElement('blade-empty', null, children);
}

/**
 * Props for the BladeIf component.
 */
export interface BladeIfProps {
    /**
     * The condition to evaluate in Blade.
     * Variable names are automatically converted to Blade syntax.
     * Examples: "serviceFee > 0", "order.total >= 100"
     */
    condition: string;
    children: React.ReactNode;
}

/**
 * Renders children inside a Blade @if directive.
 *
 * Variable names in the condition are automatically prefixed with `$`
 * and dot-notation is converted to array access.
 *
 * @example
 * ```tsx
 * <BladeIf condition="serviceFee > 0">
 *   <Text>Service fee: $$serviceFee$$ zł</Text>
 * </BladeIf>
 * ```
 *
 * Compiles to:
 * ```blade
 * @if($serviceFee > 0)
 *   <p>Service fee: {{ $serviceFee }} zł</p>
 * @endif
 * ```
 */
export function BladeIf({ condition, children }: BladeIfProps): React.ReactElement {
    return React.createElement('blade-if', { 'data-condition': condition }, children);
}

/**
 * Props for the BladeElseIf component.
 */
export interface BladeElseIfProps {
    /**
     * The condition to evaluate in Blade.
     * Variable names are automatically converted to Blade syntax.
     */
    condition: string;
    children: React.ReactNode;
}

/**
 * Renders an `@elseif` branch inside a `<BladeIf>` component.
 *
 * Must be placed as a direct child of `<BladeIf>`, after the main content.
 *
 * @example
 * ```tsx
 * <BladeIf condition="status === 'premium'">
 *   <Text>Premium member</Text>
 *   <BladeElseIf condition="status === 'pro'">
 *     <Text>Pro member</Text>
 *   </BladeElseIf>
 *   <BladeElse>
 *     <Text>Free member</Text>
 *   </BladeElse>
 * </BladeIf>
 * ```
 */
export function BladeElseIf({ condition, children }: BladeElseIfProps): React.ReactElement {
    return React.createElement('blade-elseif', { 'data-condition': condition }, children);
}

/**
 * Renders an `@else` branch inside a `<BladeIf>` component.
 *
 * Must be the last child of `<BladeIf>`.
 *
 * @example
 * ```tsx
 * <BladeIf condition="isPaid">
 *   <Text>Thank you for your payment!</Text>
 *   <BladeElse>
 *     <Text>Your payment is pending.</Text>
 *   </BladeElse>
 * </BladeIf>
 * ```
 */
export function BladeElse({ children }: { children: React.ReactNode }): React.ReactElement {
    return React.createElement('blade-else', null, children);
}

// ---------------------------------------------------------------------------
// Blade x-component support
// ---------------------------------------------------------------------------

/**
 * Props for the BladeComponent component.
 */
export interface BladeComponentProps {
    /**
     * The Blade component name, e.g. "mail::button", "alert", "nav.dropdown".
     */
    name: string;
    children?: React.ReactNode;
    [key: string]: any;
}

/**
 * Renders a Blade anonymous/class component (`<x-name>`) in the compiled output.
 *
 * Prop values that are `$$varName$$` placeholders compile to `:propName="$varName"`
 * (dynamic Blade binding). All other prop values compile to `propName="literal"`.
 *
 * @example
 * ```tsx
 * <BladeComponent name="mail::button" href={blade.string('url')} color="primary">
 *   Click here
 * </BladeComponent>
 * ```
 *
 * Compiles to:
 * ```blade
 * <x-mail::button :href="$url" color="primary">
 *   Click here
 * </x-mail::button>
 * ```
 */
export function BladeComponent({ name, children, ...props }: BladeComponentProps): React.ReactElement {
    return React.createElement(
        'blade-component',
        {
            'data-name': name,
            'data-props': JSON.stringify(props),
        },
        children,
    );
}

/**
 * Props for the BladeSlot component.
 */
export interface BladeSlotProps {
    /**
     * The slot name, e.g. "title", "header", "footer".
     */
    name: string;
    children: React.ReactNode;
}

/**
 * Renders a named slot (`<x-slot:name>`) inside a `<BladeComponent>`.
 *
 * @example
 * ```tsx
 * <BladeComponent name="card">
 *   <BladeSlot name="title">Order Confirmation</BladeSlot>
 *   <Text>Your order has been placed.</Text>
 * </BladeComponent>
 * ```
 *
 * Compiles to:
 * ```blade
 * <x-card>
 *   <x-slot:title>Order Confirmation</x-slot:title>
 *   <p>Your order has been placed.</p>
 * </x-card>
 * ```
 */
export function BladeSlot({ name, children }: BladeSlotProps): React.ReactElement {
    return React.createElement('blade-slot', { 'data-name': name }, children);
}
