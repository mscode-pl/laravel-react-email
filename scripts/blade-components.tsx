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
