<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * No component method may be named after one of Livewire's own.
 *
 * Found the hard way. The counter's "Call pharmacist" button was
 * wire:click="call", and the component's method was call(). In the browser that
 * expression resolves to $wire.call() - Livewire's own function for invoking a
 * method - so it asked the server to run a method with no name and got a 500.
 *
 * Every test passed, because Livewire's test helper invokes methods directly
 * and never goes through the JavaScript that broke. Nothing in the suite could
 * have caught it, so this reads the source instead.
 */
class LivewireReservedMethodTest extends TestCase
{
    /** Names that already exist on the $wire object in the browser. */
    private const RESERVED = [
        'call', 'get', 'set', 'on', 'commit', 'dispatch', 'entangle',
        'refresh', 'parent', 'upload', 'uploadMultiple', 'removeUpload',
        'cancelUpload', 'toggle', 'watch',
    ];

    public function test_no_component_method_is_named_after_a_livewire_one(): void
    {
        $offenders = [];

        foreach ($this->componentFiles() as $file) {
            $source = file_get_contents($file);

            preg_match_all('/public function ([a-zA-Z_]\w*)\s*\(/', $source, $matches);

            foreach ($matches[1] as $method) {
                if (in_array($method, self::RESERVED, true)) {
                    $offenders[] = basename($file) . '::' . $method . '()';
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These component methods share a name with one of Livewire\'s own.'],
            ['A wire:click on any of them runs Livewire\'s function instead, and the button fails in the browser'],
            ['while every test still passes. Rename them:'],
            $offenders,
        )));
    }

    /** @return string[] */
    private function componentFiles(): array
    {
        $directory = app_path('Livewire');

        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_the_check_would_notice_a_new_one(): void
    {
        // The guard above only earns its place if it can fail. This proves the
        // matching works rather than trusting an empty result.
        $source = '<?php class Example { public function call(): void {} }';

        preg_match_all('/public function ([a-zA-Z_]\w*)\s*\(/', $source, $matches);

        $this->assertContains('call', $matches[1]);
        $this->assertContains('call', self::RESERVED);
    }
}
