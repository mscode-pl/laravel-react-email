<?php

namespace MsCodePL\LaravelReactEmail;

use Illuminate\Support\HtmlString;
use ReflectionException;

trait ReactMailView
{
    /**
     * @throws ReflectionException
     */
    protected function buildView(): array
    {
        $renderer = new Renderer();
        $compiled = $renderer->compile($this->view, $this->buildViewData());

        return array_map(
            fn (string $html) => new HtmlString($html),
            $compiled
        );
    }
}
