<?php

namespace MsCodePL\LaravelReactEmail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MsCodePL\LaravelReactEmail\Services\ReactEmailBuilder;

abstract class ReactMailable extends Mailable
{
    /**
     * Override to customise the react-email view name used for this mailable.
     *
     * By default the class name is converted to kebab-case:
     *   OrderConfirmation  →  order-confirmation
     *   WelcomeEmail       →  welcome-email
     *
     * The base class will look for:
     *   react-email.{view}       (HTML)
     *   react-email.{view}-text  (plain text)
     */
    protected function reactEmailView(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * Get the message content definition.
     *
     * Automatically resolves HTML and plain-text views from reactEmailView().
     * In the local environment, triggers an incremental build if the compiled
     * Blade file is missing or older than the TSX source.
     *
     * Override this method only when you need full control over Content options
     * (e.g. markdown view, custom theme). For simple view name changes, override
     * reactEmailView() instead.
     */
    public function content(): Content
    {
        $view = $this->reactEmailView();

        if (app()->environment('local')) {
            $this->autoBuildIfNeeded($view);
        }

        return new Content(
            html: "react-email.{$view}",
            text: "react-email.{$view}-text",
        );
    }

    /**
     * Builds the template if the compiled Blade file is missing or stale.
     * Only runs in the local environment to avoid unintended Node.js invocations
     * in staging or production.
     */
    private function autoBuildIfNeeded(string $view): void
    {
        $buildPath  = config('react-email.build_path');
        $sourcePath = config('react-email.path');
        $bladePath  = "{$buildPath}/{$view}.blade.php";
        $tsxPath    = "{$sourcePath}/{$view}.tsx";

        $isMissing = ! File::exists($bladePath);
        $isStale   = File::exists($tsxPath) && File::exists($bladePath)
            && File::lastModified($tsxPath) > File::lastModified($bladePath);

        if (File::exists($tsxPath) && ($isMissing || $isStale)) {
            app(ReactEmailBuilder::class)->buildTemplate($view);
        }
    }
}
