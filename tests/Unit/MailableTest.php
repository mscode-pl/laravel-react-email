<?php

use Illuminate\Mail\Mailables\Content;
use MsCodePL\LaravelReactEmail\Exceptions\NodeNotFoundException;
use MsCodePL\LaravelReactEmail\ReactMailable;
use MsCodePL\LaravelReactEmail\Renderer;
use Mockery\MockInterface;
use Symfony\Component\Process\ExecutableFinder;

it('renders the html and text from react-email', function () {
    (new TestMailable)
        ->assertSeeInHtml('Hi,', false)
        ->assertSeeInText('Hi,');
});

it('throws an exception if node executable is not resolved', function () {
    config()->set('laravel-react-email.node_path');

    $this->expectException(NodeNotFoundException::class);

    $this->instance(
        ExecutableFinder::class,
        Mockery::mock(ExecutableFinder::class, function (MockInterface $mock) {
            $mock->shouldReceive('find')->andReturn(null);
        })
    );

    (new TestMailable)->render();
});

it('prioritises configuration value over executable finder', function () {
    config()->set('laravel-react-email.node_path', '/path/to/node');

    expect(Renderer::resolveNodeExecutable())->toEqual('/path/to/node');
});

class TestMailable extends ReactMailable
{
    public function __construct(public array $user = ['firstName' => 'Matthew']) { }

    public function content(): Content
    {
        return new Content(
            view: 'hello-user',
            with: $this->user,
        );
    }
}
