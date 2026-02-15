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
// HTML → plain text conversion
// ---------------------------------------------------------------------------

/**
 * Derives a plain-text version from already-processed HTML (which may contain
 * Blade directives such as @foreach, @if, {{ $var }}).
 *
 * We intentionally do NOT use @react-email/render with plainText:true here,
 * because that renderer strips all HTML tags — including our custom
 * <blade-foreach*> and <blade-if> elements — before postProcessBlade() gets
 * a chance to convert them to @foreach / @if directives.
 *
 * By deriving plain text from the post-processed HTML instead, Blade directives
 * are already in-place as plain text and survive the tag-stripping step.
 */
function htmlToPlainText(html: string): string {
    return html
        // Remove entire <head>…</head> block (contains <style> and <title>)
        .replace(/<head[\s\S]*?<\/head>/gi, '')
        // Remove entire <style>…</style> blocks (CSS content, @font-face, etc.)
        .replace(/<style[\s\S]*?<\/style>/gi, '')
        // Remove entire <script>…</script> blocks
        .replace(/<script[\s\S]*?<\/script>/gi, '')
        // Block elements → newlines before stripping
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n\n')
        .replace(/<\/div>/gi, '\n')
        .replace(/<\/tr>/gi, '\n')
        .replace(/<\/h[1-6]>/gi, '\n\n')
        .replace(/<\/li>/gi, '\n')
        // Strip all remaining HTML tags — Blade directives (@foreach, {{ }})
        // do NOT contain < or > so they are preserved intact.
        .replace(/<[^>]+>/g, '')
        // Decode common HTML entities
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&nbsp;/g, ' ')
        // Remove zero-width and invisible Unicode characters used as email preview spacers
        .replace(/[\u200B\u200C\u200D\u200E\u200F\u00AD\uFEFF\u2060\u180E]/g, '')
        // Collapse runs of spaces/tabs on a single line to one space
        .replace(/[^\S\n]+/g, ' ')
        // Remove lines that contain only whitespace
        .replace(/^ +$/gm, '')
        // Collapse runs of blank lines to at most two
        .replace(/\n{3,}/g, '\n\n')
        .trim();
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

        // ---- Post-process Blade directives on HTML ----
        // Must happen before plain-text derivation so @foreach/@if survive.
        const processedHtml = postProcessBlade(rawHtml);

        // ---- Derive plain text from post-processed HTML ----
        // Using plainText:true from @react-email/render would strip our custom
        // <blade-foreach*> / <blade-if> elements before we can process them.
        // Deriving from processedHtml means all Blade directives are already
        // present as plain text before the HTML-tag stripping step.
        const processedPlainText = htmlToPlainText(processedHtml);

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
