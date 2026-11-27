import React from 'react';
import { render } from '@react-email/render';
import path from 'path';
import fs from 'fs';
import { pathToFileURL } from 'url';
import { createSmartProxy } from './helpers';

// ---------------------------------------------------------------------------
// Variable / Blade conversion utilities
// ---------------------------------------------------------------------------

/**
 * Converts a dot-notation variable name to a bare Blade expression (no {{ }}).
 *   "price"        => "$price"
 *   "order.total"  => "$order['total']"
 */
function varToBlade(varName: string): string {
    const [base, ...rest] = varName.split('.');
    return `$${base}${rest.map(part => `['${part}']`).join('')}`;
}

/**
 * Converts a variable name to a Blade echo expression: {{ $var }} or {{ $obj['key'] }}.
 */
function convertToBlade(variable: string): string {
    return `{{ ${varToBlade(variable)} }}`;
}

/**
 * Decodes the HTML entities React and the pretty-printer emit in text and
 * attribute values (named, decimal and hex forms).
 */
function decodeEntities(value: string): string {
    return value
        .replace(/&#x([0-9a-f]+);/gi, (_m, hex: string) => String.fromCodePoint(parseInt(hex, 16)))
        .replace(/&#(\d+);/g, (_m, dec: string) => String.fromCodePoint(parseInt(dec, 10)))
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&');
}

const CONDITION_KEYWORDS = new Set([
    'true', 'false', 'null', 'and', 'or', 'xor', 'not', 'instanceof', 'new',
    'isset', 'empty', 'count',
]);

/**
 * Converts a condition string (used in BladeIf) to Blade syntax.
 * Standalone identifiers and dot-paths are prefixed with `$`; string literals,
 * function calls, keywords and already-prefixed variables are left untouched.
 *
 *   "serviceFee > 0"            => "$serviceFee > 0"
 *   "order.total >= 100"        => "$order['total'] >= 100"
 *   "status === 'premium'"      => "$status === 'premium'"
 *   "count(items) > 0"          => "count($items) > 0"
 */
function convertConditionToBlade(condition: string): string {
    const token = /('(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*")|(\$|->|::)?\b([a-zA-Z_]\w*(?:\.[a-zA-Z_]\w*)*)\b(\s*\()?/g;

    return decodeEntities(condition).replace(
        token,
        (match, literal?: string, prefix?: string, identifier?: string, call?: string) => {
            if (literal || prefix || call || !identifier) return match;
            if (CONDITION_KEYWORDS.has(identifier.toLowerCase())) return match;
            return varToBlade(identifier);
        },
    );
}

/**
 * Returns a loop variable name for `@foreach(... as $name)`, falling back to
 * `item` when the given value is not a valid PHP identifier.
 */
function loopVariable(name: string | undefined): string {
    return name && /^[a-zA-Z_]\w*$/.test(name) ? name : 'item';
}

// ---------------------------------------------------------------------------
// Source analysis: detect $$placeholder$$ default-prop values
// ---------------------------------------------------------------------------

/**
 * Scans source code for default props whose values ARE $$placeholder$$ strings.
 *
 *   propName = '$$varName$$'   => Map { propName => 'varName' }
 *   propName = "$$var.sub$$"   => Map { propName => 'var.sub' }
 *
 * These props are replaced with smart proxies before rendering, so calls like
 * price.toFixed(2) or items.map(...) don't crash at build time.
 */
function extractPlaceholderProps(source: string): Map<string, string> {
    const pattern = /(\w+)\s*=\s*['"](\$\$[\w.]+\$\$)['"]/g;
    const props   = new Map<string, string>();
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(source)) !== null) {
        props.set(match[1], match[2].slice(2, -2));
    }
    return props;
}

// ---------------------------------------------------------------------------
// Post-processing: convert custom blade-* elements to Blade directives
// ---------------------------------------------------------------------------

type Attributes = Record<string, string>;

/**
 * Parses the attribute string of an opening tag. Handles both quote styles,
 * since the pretty-printer switches to single quotes when a value contains `"`.
 */
function parseAttributes(source: string): Attributes {
    const attrs: Attributes = {};
    const pattern = /([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/g;
    let match: RegExpExecArray | null;
    while ((match = pattern.exec(source)) !== null) {
        attrs[match[1]] = decodeEntities(match[2] ?? match[3] ?? '');
    }
    return attrs;
}

/**
 * Replaces every `<tag ...>...</tag>` element, innermost first, so nested elements
 * of the same type (a loop inside a loop, an if inside an if) are converted
 * correctly instead of being cut at the first closing tag.
 */
function transformElements(
    html: string,
    tag: string,
    replacer: (attrs: Attributes, content: string) => string,
): string {
    const pattern = new RegExp(
        `<${tag}(?=[\\s>/])([^>]*)>((?:(?!<${tag}[\\s>/])[\\s\\S])*?)<\\/${tag}\\s*>`,
        'g',
    );

    let previous: string;
    do {
        previous = html;
        html = html.replace(pattern, (_match, attrs: string, content: string) =>
            replacer(parseAttributes(attrs), content),
        );
    } while (html !== previous);

    return html;
}

/**
 * Blade only compiles a directive when it is not preceded by a word character,
 * so every directive is emitted on its own line.
 */
function directive(open: string, body: string, close: string): string {
    return `\n${open}\n${body.trim()}\n${close}\n`;
}

function bladeComponentAttribute(key: string, value: unknown): string {
    const str   = String(value);
    const bound = str.match(/^\$\$([\w.]+)\$\$$/);
    if (bound) return `:${key}="${varToBlade(bound[1])}"`;

    const quote = str.includes('"') ? "'" : '"';
    return `${key}=${quote}${str}${quote}`;
}

/**
 * Post-processes rendered HTML, converting the custom elements emitted by the
 * Blade* components and smart proxies into Blade syntax:
 *
 *  <blade-slot>          => <x-slot:name>
 *  <blade-component>     => <x-name :prop="$var" prop="literal">
 *  <blade-foreach-auto>  => @foreach ... @endforeach  (from proxy .map())
 *  <blade-forelse>       => @forelse ... @empty ... @endforelse
 *  <blade-foreach>       => @foreach ... @endforeach
 *  <blade-if>            => @if ... @elseif ... @else ... @endif
 *
 * Must run BEFORE the global $$var$$ to {{ $var }} substitution.
 */
function postProcessBlade(html: string): string {
    html = transformElements(html, 'blade-slot', (attrs, content) =>
        `<x-slot:${attrs['data-name']}>${content}</x-slot:${attrs['data-name']}>`,
    );

    html = transformElements(html, 'blade-component', (attrs, content) => {
        let props: Record<string, unknown> = {};
        try {
            props = JSON.parse(attrs['data-props'] || '{}');
        } catch {
            props = {};
        }

        const name     = attrs['data-name'];
        const attrList = Object.entries(props).map(([key, val]) => bladeComponentAttribute(key, val));
        const attrStr  = attrList.length ? ` ${attrList.join(' ')}` : '';

        return content.trim()
            ? `<x-${name}${attrStr}>${content}</x-${name}>`
            : `<x-${name}${attrStr} />`;
    });

    html = transformElements(html, 'blade-foreach-auto', (attrs, content) => {
        const inner = content
            .replace(/\$\$[\w.]+__ITEM__\.([\w.]+)\$\$/g, (_m, propPath: string) =>
                `{{ $item${propPath.split('.').map(p => `['${p}']`).join('')} }}`,
            )
            .replace(/\$\$[\w.]+__ITEM__\$\$/g, '{{ $item }}');

        return directive(`@foreach(${varToBlade(attrs['data-items'])} as $item)`, inner, '@endforeach');
    });

    html = transformElements(html, 'blade-forelse', (attrs, content) => {
        let emptyContent = '';
        const loopContent = transformElements(content, 'blade-empty', (_attrs, inner) => {
            emptyContent = inner;
            return '';
        });

        const as = loopVariable(attrs['data-as']);
        return `\n@forelse(${varToBlade(attrs['data-items'])} as $${as})\n${loopContent.trim()}\n@empty\n${emptyContent.trim()}\n@endforelse\n`;
    });

    html = transformElements(html, 'blade-foreach', (attrs, content) => {
        const as = loopVariable(attrs['data-as']);
        return directive(`@foreach(${varToBlade(attrs['data-items'])} as $${as})`, content, '@endforeach');
    });

    html = transformElements(html, 'blade-if', (attrs, content) => {
        let processed = transformElements(content, 'blade-elseif', (inner, body) =>
            `\n@elseif(${convertConditionToBlade(inner['data-condition'] ?? '')})\n${body.trim()}\n`,
        );
        processed = transformElements(processed, 'blade-else', (_inner, body) => `\n@else\n${body.trim()}\n`);

        return directive(`@if(${convertConditionToBlade(attrs['data-condition'] ?? '')})`, processed, '@endif');
    });

    return html;
}

/**
 * Replaces the remaining placeholders with Blade echoes:
 *   $$raw:var$$ => {!! $var !!}
 *   $$var$$     => {{ $var }}
 */
function replacePlaceholders(output: string): string {
    return output
        .replace(/\$\$raw:([\w.]+)\$\$/g, (_match, variable: string) => `{!! ${varToBlade(variable)} !!}`)
        .replace(/\$\$([\w.]+)\$\$/g, (_match, variable: string) => convertToBlade(variable));
}

// ---------------------------------------------------------------------------
// HTML to plain text conversion
// ---------------------------------------------------------------------------

/**
 * Derives a plain-text version from already-processed HTML (which may contain
 * Blade directives such as @foreach, @if, {{ $var }}).
 *
 * We intentionally do NOT use @react-email/render with plainText:true here,
 * because that renderer strips all HTML tags, including our custom
 * <blade-foreach*> and <blade-if> elements, before postProcessBlade() gets
 * a chance to convert them to @foreach / @if directives.
 */
function htmlToPlainText(html: string): string {
    const text = html
        .replace(/<head[\s\S]*?<\/head\s*>/gi, '')
        .replace(/<style[\s\S]*?<\/style\s*>/gi, '')
        .replace(/<script[\s\S]*?<\/script\s*>/gi, '')
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p\s*>/gi, '\n\n')
        .replace(/<\/div\s*>/gi, '\n')
        .replace(/<\/tr\s*>/gi, '\n')
        .replace(/<\/h[1-6]\s*>/gi, '\n\n')
        .replace(/<\/li\s*>/gi, '\n')
        // Blade directives (@foreach, {{ }}) contain no < or > and survive this step.
        .replace(/<(?:[^>"']|"[^"]*"|'[^']*')+>/g, '');

    return decodeEntities(text)
        // Zero-width and invisible characters used as email preview spacers
        .replace(/[​‌‍‎‏­﻿⁠᠎͏]/g, '')
        .replace(/[^\S\n]+/g, ' ')
        .replace(/^ +| +$/gm, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

// ---------------------------------------------------------------------------
// Main renderer
// ---------------------------------------------------------------------------

/**
 * Renders a single email template and returns the compiled Blade HTML and
 * plain-text strings. Throws on any error so the batch runner can handle
 * failures per-template without killing the whole process.
 */
async function buildOutput(templatePath: string): Promise<{ html: string; plainText: string }> {
    const resolvedPath   = path.resolve(templatePath);
    const importedModule = await import(pathToFileURL(resolvedPath).href);
    const EmailComponent = importedModule.default?.default ?? importedModule.default ?? importedModule;

    if (typeof EmailComponent !== 'function') {
        throw new Error('Default export is not a React component');
    }

    // e.g. price = '$$price$$' as any as number  =>  smartProps.price = Proxy
    const source     = fs.readFileSync(resolvedPath, 'utf-8');
    const smartProps: Record<string, unknown> = {};
    for (const [propName, varName] of extractPlaceholderProps(source)) {
        smartProps[propName] = createSmartProxy(varName);
    }

    const rawHtml = await render(
        React.createElement(EmailComponent as React.FC, smartProps),
        { pretty: true },
    );

    // Plain text is derived from the post-processed HTML so that @foreach/@if
    // directives are already plain text before tags are stripped.
    const processedHtml = postProcessBlade(rawHtml);

    return {
        html: replacePlaceholders(processedHtml),
        plainText: replacePlaceholders(htmlToPlainText(processedHtml)),
    };
}

// ---------------------------------------------------------------------------
// Batch entry point
// ---------------------------------------------------------------------------

(async () => {
    const templatePaths = process.argv.slice(2);

    if (templatePaths.length === 0) {
        console.error('Usage: tsx react-email-renderer.tsx <template1> [template2...]');
        process.exit(1);
    }

    // stdout is reserved for the JSON result read by ReactEmailBuilder, so any
    // logging done by templates is sent to stderr instead.
    console.log   = console.error;
    console.info  = console.error;
    console.debug = console.error;

    type TemplateResult = {
        path: string;
        html: string | null;
        plainText: string | null;
        error: string | null;
    };

    const results: TemplateResult[] = [];

    // Sequential on purpose: templates share module state (React, proxies).
    for (const templatePath of templatePaths) {
        try {
            const { html, plainText } = await buildOutput(templatePath);
            results.push({ path: templatePath, html, plainText, error: null });
        } catch (error) {
            results.push({
                path: templatePath,
                html: null,
                plainText: null,
                error: error instanceof Error ? error.message : String(error),
            });
        }
    }

    process.stdout.write(JSON.stringify(results));
})();
