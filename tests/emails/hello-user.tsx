import React from 'react';
import { Text, Html, Head, Body, Container } from '@react-email/components';

interface HelloUserProps {
    firstName?: string;
    user?: {
        email?: string;
        name?: string;
    };
}

export default function HelloUser({ firstName = 'User', user = { email: 'user@example.com', name: 'John Doe' } }: HelloUserProps) {
    return (
        <Html lang="en">
            <Head />
            <Body style={{ fontFamily: 'Arial, sans-serif' }}>
                <Container>
                    <Text>Hi, $$firstName$$!</Text>
                    <Text>Welcome to our platform.</Text>
                    {/* Example of nested variables - will convert to {{ $user['email'] }} */}
                    <Text>Your email: $$user.email$$</Text>
                    <Text>Your name: $$user.name$$</Text>
                </Container>
            </Body>
        </Html>
    );
}
