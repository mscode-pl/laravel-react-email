import * as React from 'react';
import { Text, Html, Head, Body, Container } from '@react-email/components';

interface HelloUserProps {
    firstName?: string;
}

export default function HelloUser({ firstName = 'User' }: HelloUserProps) {
    return (
        <Html lang="en">
            <Head />
            <Body style={{ fontFamily: 'Arial, sans-serif' }}>
                <Container>
                    <Text>Hi, $$firstName$$!</Text>
                    <Text>Welcome to our platform.</Text>
                </Container>
            </Body>
        </Html>
    );
}
