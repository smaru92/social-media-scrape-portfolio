<?php
namespace App\Filament\Fields;

use Filament\Forms\Components\Field;

class IframeField extends Field
{
    protected string $view = 'filament-iframe-field::field';

    protected function setUp(): void
    {
        $this->configureDefaultOptions([
            'src' => '',
            'height' => '400px',
            'width' => '100%',
        ]);
    }

    public function src($src)
    {
        $this->configure(['src' => $src]);

        return $this;
    }

    public function height($height)
    {
        $this->configure(['height' => $height]);

        return $this;
    }

    public function width($width)
    {
        $this->configure(['width' => $width]);

        return $this;
    }
}
