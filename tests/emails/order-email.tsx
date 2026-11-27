/**
 * Order email fixture covering:
 *
 *  1. blade.number('price')    numeric proxy, .toFixed() works at build time
 *  2. blade.array<T>('items')  array proxy, .map() generates @foreach
 *  3. <BladeForEach>           explicit @foreach with $$item.prop$$ literals
 *  4. <BladeIf>                @if Blade directive
 *  5. '$$var$$' literals       plain string placeholders
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

                    {/* Plain string placeholders */}
                    <Text>Cześć, $$customerName$$!</Text>
                    <Text>Dziękujemy za zamówienie nr $$orderNumber$$.</Text>

                    <Hr />

                    {/*
                     * BladeForEach: children use $$item.prop$$ literals,
                     * compiled to {{ $item['prop'] }}.
                     */}
                    <Text style={{ fontWeight: 'bold' }}>Twoje produkty:</Text>
                    <BladeForEach items="items">
                        <Section style={{ borderBottom: '1px solid #eee', padding: '8px 0' }}>
                            <Text>$$item.name$$ x $$item.quantity$$: $$item.price$$ zł</Text>
                        </Section>
                    </BladeForEach>

                    <Hr />

                    {/*
                     * Smart-proxy .map(): items is a blade.array proxy, .map() wraps output in
                     * <blade-foreach-auto> for automatic @foreach generation.
                     */}
                    <Text style={{ fontWeight: 'bold' }}>Podsumowanie produktów:</Text>
                    {items.map((item: OrderItem) => (
                        <Text key={String(item.name)}>
                            {item.name}, cena: {(item.price as any).toFixed(2)} zł
                        </Text>
                    ))}

                    <Hr />

                    {/* Numeric proxies: .toFixed() works at build time */}
                    <Text>Suma częściowa: {subtotal.toFixed(2)} zł</Text>
                    <Text>Dostawa: {shippingCost.toFixed(2)} zł</Text>

                    {/*
                     * BladeIf: the section is always rendered at build time
                     * and wrapped in @if, so Blade decides at send time.
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
