<?php

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use MsCodePL\LaravelReactEmail\ReactMailable;
use MsCodePL\LaravelReactEmail\Services\ReactEmailBuilder;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Setup test directories
    $this->templatesPath = config('react-email.path');
    $this->buildPath = config('react-email.build_path');

    File::makeDirectory($this->templatesPath, 0755, true, true);
    File::makeDirectory($this->buildPath, 0755, true, true);
});

afterEach(function () {
    // Clean up test files
    if (File::exists($this->buildPath)) {
        File::deleteDirectory($this->buildPath);
    }
});

it('can extend ReactMailable', function () {
    $mailable = new TestMailable('John Doe');

    expect($mailable)->toBeInstanceOf(ReactMailable::class);
    expect($mailable->userName)->toBe('John Doe');
});

it('references correct view paths', function () {
    $mailable = new TestMailable('John Doe');
    $content = $mailable->content();

    expect($content->html)->toBe('react-email.test-email')
        ->and($content->text)->toBe('react-email.test-email-text');
});

it('can build templates with ReactEmailBuilder', function () {
    $builder = app(ReactEmailBuilder::class);

    // Create a test template
    $templateContent = <<<'TSX'
import * as React from 'react';
import { Html, Text } from "@react-email/components";

export default function TestEmail() {
  return (
    <Html>
      <Text>Hello World</Text>
    </Html>
  );
}
TSX;

    File::put($this->templatesPath . '/test-email.tsx', $templateContent);

    [$htmlPath, $textPath] = $builder->buildTemplate('test-email');

    expect(File::exists($htmlPath))->toBeTrue()
        ->and(File::exists($textPath))->toBeTrue();
})->skip('Requires Node.js and dependencies installed');

class TestMailable extends ReactMailable
{
    public function __construct(
        public string $userName = 'User'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'react-email.test-email',
            text: 'react-email.test-email-text',
        );
    }
}
