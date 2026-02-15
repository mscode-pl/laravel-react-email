/**
 * Order email template — demonstrates all new features:
 *
 *  1. blade.number('price')    → numeric proxy, .toFixed() works at build time
 *  2. blade.array<T>('items')  → array proxy, .map() generates @foreach
 *  3. <BladeForEach>           → explicit @foreach with $$item.prop$$ literals
 *  4. <BladeIf>                → @if Blade directive
 *  5. '$$var$$' literal placeholders → still work (backward compat)
 */
import React from 'react';
import { Html, Head, Body, Container, Text, Hr, Section } from '@react-email/components';
import { BladeForEach, BladeIf, blade } from '../../scripts';

interface OrderItem {
    name: string;
    quantity: number;
    price: number;
}

interface OrderEmailProps {
    customerName?: string;
    orderNumber?: string;
    subtotal?: number;
    serviceFee?: number;
    shippingCost?: number;
    total?: number;
    items?: OrderItem[];
}

export default function OrderEmail({
    customerName = '$$customerName$$',
    orderNumber  = '$$orderNumber$$',
    subtotal     = blade.number('subtotal'),
    serviceFee   = blade.number('serviceFee'),
    shippingCost = blade.number('shippingCost'),
    total        = blade.number('total'),
    items        = blade.array<OrderItem>('items'),
}: OrderEmailProps) {
    return (
        <Html lang="pl">
            <Head />
            <Body style={{ fontFamily: 'Arial, sans-serif', backgroundColor: '#f9f9f9' }}>
                <Container style={{ maxWidth: '600px', backgroundColor: '#fff', padding: '24px' }}>

                    {/* Simple string placeholders — backward compat */}
                    <Text>Cześć, $$customerName$$!</Text>
                    <Text>Dziękujemy za zamówienie nr $$orderNumber$$.</Text>

                    <Hr />

                    {/*
                     * Approach A: BladeForEach component (explicit, CRITICAL priority 2).
                     * Children use $$item.prop$$ literals → {{ $item['prop'] }} in Blade.
                     */}
                    <Text style={{ fontWeight: 'bold' }}>Twoje produkty:</Text>
                    <BladeForEach items="items">
                        <Section style={{ borderBottom: '1px solid #eee', padding: '8px 0' }}>
                            <Text>$$item.name$$ × $$item.quantity$$ — $$item.price$$ zł</Text>
                        </Section>
                    </BladeForEach>

                    <Hr />

                    {/*
                     * Approach B: smart-proxy .map() (NICE TO HAVE priority 5).
                     * items is a blade.array proxy; .map() wraps output in
                     * <blade-foreach-auto> for automatic @foreach generation.
                     */}
                    <Text style={{ fontWeight: 'bold' }}>Podsumowanie produktów:</Text>
                    {items.map((item: OrderItem) => (
                        <Text key={String(item.name)}>
                            {item.name} — cena: {(item.price as any).toFixed(2)} zł
                        </Text>
                    ))}

                    <Hr />

                    {/* Numeric proxies — .toFixed() doesn't crash (CRITICAL priority 1) */}
                    <Text>Suma częściowa: {subtotal.toFixed(2)} zł</Text>
                    <Text>Dostawa: {shippingCost.toFixed(2)} zł</Text>

                    {/*
                     * BladeIf — conditional section (IMPORTANT priority 3).
                     * At build time .valueOf() = 0 so the section is NOT rendered
                     * (correct for build). BladeIf wraps it in @if for runtime.
                     */}
                    <BladeIf condition="serviceFee > 0">
                        <Text>Opłata serwisowa: {serviceFee.toFixed(2)} zł</Text>
                    </BladeIf>

                    <Text style={{ fontWeight: 'bold' }}>
                        Łącznie: {total.toFixed(2)} zł
                    </Text>

                </Container>
            </Body>
        </Html>
    );
}
