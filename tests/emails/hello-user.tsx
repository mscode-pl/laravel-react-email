import * as React from 'react';
import { Text, Html } from '@react-email/components';

export default function Email({ user }) {
    return (
        <Html>
            <Text>Hi, {user.firstName}</Text>
        </Html>
    );
}
