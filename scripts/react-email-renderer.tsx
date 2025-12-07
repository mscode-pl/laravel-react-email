import React from 'react';
import { render } from '@react-email/render';
import path from 'path';

const [, , template] = process.argv;

if (!template) {
    console.error('Usage: tsx react-email-renderer.tsx <template>');
    process.exit(1);
}

async function renderEmailTemplate(template: string) {
    try {
        const resolvedPath = path.resolve(template);

        const templateUrl = process.platform === 'win32'
            ? `file:///${resolvedPath.replace(/\\/g, '/')}`
            : resolvedPath;

        const importedModule = await import(templateUrl);
        const EmailComponent = importedModule.default || importedModule;

        if (typeof EmailComponent !== 'function') {
            throw new Error('Default export is not a React component');
        }

        // Render the component to HTML
        const html = await render(<EmailComponent/>, {
            pretty: true,
        });

        const convertToBlade = (variable: string): string => {
            if (variable.includes('.')) {
                const parts = variable.split('.');
                const base = parts[0];
                const rest = parts.slice(1).map(part => `['${part}']`).join('');
                return `{{ $${base}${rest} }}`;
            }
            return `{{ $${variable} }}`;
        };

        const variables: string[] = [];
        html.replace(/\$\$([\w.]+)\$\$/g, (match, variable) => {
            if (!variables.includes(variable)) {
                variables.push(variable);
            }
            return match;
        });

        // Render plain text version
        let plainText = await render(<EmailComponent/>, {
            plainText: true,
        });

        variables.forEach(variable => {
            if (!variable.includes('.')) {
                const uppercaseVar = variable.toUpperCase();
                const regex = new RegExp(`\\$${uppercaseVar}\\$`, 'g');
                plainText = plainText.replace(regex, `$$${variable}$$`);
            }

            else {
                const uppercaseVar = variable.toUpperCase().replace(/\./g, '');
                const regex = new RegExp(`\\$${uppercaseVar}\\$`, 'g');
                plainText = plainText.replace(regex, `$$${variable}$$`);
            }
        });

        // Replace $$vars$$ with Laravel Blade variables in both HTML and plain text
        const bladeHtml = html.replace(/\$\$([\w.]+)\$\$/g, (match, variable) => convertToBlade(variable));
        const bladePlainText = plainText.replace(/\$\$([\w.]+)\$\$/g, (match, variable) => convertToBlade(variable));

        // Output the rendered Blade-compatible HTML and text to stdout
        console.log(JSON.stringify({html: bladeHtml, plainText: bladePlainText}));
    } catch (error) {
        if (error instanceof Error) {
            console.error(error.message);
        }
        process.exit(1);
    }
}

renderEmailTemplate(template);
