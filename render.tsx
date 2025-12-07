import { render } from '@react-email/render'
import * as React from 'react';
import * as process from "node:process";
import { resolve } from 'node:path';

const [, , templatePath, contextJson] = process.argv;

const resolvedPath = resolve(templatePath);
const templateUrl = pathToFileUrl(resolvedPath);

function pathToFileUrl(filepath: string): string {
    const normalized = filepath.replace(/\\/g, '/');

    if (process.platform === 'win32') {
        // Windows: C:/path/to/file -> file:///C:/path/to/file
        return 'file:///' + normalized;
    }

    // Linux/Mac: /path/to/file -> file:///path/to/file
    return 'file://' + normalized;
}

import(templateUrl).then(async (module) => {
    const Email = module.default
    const context = JSON.parse(contextJson);

    const html = await render(<Email {...context} />);
    const text = await render(<Email {...context} />, {
        plainText: true,
    });


    console.log(JSON.stringify({html, text}));
})
