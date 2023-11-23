<?php

namespace Agenciafmd\Generator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CreatePackage extends Command
{
    protected $signature = 'admix:create-package';

    protected $description = 'Create a new package';

    protected array $data = [];

    public function handle(): void
    {
        $authorName = $this->ask('What is your name?', $this->processRun('git config user.name'));
        $authorEmail = $this->ask('What is your email?', $this->processRun('git config user.email'));
        $packageVendor = $this->ask('What is your package vendor?', 'Agenciafmd');
        $packageName = $this->ask('What is your package name?', 'Lead');
        $packageName = Str::of($packageName)
            ->ucfirst()
            ->__toString();
        $packageDescription = $this->ask('What is your package about?', $packageName . ' - Agência F&MD');
        $packageIcon = $this->askWithCompletion('What is your package icon? (https://tabler-icons.io/)', ['box']);

        $this->data['authorName'] = $authorName;
        $this->data['authorEmail'] = $authorEmail;

        $this->data['packageVendor'] = Str::of($packageVendor)
            ->lower()
            ->__toString();
        $this->data['packageName'] = Str::of($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['packageDescription'] = $packageDescription;
        $this->data['packageFriendlyName'] = Str::of($packageName)
            ->plural()
            ->lower()
            ->ucfirst()
            ->__toString();
        $this->data['packageIcon'] = $packageIcon;

        $this->data['namespaceVendor'] = Str::of($packageVendor)
            ->studly()
            ->__toString();
        $this->data['namespaceName'] = Str::of($packageName)
            ->plural()
            ->studly()
            ->__toString();

        $this->data['modelVariableName'] = Str::of($packageName)
            ->singular()
            ->camel()
            ->__toString();
        $this->data['modelName'] = Str::of($packageName)
            ->singular()
            ->studly()
            ->__toString();

        $this->data['className'] = $this->data['modelName'];

        /* non standard */
        $this->data['routeName'] = Str::of($packageName)
            ->plural()
            ->camel()
            ->__toString();
        $this->data['routePath'] = Str::of($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['routeModelBind'] = Str::of($packageName)
            ->singular()
            ->camel()
            ->__toString();

        $this->data['migrationTable'] = Str::of($packageName)
            ->plural()
            ->snake()
            ->__toString();
        $this->data['migrationTimestamp'] = now()
            ->format('Y_m_d_His');

        $this->data['directoryName'] = Str::of($packageName)
            ->singular()
            ->slug()
            ->__toString();

        $this->data['viewNamespace'] = 'local-' . Str::of($packageName)
            ->plural()
            ->slug()
            ->__toString();
        $this->data['viewDirectory'] = Str::of($packageName)
            ->plural()
            ->studly()
            ->__toString();

        $this->info('Creating package...');
        $this->updateStubs();

        $this->info('Updating composer.json...');
        $this->updateComposerJson();

        $this->info('Updating composer.lock...');
        $this->updateComposerLock();

        $this->info('Done!');
    }

    private function exportFile(string $source, string $target): void
    {
        $source = __DIR__ . '/../../stubs/' . $source;
        $target = base_path("packages/{$this->data['packageVendor']}/local-{$this->data['packageName']}/{$target}");

        File::ensureDirectoryExists(dirname($target), 0775);
        File::copy($source, $target);

        foreach ($this->data as $key => $value) {
            File::replaceInFile(":{$key}:", $value, $target);
        }
    }

    private function processRun(string $command): string
    {
        $result = Process::run($command);
        if ($result->successful()) {
            return Str::of($result->output())
                ->trim()
                ->__toString();
        }

        return '';
    }

    private function updateComposerJson(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $composer['repositories']["{$this->data['packageVendor']}/local-{$this->data['packageName']}"] = [
            'type' => 'path',
            'url' => "packages/{$this->data['packageVendor']}/local-{$this->data['packageName']}",
            'options' => [
                'symlink' => true,
            ],
        ];
        ksort($composer['repositories']);

        $composer['require']["{$this->data['packageVendor']}/local-{$this->data['packageName']}"] = '*';
        $requirePhp = $composer['require']['php'];
        unset($composer['require']['php']);
        ksort($composer['require']);
        $composer['require'] = array_merge(['php' => $requirePhp], $composer['require']);

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function updateComposerLock(): void
    {
        $this->processRun('composer update');
    }

    private function updateStubs(): void
    {
        $files = File::allFiles(__DIR__ . '/../../stubs/');
        collect($files)
            ->map(function ($path) {
                return $path->getRelativePathname();
            })
            ->mapWithKeys(function ($source) {
                $target = Str::of($source)
                    ->replace(array_keys($this->data), array_values($this->data))
                    ->replaceLast('.stub', '')
                    ->__toString();

                return [
                    $source => $target,
                ];
            })
            ->each(function ($target, $source) {
                $this->exportFile($source, $target);
            });
    }
}
