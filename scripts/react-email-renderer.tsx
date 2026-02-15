import React from 'react';
import { render } from '@react-email/render';
import path from 'path';
import fs from 'fs';
import { createSmartProxy } from './helpers';

const [, , template] = process.argv;

if (!template) {
    console.error('Usage: tsx react-email-renderer.tsx <template>');
    process.exit(1);
}

// ---------------------------------------------------------------------------
// Variable / Blade conversion utilities
// ---------------------------------------------------------------------------

/**
 * Converts a dot-notation variable name to a bare Blade expression (no {{ }}).
 *   "price"        → "$price"
 *   "order.total"  → "$order['total']"
 */
function varToBlade(varName: string): string {
    if (varName.includes('.')) {
        const parts = varName.split('.');
        const base  = parts[0];
        const rest  = parts.slice(1).map(part => `['${part}']`).join('');
        return `$${base}${rest}`;
    }
    return `$${varName}`;
}

/**
 * Converts a variable name to a Blade echo expression: {{ $var }} or {{ $obj['key'] }}.
 */
function convertToBlade(variable: string): string {
    return `{{ ${varToBlade(variable)} }}`;
}

/**
 * Converts a condition string (used in BladeIf) to Blade syntax.
 * Standalone identifiers and dot-paths are prefixed with `$`.
 *
 *   "serviceFee > 0"        → "$serviceFee > 0"
 *   "order.total >= 100"    → "$order['total'] >= 100"
 */
function convertConditionToBlade(condition: string): string {
    const keywords = new Set(['true', 'false', 'null', 'and', 'or', 'not', 'isset', 'empty', 'count']);
    // Unescape HTML entities that React may have encoded in attributes
    const raw = condition
        .replace(/&amp;/g,  '&')
        .replace(/&lt;/g,   '<')
        .replace(/&gt;/g,   '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g,  "'");

    return raw.replace(
        /\b([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\b/g,
        (match) => {
            if (keywords.has(match.toLowerCase())) return match;
            return varToBlade(match);
        },
    );
}

// ---------------------------------------------------------------------------
// Source analysis: detect $$placeholder$$ default-prop values
// ---------------------------------------------------------------------------

/**
 * Scans source code for default props whose values ARE $$placeholder$$ strings.
 *
 *   propName = '$$varName$$'   → Map { propName → 'varName' }
 *   propName = "$$var.sub$$"   → Map { propName → 'var.sub' }
 *
 * These props are replaced with smart proxies before rendering, so calls like
 * price.toFixed(2) or items.map(...) don't crash at build time.
 */
function extractPlaceholderProps(source: string): Map<string, string> {
    // Match: identifier = '$$var$$'  or  identifier = "$$var$$"
    // optionally followed by type assertions: as any, as unknown as number, etc.
    const pattern = /(\w+)\s*=\s*['"](\$\$[\w.]+\$\$)['"]/g;
    const props    = new Map<string, string>();
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(source)) !== null) {
        const propName = match[1];
        const varName  = match[2].slice(2, -2); // strip leading and trailing $$
        props.set(propName, varName);
    }
    return props;
}

// ---------------------------------------------------------------------------
// Post-processing: convert custom blade-* elements to Blade directives
// ---------------------------------------------------------------------------

/**
 * Post-processes rendered HTML/plain-text, converting:
 *
 *  1. <blade-foreach-auto data-items="X">…</blade-foreach-auto>
 *     → @foreach(bladeVar(X) as $item) … @endforeach
 *     (also rewrites $$X__ITEM__.prop$$ → {{ $item['prop'] }} inside)
 *
 *  2. <blade-foreach data-items="X">…</blade-foreach>
 *     → @foreach(bladeVar(X) as $item) … @endforeach
 *     (content already uses $$item.prop$$ which the global regex handles)
 *
 *  3. <blade-if data-condition="X">…</blade-if>
 *     → @if(bladeCondition(X)) … @endif
 *
 * Must run BEFORE the global $$var$$ → {{ $var }} substitution.
 */
function postProcessBlade(html: string): string {
    // ---- 1. blade-foreach-auto (from smart-proxy .map()) ----
    // These contain $$arrayName__ITEM__.prop$$ markers that need special handling.
    html = html.replace(
        /<blade-foreach-auto[\s\S]*?data-items="([^"]+)"[\s\S]*?>([\s\S]*?)<\/blade-foreach-auto\s*>/g,
        (_match, items: string, content: string) => {
            const foreachVar = varToBlade(items);

            // $$X__ITEM__.prop.sub$$ → {{ $item['prop']['sub'] }}
            let inner = content.replace(
                /\$\$[\w.]+__ITEM__\.([\w.]+)\$\$/g,
                (_m: string, propPath: string) => {
                    const parts = propPath.split('.');
                    const access = parts.map((p: string) => `['${p}']`).join('');
                    return `{{ $item${access} }}`;
                },
            );

            // $$X__ITEM__$$ (bare item reference) → {{ $item }}
            inner = inner.replace(/\$\$[\w.]+__ITEM__\$\$/g, '{{ $item }}');

            return `@foreach(${foreachVar} as $item)\n${inner}\n@endforeach`;
        },
    );

    // ---- 2. blade-foreach (from BladeForEach component) ----
    html = html.replace(
        /<blade-foreach[\s\S]*?data-items="([^"]+)"[\s\S]*?>([\s\S]*?)<\/blade-foreach\s*>/g,
        (_match, items: string, content: string) => {
            const foreachVar = varToBlade(items);
            return `@foreach(${foreachVar} as $item)\n${content}\n@endforeach`;
        },
    );

    // ---- 3. blade-if (from BladeIf component) ----
    html = html.replace(
        /<blade-if[\s\S]*?data-condition="([^"]+)"[\s\S]*?>([\s\S]*?)<\/blade-if\s*>/g,
        (_match, condition: string, content: string) => {
            const bladeCondition = convertConditionToBlade(condition);
            return `@if(${bladeCondition})\n${content}\n@endif`;
        },
    );

    return html;
}

// ---------------------------------------------------------------------------
// Main renderer
// ---------------------------------------------------------------------------

async function renderEmailTemplate(templatePath: string) {
    try {
        const resolvedPath = path.resolve(templatePath);

        const templateUrl = process.platform === 'win32'
            ? `file:///${resolvedPath.replace(/\\/g, '/')}`
            : resolvedPath;

        const importedModule = await import(templateUrl);
        const EmailComponent = importedModule.default || importedModule;

        if (typeof EmailComponent !== 'function') {
            throw new Error('Default export is not a React component');
        }

        // ---- Build smart props for $$placeholder$$ default prop values ----
        // e.g. price = '$$price$$' as any as number  →  smartProps.price = Proxy
        const source         = fs.readFileSync(resolvedPath, 'utf-8');
        const placeholderMap = extractPlaceholderProps(source);
        const smartProps: Record<string, unknown> = {};
        for (const [propName, varName] of placeholderMap) {
            smartProps[propName] = createSmartProxy(varName);
        }

        // ---- Render HTML ----
        const rawHtml = await render(
            React.createElement(EmailComponent as React.FC, smartProps),
            { pretty: true },
        );

        // ---- Render plain text ----
        const rawPlainText = await render(
            React.createElement(EmailComponent as React.FC, smartProps),
            { plainText: true },
        );

        // ---- Post-process Blade directives ----
        let processedHtml      = postProcessBlade(rawHtml);
        let processedPlainText = postProcessBlade(rawPlainText);

        // ---- Collect $$var$$ names still present (for plain-text uppercase fix) ----
        const variables: string[] = [];
        processedHtml.replace(/\$\$([\w.]+)\$\$/g, (_match, variable: string) => {
            if (!variables.includes(variable)) {
                variables.push(variable);
            }
            return _match;
        });

        // Plain-text fix: react-email's plain-text renderer uppercases variable names
        // (e.g. $$firstName$$ → $FIRSTNAME$), so we restore the correct casing.
        variables.forEach(variable => {
            if (!variable.includes('.')) {
                const uppercaseVar = variable.toUpperCase();
                const regex = new RegExp(`\\$${uppercaseVar}\\$`, 'g');
                processedPlainText = processedPlainText.replace(regex, `$$${variable}$$`);
            } else {
                const uppercaseVar = variable.toUpperCase().replace(/\./g, '');
                const regex = new RegExp(`\\$${uppercaseVar}\\$`, 'g');
                processedPlainText = processedPlainText.replace(regex, `$$${variable}$$`);
            }
        });

        // ---- Replace remaining $$var$$ → {{ $var }} in both outputs ----
        const bladeHtml      = processedHtml.replace(
            /\$\$([\w.]+)\$\$/g,
            (_match, variable: string) => convertToBlade(variable),
        );
        const bladePlainText = processedPlainText.replace(
            /\$\$([\w.]+)\$\$/g,
            (_match, variable: string) => convertToBlade(variable),
        );

        console.log(JSON.stringify({ html: bladeHtml, plainText: bladePlainText }));
    } catch (error) {
        if (error instanceof Error) {
            console.error(error.message);
        }
        process.exit(1);
    }
}

renderEmailTemplate(template);
